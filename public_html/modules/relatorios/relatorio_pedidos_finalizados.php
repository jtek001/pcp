<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_inicial_form = $_GET['data_inicial'] ?? date('Y-m-01');
$data_final_form = $_GET['data_final'] ?? date('Y-m-t');

// --- Lógica de Filtragem e Busca de Dados do Relatório ---
$dados_relatorio = [];
$error_message = '';

if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';

        $sql_base = "SELECT 
                        pv.numero_pedido,
                        fc.nome as cliente_nome,
                        pv.data_pedido,
                        pv.data_entrega,
                        pv.valor_total
                     FROM pedidos_venda pv
                     JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id
                     WHERE pv.status = 'Concluido' AND pv.deleted_at IS NULL";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND DATE(pv.data_entrega) >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND DATE(pv.data_entrega) <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
        }

        $sql_base .= " ORDER BY pv.data_entrega DESC";
        
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
    <h2><i class="fas fa-check-double"></i> Relatório de Pedidos Finalizados</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_pedidos_finalizados.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="data_inicial" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_form); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="data_final" class="form-label">Data Final</label>
                        <input type="date" class="form-control" name="data_final" value="<?php echo htmlspecialchars($data_final_form); ?>">
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_pedidos_finalizados.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar'])): ?>
    <div class="card">
        <div class="card-header">Pedidos Finalizados no Período</div>
        <div class="card-body">
            <?php if (!empty($dados_relatorio)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nº Pedido</th>
                            <th>Cliente</th>
                            <th>Data do Pedido</th>
                            <th>Data de Entrega</th>
                            <th class="text-end">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_geral = 0;
                        foreach ($dados_relatorio as $pedido): 
                            $total_geral += $pedido['valor_total'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pedido['numero_pedido']); ?></td>
                            <td><?php echo htmlspecialchars($pedido['cliente_nome']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($pedido['data_entrega'])); ?></td>
                            <td class="text-end">R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td colspan="4">TOTAL GERAL</td>
                            <td class="text-end">R$ <?php echo number_format($total_geral, 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <p class="text-center mt-3">Nenhum pedido finalizado encontrado para o período selecionado.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Selecione o período desejado e clique em "Gerar Relatório" para exibir os dados.</div>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Relatórios</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
