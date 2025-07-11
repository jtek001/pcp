<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_inicial_form = $_GET['data_inicial'] ?? date('Y-m-01\T00:00');
$data_final_form = $_GET['data_final'] ?? date('Y-m-t\T23:59');

// --- Lógica para buscar dados para os filtros dinâmicos ---
$params_filtros = [$data_inicial_form, $data_final_form];

$sql_produtos = "SELECT DISTINCT p.id, p.nome, p.codigo 
                 FROM produtos p 
                 JOIN materiais_insumos_entrada mie ON p.id = mie.produto_id 
                 WHERE mie.data_entrada BETWEEN ? AND ? AND p.deleted_at IS NULL 
                 ORDER BY p.nome";
$produtos_disponiveis = $conn->execute_query($sql_produtos, $params_filtros)->fetch_all(MYSQLI_ASSOC);

$sql_fornecedores = "SELECT DISTINCT f.id, f.nome 
                     FROM fornecedores_clientes_lookup f 
                     JOIN materiais_insumos_entrada mie ON f.id = mie.fornecedor_id 
                     WHERE mie.data_entrada BETWEEN ? AND ? AND f.deleted_at IS NULL 
                     ORDER BY f.nome";
$fornecedores = $conn->execute_query($sql_fornecedores, $params_filtros)->fetch_all(MYSQLI_ASSOC);


// --- Lógica de Filtragem e Busca de Dados do Relatório ---
$dados_relatorio = [];
$filtros_aplicados = [];
$error_message = '';

if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';

        $sql_base = "SELECT 
                        mie.data_entrada,
                        p.nome as produto_nome,
                        p.codigo as produto_codigo,
                        mie.quantidade,
                        mie.valor_unitario,
                        (mie.quantidade * mie.valor_unitario) as valor_total,
                        mie.numero_nota_fiscal,
                        fc.nome as fornecedor_nome
                     FROM materiais_insumos_entrada mie
                     JOIN produtos p ON mie.produto_id = p.id
                     LEFT JOIN fornecedores_clientes_lookup fc ON mie.fornecedor_id = fc.id
                     WHERE mie.deleted_at IS NULL";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND mie.data_entrada >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
            $filtros_aplicados['Data Inicial'] = date('d/m/Y H:i', strtotime($_GET['data_inicial']));
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND mie.data_entrada <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
            $filtros_aplicados['Data Final'] = date('d/m/Y H:i', strtotime($_GET['data_final']));
        }
        if (!empty($_GET['produto_id'])) {
            $sql_base .= " AND mie.produto_id = ?";
            $params[] = $_GET['produto_id'];
            $types .= 'i';
            $filtros_aplicados['Produto'] = $conn->execute_query("SELECT nome FROM produtos WHERE id = ?", [$_GET['produto_id']])->fetch_assoc()['nome'];
        }
        if (!empty($_GET['fornecedor_id'])) {
            $sql_base .= " AND mie.fornecedor_id = ?";
            $params[] = $_GET['fornecedor_id'];
            $types .= 'i';
            $filtros_aplicados['Fornecedor'] = $conn->execute_query("SELECT nome FROM fornecedores_clientes_lookup WHERE id = ?", [$_GET['fornecedor_id']])->fetch_assoc()['nome'];
        }

        $sql_base .= " ORDER BY mie.data_entrada DESC";
        
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
    <h2><i class="fas fa-truck-loading"></i> Relatório de Entradas de Matéria-Prima</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_entradas_materiais.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="data_inicial" class="form-label">Data Inicial</label>
                        <input type="datetime-local" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_form); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="data_final" class="form-label">Data Final</label>
                        <input type="datetime-local" class="form-control" name="data_final" value="<?php echo htmlspecialchars($data_final_form); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="produto_id" class="form-label">Produto</label>
                        <select name="produto_id" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($produtos_disponiveis as $produto): ?>
                                <option value="<?php echo $produto['id']; ?>" <?php echo (($_GET['produto_id'] ?? '') == $produto['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($produto['nome'] . ' (' . $produto['codigo'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="fornecedor_id" class="form-label">Fornecedor</label>
                        <select name="fornecedor_id" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($fornecedores as $fornecedor): ?>
                                <option value="<?php echo $fornecedor['id']; ?>" <?php echo (($_GET['fornecedor_id'] ?? '') == $fornecedor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fornecedor['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_entradas_materiais.php" class="button button-clear">Limpar Filtros</a>
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
            <?php if(!empty($filtros_aplicados)): ?>
                <p><strong>Filtros aplicados:</strong> <?php echo implode('; ', array_map(fn($k, $v) => "<strong>$k:</strong> " . htmlspecialchars($v), array_keys($filtros_aplicados), $filtros_aplicados)); ?></p><hr>
            <?php endif; ?>
            <?php if (!empty($dados_relatorio)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data Entrada</th>
                            <th>Produto</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-end">Valor Unit.</th>
                            <th class="text-end">Valor Total</th>
                            <th>Nota Fiscal</th>
                            <th>Fornecedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_geral_qtd = 0; 
                        $total_geral_valor = 0;
                        foreach ($dados_relatorio as $entrada): 
                            $total_geral_qtd += $entrada['quantidade'];
                            $total_geral_valor += $entrada['valor_total'];
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($entrada['data_entrada'])); ?></td>
                            <td><?php echo htmlspecialchars($entrada['produto_nome'] . ' (' . $entrada['produto_codigo'] . ')'); ?></td>
                            <td class="text-end"><?php echo number_format($entrada['quantidade'], 2, ',', '.'); ?></td>
                            <td class="text-end">R$ <?php echo number_format($entrada['valor_unitario'], 2, ',', '.'); ?></td>
                            <td class="text-end">R$ <?php echo number_format($entrada['valor_total'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($entrada['numero_nota_fiscal']); ?></td>
                            <td><?php echo htmlspecialchars($entrada['fornecedor_nome'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td colspan="4">TOTAL GERAL</td>
                            <td class="text-end">R$ <?php echo number_format($total_geral_valor, 2, ',', '.'); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="text-center mt-3">Nenhuma entrada encontrada para os filtros selecionados.</p>
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
