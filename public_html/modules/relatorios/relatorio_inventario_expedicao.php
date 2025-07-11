<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- OBSERVAÇÃO: Lógica de filtros dinâmicos atualizada ---
// As opções dos filtros agora são baseadas apenas nos produtos que têm saldo na expedição.

// Subquery para encontrar os IDs dos produtos com saldo na expedição
$subquery_produtos_na_expedicao = "SELECT el.produto_id
                                   FROM expedicao_log el
                                   GROUP BY el.produto_id, el.lote_numero
                                   HAVING SUM(CASE WHEN el.tipo_movimentacao = 'entrada' THEN el.quantidade ELSE -el.quantidade END) > 0";

function get_dynamic_filter_options($conn, $column, $subquery) {
    $sql = "SELECT DISTINCT p.$column 
            FROM produtos p 
            WHERE p.id IN ($subquery) 
              AND p.$column IS NOT NULL AND p.$column != '' 
            ORDER BY p.$column ASC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Busca opções para os filtros
$grupos = get_dynamic_filter_options($conn, 'grupo', $subquery_produtos_na_expedicao);
$modelos = get_dynamic_filter_options($conn, 'modelo', $subquery_produtos_na_expedicao);
$acabamentos = get_dynamic_filter_options($conn, 'acabamento', $subquery_produtos_na_expedicao);
$familias = get_dynamic_filter_options($conn, 'familia', $subquery_produtos_na_expedicao);

// Busca pedidos que têm lotes na expedição
$sql_pedidos = "SELECT DISTINCT op.numero_pedido 
                FROM ordens_producao op
                WHERE op.numero_pedido IS NOT NULL AND op.numero_pedido != ''
                AND EXISTS (
                    SELECT 1 FROM expedicao_log el
                    JOIN apontamentos_producao ap ON el.lote_numero = ap.lote_numero
                    WHERE ap.ordem_producao_id = op.id
                    GROUP BY el.lote_numero
                    HAVING SUM(CASE WHEN el.tipo_movimentacao = 'entrada' THEN el.quantidade ELSE -el.quantidade END) > 0
                )
                ORDER BY op.numero_pedido DESC";
$pedidos = $conn->query($sql_pedidos)->fetch_all(MYSQLI_ASSOC);


// --- Lógica de Filtragem e Busca de Dados do Relatório ---
$dados_relatorio = [];
$error_message = '';
$filtros_aplicados = [];

if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';

        $sql_base = "SELECT 
                        el.lote_numero,
                        p.codigo as produto_codigo,
                        p.nome as produto_nome,
                        p.grupo,
                        p.unidade_medida,
                        p.unidade_medida2,
                        SUM(CASE WHEN el.tipo_movimentacao = 'entrada' THEN el.quantidade ELSE -el.quantidade END) as saldo_expedicao,
                        calcularVolume(
                            SUM(CASE WHEN el.tipo_movimentacao = 'entrada' THEN el.quantidade ELSE -el.quantidade END), 
                            p.espessura, 
                            p.largura, 
                            p.comprimento
                        ) as volume_m3
                    FROM expedicao_log el
                    JOIN produtos p ON el.produto_id = p.id
                    LEFT JOIN apontamentos_producao ap ON el.lote_numero = ap.lote_numero
                    LEFT JOIN ordens_producao op ON ap.ordem_producao_id = op.id
                    WHERE 1=1";

        if (!empty($_GET['grupo'])) {
            $sql_base .= " AND p.grupo = ?";
            $params[] = $_GET['grupo'];
            $types .= 's';
            $filtros_aplicados['Grupo'] = $_GET['grupo'];
        }
        if (!empty($_GET['modelo'])) {
            $sql_base .= " AND p.modelo = ?";
            $params[] = $_GET['modelo'];
            $types .= 's';
            $filtros_aplicados['Modelo'] = $_GET['modelo'];
        }
        if (!empty($_GET['acabamento'])) {
            $sql_base .= " AND p.acabamento = ?";
            $params[] = $_GET['acabamento'];
            $types .= 's';
            $filtros_aplicados['Acabamento'] = $_GET['acabamento'];
        }
        if (!empty($_GET['familia'])) {
            $sql_base .= " AND p.familia = ?";
            $params[] = $_GET['familia'];
            $types .= 's';
            $filtros_aplicados['Família'] = $_GET['familia'];
        }
        if (!empty($_GET['numero_pedido'])) {
            $sql_base .= " AND op.numero_pedido = ?";
            $params[] = $_GET['numero_pedido'];
            $types .= 's';
            $filtros_aplicados['Pedido'] = $_GET['numero_pedido'];
        }

        $sql_base .= " GROUP BY el.lote_numero, p.id, p.codigo, p.nome, p.unidade_medida, p.unidade_medida2, p.espessura, p.largura, p.comprimento
                      HAVING saldo_expedicao > 0
                      ORDER BY p.grupo, p.nome, el.lote_numero";
        
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
?>

<div class="container mt-4">
    <h2><i class="fas fa-shipping-fast"></i> Relatório de Inventário da Expedição</h2>
    <p class="lead">Lista de todos os lotes de produção com saldo disponível na área de expedição.</p>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_inventario_expedicao.php" method="GET">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="grupo" class="form-label">Grupo</label>
                        <select name="grupo" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($grupos as $item): ?><option value="<?php echo htmlspecialchars($item['grupo']); ?>" <?php echo (($_GET['grupo'] ?? '') == $item['grupo']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['grupo']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="modelo" class="form-label">Modelo</label>
                        <select name="modelo" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($modelos as $item): ?><option value="<?php echo htmlspecialchars($item['modelo']); ?>" <?php echo (($_GET['modelo'] ?? '') == $item['modelo']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['modelo']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="acabamento" class="form-label">Acabamento</label>
                        <select name="acabamento" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($acabamentos as $item): ?><option value="<?php echo htmlspecialchars($item['acabamento']); ?>" <?php echo (($_GET['acabamento'] ?? '') == $item['acabamento']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['acabamento']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="familia" class="form-label">Família</label>
                        <select name="familia" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach($familias as $item): ?><option value="<?php echo htmlspecialchars($item['familia']); ?>" <?php echo (($_GET['familia'] ?? '') == $item['familia']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['familia']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="numero_pedido" class="form-label">Pedido</label>
                        <select name="numero_pedido" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($pedidos as $pedido): ?>
                                <option value="<?php echo htmlspecialchars($pedido['numero_pedido']); ?>" <?php echo (($_GET['numero_pedido'] ?? '') == $pedido['numero_pedido']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pedido['numero_pedido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_inventario_expedicao.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar'])): ?>
    <div class="card">
        <div class="card-header">Lotes na Expedição</div>
        <div class="card-body">
            <?php if(!empty($filtros_aplicados)): ?>
                <p><strong>Filtros aplicados:</strong> <?php echo implode('; ', array_map(fn($k, $v) => "<strong>$k:</strong> " . htmlspecialchars($v), array_keys($filtros_aplicados), $filtros_aplicados)); ?></p><hr>
            <?php endif; ?>
            <?php if (!empty($dados_relatorio)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Lote</th>
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
                        foreach ($dados_relatorio as $item): 
                            $total_geral_qtd += $item['saldo_expedicao'];
                            $total_geral_vol += (strtoupper($item['unidade_medida2']) === 'M3' ? $item['volume_m3'] : 0);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['lote_numero']); ?></td>
                            <td><?php echo htmlspecialchars($item['grupo']); ?></td>
                            <td><?php echo htmlspecialchars($item['produto_nome'] . ' (' . $item['produto_codigo'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($item['unidade_medida']); ?></td>
                            <td class="text-end"><?php echo number_format($item['saldo_expedicao'], 2, ',', '.'); ?></td>
                            <td class="text-end">
                                <?php if (strtoupper($item['unidade_medida2']) === 'M3'): ?>
                                    <?php echo number_format($item['volume_m3'], 4, ',', '.'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td colspan="4">TOTAL GERAL</td>
                            <td class="text-end"><?php echo number_format($total_geral_qtd, 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($total_geral_vol, 4, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="text-center mt-3">Nenhum lote com saldo encontrado na expedição para os filtros selecionados.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Selecione os filtros desejados e clique em "Gerar Relatório" para exibir os dados.</div>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Relatórios</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
