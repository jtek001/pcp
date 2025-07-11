<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_inicial_form = $_GET['data_inicial'] ?? date('Y-m-01\T00:00');
$data_final_form = $_GET['data_final'] ?? date('Y-m-t\T23:59');

// --- Lógica para buscar dados para os filtros dinâmicos ---
function get_dynamic_filter_options($conn, $column) {
    $sql = "SELECT DISTINCT p.$column FROM produtos p JOIN expedicao_log el ON p.id = el.produto_id WHERE el.tipo_movimentacao = 'saida' AND p.$column IS NOT NULL AND p.$column != '' ORDER BY p.$column ASC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$grupos = get_dynamic_filter_options($conn, 'grupo');
$modelos = get_dynamic_filter_options($conn, 'modelo');
$acabamentos = get_dynamic_filter_options($conn, 'acabamento');
$familias = get_dynamic_filter_options($conn, 'familia');

// OBSERVAÇÃO: A consulta para buscar os pedidos agora usa a ligação direta com o log de expedição.
$pedidos = $conn->query("SELECT DISTINCT pv.id, pv.numero_pedido, fc.nome as cliente_nome 
                        FROM pedidos_venda pv 
                        JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id 
                        JOIN expedicao_log el ON pv.id = el.pedido_venda_id 
                        WHERE el.tipo_movimentacao = 'saida' 
                        ORDER BY pv.id DESC")->fetch_all(MYSQLI_ASSOC);
$notas_fiscais = $conn->query("SELECT DISTINCT nota_fiscal_saida FROM expedicao_log WHERE tipo_movimentacao = 'saida' AND nota_fiscal_saida IS NOT NULL ORDER BY nota_fiscal_saida")->fetch_all(MYSQLI_ASSOC);

// --- Lógica de Filtragem e Busca de Dados do Relatório ---
$dados_relatorio = [];
$filtros_aplicados = [];
$error_message = '';

if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';

        // OBSERVAÇÃO: A consulta foi corrigida para usar a ligação direta entre expedicao_log e pedidos_venda.
        $sql_base = "SELECT 
                        el.data_movimentacao,
                        el.nota_fiscal_saida,
                        pv.numero_pedido,
                        fc.nome as cliente_nome,
                        p.codigo as produto_codigo,
                        p.nome as produto_nome,
                        el.quantidade,
                        el.lote_numero,
                        p.unidade_medida2,
                        calcularVolume(el.quantidade, p.espessura, p.largura, p.comprimento) as volume_m3
                     FROM expedicao_log el
                     JOIN produtos p ON el.produto_id = p.id
                     LEFT JOIN pedidos_venda pv ON el.pedido_venda_id = pv.id
                     LEFT JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id
                     WHERE el.tipo_movimentacao = 'saida'";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND el.data_movimentacao >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
            $filtros_aplicados['Data Inicial'] = date('d/m/Y H:i', strtotime($_GET['data_inicial']));
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND el.data_movimentacao <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
            $filtros_aplicados['Data Final'] = date('d/m/Y H:i', strtotime($_GET['data_final']));
        }
        if (!empty($_GET['grupo'])) $sql_base .= " AND p.grupo = '" . $conn->real_escape_string($_GET['grupo']) . "'";
        if (!empty($_GET['modelo'])) $sql_base .= " AND p.modelo = '" . $conn->real_escape_string($_GET['modelo']) . "'";
        if (!empty($_GET['acabamento'])) $sql_base .= " AND p.acabamento = '" . $conn->real_escape_string($_GET['acabamento']) . "'";
        if (!empty($_GET['familia'])) $sql_base .= " AND p.familia = '" . $conn->real_escape_string($_GET['familia']) . "'";
        if (!empty($_GET['pedido_id'])) $sql_base .= " AND el.pedido_venda_id = " . (int)$_GET['pedido_id'];
        if (!empty($_GET['nota_fiscal'])) $sql_base .= " AND el.nota_fiscal_saida = '" . $conn->real_escape_string($_GET['nota_fiscal']) . "'";
        
        $sql_base .= " ORDER BY el.data_movimentacao DESC";
        
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
    <h2><i class="fas fa-truck"></i> Relatório de Produtos Expedidos</h2>
    <p class="lead">Lista de todos os produtos que saíram da expedição para clientes.</p>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_produtos_expedidos.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3"><label>Data Inicial</label><input type="datetime-local" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_form); ?>"></div>
                    <div class="col-md-6 mb-3"><label>Data Final</label><input type="datetime-local" class="form-control" name="data_final" value="<?php echo htmlspecialchars($data_final_form); ?>"></div>
                    <div class="col-md-6 mb-3"><label>Pedido</label><select name="pedido_id" class="form-select"><option value="">Todos</option><?php foreach($pedidos as $pedido): ?><option value="<?php echo $pedido['id']; ?>" <?php echo (($_GET['pedido_id'] ?? '') == $pedido['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pedido['numero_pedido'] . ' - ' . $pedido['cliente_nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Nota Fiscal</label><select name="nota_fiscal" class="form-select"><option value="">Todas</option><?php foreach($notas_fiscais as $nf): ?><option value="<?php echo htmlspecialchars($nf['nota_fiscal_saida']); ?>" <?php echo (($_GET['nota_fiscal'] ?? '') == $nf['nota_fiscal_saida']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($nf['nota_fiscal_saida']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Grupo</label><select name="grupo" class="form-select"><option value="">Todos</option><?php foreach($grupos as $item): ?><option value="<?php echo htmlspecialchars($item['grupo']); ?>" <?php echo (($_GET['grupo'] ?? '') == $item['grupo']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['grupo']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Modelo</label><select name="modelo" class="form-select"><option value="">Todos</option><?php foreach($modelos as $item): ?><option value="<?php echo htmlspecialchars($item['modelo']); ?>" <?php echo (($_GET['modelo'] ?? '') == $item['modelo']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['modelo']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Acabamento</label><select name="acabamento" class="form-select"><option value="">Todos</option><?php foreach($acabamentos as $item): ?><option value="<?php echo htmlspecialchars($item['acabamento']); ?>" <?php echo (($_GET['acabamento'] ?? '') == $item['acabamento']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['acabamento']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Família</label><select name="familia" class="form-select"><option value="">Todos</option><?php foreach($familias as $item): ?><option value="<?php echo htmlspecialchars($item['familia']); ?>" <?php echo (($_GET['familia'] ?? '') == $item['familia']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($item['familia']); ?></option><?php endforeach; ?></select></div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_produtos_expedidos.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar'])): ?>
    <div class="card">
        <div class="card-header">Produtos Expedidos</div>
        <div class="card-body">
            <?php if (!empty($dados_relatorio)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data Saída</th>
                            <th>Lote</th>
                            <th>Produto</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-end">Volume (M³)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_geral_qtd = 0; 
                        $total_geral_vol = 0;
                        foreach ($dados_relatorio as $item): 
                            $total_geral_qtd += $item['quantidade'];
                            $total_geral_vol += (strtoupper($item['unidade_medida2']) === 'M3' ? $item['volume_m3'] : 0);
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($item['data_movimentacao'])); ?></td>
                            <td><?php echo htmlspecialchars($item['lote_numero']); ?></td>
                            <td><?php echo htmlspecialchars($item['produto_nome'] . ' (' . $item['produto_codigo'] . ')'); ?></td>
                            <td class="text-end"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
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
                            <td colspan="3">TOTAL GERAL</td>
                            <td class="text-end"><?php echo number_format($total_geral_qtd, 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($total_geral_vol, 4, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="text-center mt-3">Nenhum produto expedido encontrado para os filtros selecionados.</p>
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
