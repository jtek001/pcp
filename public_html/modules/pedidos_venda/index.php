<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// Lógica de Paginação
$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Contar o total de itens
$total_items = $conn->query("SELECT COUNT(*) AS total FROM pedidos_venda WHERE deleted_at IS NULL")->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os pedidos para a página atual
$sql = "SELECT pv.*, fc.nome as cliente_nome
        FROM pedidos_venda pv
        JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id
        WHERE pv.deleted_at IS NULL
        ORDER BY pv.id DESC
        LIMIT ? OFFSET ?";
$pedidos = $conn->execute_query($sql, [$items_per_page, $offset])->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-shopping-cart"></i> Pedidos de Venda</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Novo Pedido</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Nº Pedido</th>
                <th>Cliente</th>
                <th>Data do Pedido</th>
                <th>Previsão de Entrega</th>
                <th>Valor Total</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <td><?php echo htmlspecialchars($pedido['numero_pedido']); ?></td>
                    <td><?php echo htmlspecialchars($pedido['cliente_nome']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?></td>
                    <td><?php echo $pedido['data_previsao_entrega'] ? date('d/m/Y', strtotime($pedido['data_previsao_entrega'])) : 'N/A'; ?></td>
                    <td class="text-end">R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></td>
                    <td class="text-center">
                        <?php
                        // OBSERVAÇÃO: Lógica para exibir ícones de status
                        if ($pedido['status'] === 'Concluido') {
                            echo '<i class="fas fa-check-circle text-success" title="Concluído" style="font-size: 1.3em;"></i>';
                        } elseif ($pedido['status'] === 'Cancelado') {
                            echo '<i class="fas fa-times-circle text-danger" title="Cancelado" style="font-size: 1.3em;"></i>';
                        } elseif ($pedido['status'] === 'Aprovado') {
                            echo '<i class="fas fa-thumbs-up text-primary" title="Aprovado" style="font-size: 1.3em;"></i>';
                        } else {
                            echo '<span class="status-' . strtolower(str_replace(' ', '-', htmlspecialchars($pedido['status']))) . '">' . htmlspecialchars($pedido['status']) . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="editar.php?id=<?php echo $pedido['id']; ?>" class="button small">Gerir Itens</a>
                        <?php
                        // Lógica para desativar o botão de exclusão
                        $is_deletable = !in_array($pedido['status'], ['Concluido', 'Cancelado']);
                        $disabled_class = $is_deletable ? '' : 'disabled';
                        $onclick_action = $is_deletable ? "return confirm('Tem certeza que deseja excluir este pedido?');" : "return false;";
                        ?>
                        <a href="excluir.php?id=<?php echo $pedido['id']; ?>" class="button delete small <?php echo $disabled_class; ?>" onclick="<?php echo $onclick_action; ?>">Excluir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="page-link <?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
?>
