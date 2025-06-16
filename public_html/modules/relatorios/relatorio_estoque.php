<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// Função para buscar valores distintos para os filtros
function get_filter_options($conn, $column) {
    $sql = "SELECT DISTINCT $column FROM produtos WHERE $column IS NOT NULL AND $column != '' AND deleted_at IS NULL ORDER BY $column ASC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Busca opões para os filtros
$grupos = get_filter_options($conn, 'grupo');
$modelos = get_filter_options($conn, 'modelo');
$acabamentos = get_filter_options($conn, 'acabamento');
$familias = get_filter_options($conn, 'familia');

// --- Lógica de Filtragem ---
$dados_relatorio = [];
$error_message = '';
$filtro_saldo_selecionado = $_GET['filtro_saldo'] ?? ''; // Padrão agora é 'Todos'

// OBSERVAÇÃO: A busca agora só é executada quando o botão "Gerar Relatório"  clicado.
if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';
        $sql_base = "SELECT p.*, calcularVolume(p.estoque_atual, p.espessura, p.largura, p.comprimento) AS volume_m3 FROM produtos p WHERE p.deleted_at IS NULL";

        if (!empty($_GET['grupo'])) {
            $sql_base .= " AND p.grupo = ?";
            $params[] = $_GET['grupo'];
            $types .= 's';
        }
        if (!empty($_GET['modelo'])) {
            $sql_base .= " AND p.modelo = ?";
            $params[] = $_GET['modelo'];
            $types .= 's';
        }
        if (!empty($_GET['acabamento'])) {
            $sql_base .= " AND p.acabamento = ?";
            $params[] = $_GET['acabamento'];
            $types .= 's';
        }
        if (!empty($_GET['familia'])) {
            $sql_base .= " AND p.familia = ?";
            $params[] = $_GET['familia'];
            $types .= 's';
        }
        // Aplica o filtro de saldo com base na seleção do formulário
        if ($filtro_saldo_selecionado === 'com_saldo') {
            $sql_base .= " AND p.estoque_atual >= 1";
        }

        $sql_base .= " ORDER BY p.grupo ASC, p.nome ASC";
        
        $stmt = $conn->prepare($sql_base);
        if ($stmt === false) throw new Exception("Erro ao preparar a consulta: " . $conn->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $dados_relatorio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

    } catch (Exception $e) {
        $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
    }
}

// Monta a query string para o botão de exportação
$export_query_string = http_build_query(array_filter($_GET));
?>

<div class="container mt-4">
    <h2>Relatório de Posição de Estoque</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_estoque.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="grupo" class="form-label">Grupo</label>
                        <select name="grupo" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($grupos as $item): ?><option value="<?php echo htmlspecialchars($item['grupo']); ?>" <?php echo (($_GET['grupo'] ?? '') == $item['grupo']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['grupo']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="modelo" class="form-label">Modelo</label>
                        <select name="modelo" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($modelos as $item): ?><option value="<?php echo htmlspecialchars($item['modelo']); ?>" <?php echo (($_GET['modelo'] ?? '') == $item['modelo']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['modelo']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="acabamento" class="form-label">Acabamento</label>
                        <select name="acabamento" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($acabamentos as $item): ?><option value="<?php echo htmlspecialchars($item['acabamento']); ?>" <?php echo (($_GET['acabamento'] ?? '') == $item['acabamento']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['acabamento']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="familia" class="form-label">Família</label>
                        <select name="familia" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($familias as $item): ?><option value="<?php echo htmlspecialchars($item['familia']); ?>" <?php echo (($_GET['familia'] ?? '') == $item['familia']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['familia']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="filtro_saldo" class="form-label">Saldo</label>
                        <select name="filtro_saldo" id="filtro_saldo" class="form-select">
                            <option value="" <?php echo ($filtro_saldo_selecionado == '') ? 'selected' : ''; ?>>Todos</option>
                            <option value="com_saldo" <?php echo ($filtro_saldo_selecionado == 'com_saldo') ? 'selected' : ''; ?>>Saldo [+]</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_estoque.php" class="button button-clear">Limpar Filtros</a>
                <a href="exportar_estoque.php?<?php echo $export_query_string; ?>" class="button" style="background-color: #16a085;">
                    <i class="fas fa-file-excel"></i> Exportar para Excel
                </a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['filtrar'])): ?>
        <div class="card">
            <div class="card-header">Resultados</div>
            <div class="card-body">
              <h4>Posição Atual de Estoque</h4>
                <?php if (!empty($dados_relatorio)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cdigo</th>
                            <th>Grupo</th>
                            <th>Produto</th>
                            <th>Un. Medida</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-end">Volume (M³)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_geral_qtd = 0; 
                        $total_geral_vol = 0;
                        foreach ($dados_relatorio as $produto): 
                            $total_geral_qtd += $produto['estoque_atual'];
                            $total_geral_vol += (strtoupper($produto['unidade_medida2']) === 'M3' ? $produto['volume_m3'] : 0);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($produto['codigo']); ?></td>
                            <td><?php echo htmlspecialchars($produto['grupo']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($produto['nome']); ?>
                                <?php if ($produto['estoque_atual'] < $produto['estoque_minimo']): ?>
                                    <i class="fas fa-exclamation-triangle text-warning" title="Estoque abaixo do mínimo!"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($produto['unidade_medida']); ?></td>
                            <td class="text-end"><?php echo number_format($produto['estoque_atual'], 2, ',', '.'); ?></td>
                            <td class="text-end">
                                <?php if (strtoupper($produto['unidade_medida2']) === 'M3'): ?>
                                    <?php echo number_format($produto['volume_m3'], 2, ',', '.'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td colspan="4">TOTAL GERAL</td>
                            <td class="text-end"><?php echo number_format($total_geral_qtd, 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($total_geral_vol, 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                    <p class="text-center mt-3">Nenhum produto encontrado para os filtros selecionados.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Relatórios</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
?>
