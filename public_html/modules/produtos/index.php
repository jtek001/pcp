<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'nome'); 

$filter_options = [
    'codigo' => 'Código',
    'nome' => 'Nome',
    'grupo' => 'Grupo'
];

// Lógica de Paginação
$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE
$where_clause = " WHERE deleted_at IS NULL ";
$params = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params[] = '%' . $search_term . '%';
}

// Contar o total de itens
$sql_count = "SELECT COUNT(*) AS total_items FROM produtos" . $where_clause;
$result_count = $conn->execute_query($sql_count, $params);
$total_items = $result_count->fetch_assoc()['total_items'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os itens para a página atual
$sql_fetch = "SELECT * FROM produtos" . $where_clause . " ORDER BY nome ASC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params, [$items_per_page, $offset]);

$result_fetch = $conn->execute_query($sql_fetch, $params_fetch);
$produtos = $result_fetch->fetch_all(MYSQLI_ASSOC);

?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-box-open"></i> Gestão de Produtos</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Adicionar Novo</a>
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

    <?php if (!empty($produtos)): ?>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Grupo</th>
                    <th>Un. Medida</th>
                    <th class="text-end">Estoque Atual</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($produto['codigo']); ?></td>
                        <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                        <td><?php echo htmlspecialchars($produto['grupo']); ?></td>
                        <td><?php echo htmlspecialchars($produto['unidade_medida']); ?></td>
                        <td class="text-end"><?php echo number_format($produto['estoque_atual'], 2, ',', '.'); ?></td>
                        <td>
                            <a href="editar.php?id=<?php echo $produto['id']; ?>" class="button edit small">Editar</a>
                            <button class="button delete small" onclick="showDeleteModal('produtos', <?php echo $produto['id']; ?>)">Excluir</button>
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
        <p style="text-align: center; margin-top: 20px;">Nenhum produto encontrado.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
