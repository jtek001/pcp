<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// Lógica para pesquisa
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'produto_pai'); 

$filter_options = [
    'produto_pai' => 'Produto Pai',
    'componente' => 'Componente'
];

// Lógica de Paginação
$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE
$where_clause = " WHERE lm.deleted_at IS NULL ";
$params = [];

if (!empty($search_term)) {
    if ($filter_field === 'produto_pai') {
        $where_clause .= " AND (p_pai.nome LIKE ? OR p_pai.codigo LIKE ?)";
    } else { // componente
        $where_clause .= " AND (p_filho.nome LIKE ? OR p_filho.codigo LIKE ?)";
    }
    $params[] = '%' . $search_term . '%';
    $params[] = '%' . $search_term . '%';
}

// Contar o total de itens
$sql_count = "SELECT COUNT(lm.id) AS total_items 
              FROM lista_materiais lm
              JOIN produtos p_pai ON lm.produto_pai_id = p_pai.id
              JOIN produtos p_filho ON lm.produto_filho_id = p_filho.id
              " . $where_clause;
$result_count = $conn->execute_query($sql_count, $params);
$total_items = $result_count->fetch_assoc()['total_items'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os itens para a página atual
$sql_fetch = "SELECT 
                lm.id, 
                p_pai.nome as produto_pai_nome, 
                p_pai.codigo as produto_pai_codigo,
                p_filho.nome as componente_nome,
                p_filho.codigo as componente_codigo,
                lm.quantidade_necessaria
              FROM lista_materiais lm
              JOIN produtos p_pai ON lm.produto_pai_id = p_pai.id
              JOIN produtos p_filho ON lm.produto_filho_id = p_filho.id
              " . $where_clause . " 
              ORDER BY p_pai.nome, p_filho.nome 
              LIMIT ? OFFSET ?";
$params_fetch = array_merge($params, [$items_per_page, $offset]);

$result_fetch = $conn->execute_query($sql_fetch, $params_fetch);
$boms = $result_fetch->fetch_all(MYSQLI_ASSOC);

?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-sitemap"></i> Lista de Materiais (BoM)</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Adicionar Composição</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="search-container">
        <form action="index.php" method="GET" class="search-form-inline">
            <input type="text" name="search_term" placeholder="Buscar..." value="<?php echo htmlspecialchars($search_term); ?>">
            <select name="filter_field">
                <?php foreach ($filter_options as $field_value => $field_label): ?>
                    <option value="<?php echo htmlspecialchars($field_value); ?>" <?php echo ($filter_field === $field_value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($field_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Pesquisar</button>
            <?php if (!empty($search_term)): ?>
                <a href="index.php" class="button button-clear">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($boms)): ?>
        <table>
            <thead>
                <tr>
                    <th>Produto Pai</th>
                    <th>Componente</th>
                    <th class="text-end">Quantidade Necessária</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($boms as $bom): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bom['produto_pai_nome'] . ' (' . $bom['produto_pai_codigo'] . ')'); ?></td>
                        <td><?php echo htmlspecialchars($bom['componente_nome'] . ' (' . $bom['componente_codigo'] . ')'); ?></td>
                        <td class="text-end"><?php echo number_format($bom['quantidade_necessaria'], 2, ',', '.'); ?></td>
                        <td>
                            <a href="editar.php?id=<?php echo $bom['id']; ?>" class="button edit small">Editar</a>
                            <button class="button delete small" onclick="showDeleteModal('bom', <?php echo $bom['id']; ?>)">Excluir</button>
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
        <p style="text-align: center; margin-top: 20px;">Nenhuma composição encontrada.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
