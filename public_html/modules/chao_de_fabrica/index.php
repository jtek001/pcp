<?php
// modules/chao_de_fabrica/index.php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'op.numero_op'); 

$filter_options = [
    'op.numero_op' => 'Número da OP',
    'p.nome' => 'Produto',
    'p.codigo' => 'Código Produto',
    'm.nome' => 'Máquina',
    'op.numero_pedido' => 'Número do Pedido' // Filtro adicionado
];

$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

$where_clause = " WHERE op.deleted_at IS NULL AND op.status IN ('pendente', 'em_producao', 'concluida') AND pv.status = 'Aprovado' "; 
$params = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params[] = '%' . $search_term . '%';
}

$sql_count = "SELECT COUNT(DISTINCT op.id) AS total_items 
              FROM ordens_producao op 
              JOIN produtos p ON op.produto_id = p.id 
              LEFT JOIN maquinas m ON op.maquina_id = m.id
              JOIN pedidos_venda pv ON op.numero_pedido = pv.numero_pedido" . $where_clause;
$result_count = $conn->execute_query($sql_count, $params);
$total_items = $result_count->fetch_assoc()['total_items'] ?? 0;

$total_pages = ceil($total_items / $items_per_page);

$sql_fetch = "SELECT 
                op.id, op.numero_op, op.quantidade_produzir, op.status, 
                p.nome AS produto_nome, p.codigo AS produto_codigo, 
                m.nome AS maquina_nome,
                (SELECT SUM(COALESCE(ap.quantidade_produzida, 0)) FROM apontamentos_producao ap WHERE ap.ordem_producao_id = op.id AND ap.deleted_at IS NULL) AS quantidade_apontada
              FROM 
                ordens_producao op
              JOIN 
                produtos p ON op.produto_id = p.id
              LEFT JOIN 
                maquinas m ON op.maquina_id = m.id
              JOIN 
                pedidos_venda pv ON op.numero_pedido = pv.numero_pedido
              " . $where_clause . " 
              GROUP BY op.id 
              ORDER BY op.id DESC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params, [$items_per_page, $offset]);
$ops_list = $conn->execute_query($sql_fetch, $params_fetch)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-cogs"></i> Chão de Fábrica - Apontamento de Produção</h2>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="search-container">
        <form action="index.php" method="GET" class="search-form-inline">
            <input type="text" name="search_term" placeholder="Buscar por OP, Produto, Pedido..." value="<?php echo htmlspecialchars($search_term); ?>">
            <select name="filter_field">
                <?php foreach ($filter_options as $field_value => $field_label): ?>
                    <option value="<?php echo htmlspecialchars($field_value); ?>" <?php echo ($filter_field === $field_value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($field_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Pesquisar</button>
            <?php if (!empty($search_term)): ?>
                <a href="index.php" class="button button-clear">Limpar Pesquisa</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($ops_list)): ?>
        <table>
            <thead>
                <tr>
                    <th>OP</th>
                    <th>Produto</th>
                    <th>Programado</th>
                    <th>Apontado</th>
                    <th>Máquina</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ops_list as $op): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($op['numero_op']); ?></td>
                        <td><?php echo htmlspecialchars($op['produto_nome'] ); ?></td>
                        <td class="text-end"><?php echo number_format($op['quantidade_produzir'], 2, ',', '.'); ?></td>
                        <td class="text-end"><?php echo number_format($op['quantidade_apontada'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($op['maquina_nome'] ?? 'N/A'); ?></td>
                        <td class="text-center">
                            <?php
                            if ($op['status'] === 'concluida') {
                                echo '<i class="fas fa-check-circle text-success" title="Concluída" style="font-size: 1.3em;"></i>';
                            } elseif ($op['status'] === 'cancelada') {
                                echo '<i class="fas fa-times-circle text-danger" title="Cancelada" style="font-size: 1.3em;"></i>';
                            } else {
                                echo '<span class="status-' . htmlspecialchars(strtolower($op['status'])) . '">' . htmlspecialchars(ucfirst($op['status'])) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px; align-items: center;">
                                <a href="apontar.php?id=<?php echo $op['id']; ?>" class="button small add">Apontar</a>
                                <a href="consumo.php?op_id=<?php echo $op['id']; ?>" class="button small">Consumir</a>
                                <a href="insumos.php?id=<?php echo $op['id']; ?>" class="button small edit">Insumos</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php
            $pagination_base_query = http_build_query(array_filter(['search_term' => $search_term, 'filter_field' => $filter_field]));
            if ($total_pages > 1) {
                if ($current_page > 1) {
                    echo '<a href="?' . $pagination_base_query . '&page=' . ($current_page - 1) . '" class="page-link">&laquo; Anterior</a>';
                }
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active_class = ($i == $current_page) ? 'active' : '';
                    echo '<a href="?' . $pagination_base_query . '&page=' . $i . '" class="page-link ' . $active_class . '">' . $i . '</a>';
                }
                if ($current_page < $total_pages) {
                    echo '<a href="?' . $pagination_base_query . '&page=' . ($current_page + 1) . '" class="page-link">Próxima &raquo;</a>';
                }
            }
            ?>
        </div>

    <?php else: ?>
        <p style="text-align: center; margin-top: 20px;">Nenhuma Ordem de Produção de pedido aprovado encontrada.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
