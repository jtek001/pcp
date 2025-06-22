<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'nome'); 

$filter_options = [
    'nome' => 'Nome',
    'cnpj' => 'CNPJ/CPF',
    'tipo' => 'Tipo'
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
$sql_count = "SELECT COUNT(*) AS total_items FROM fornecedores_clientes_lookup" . $where_clause;
$result_count = $conn->execute_query($sql_count, $params);
$total_items = $result_count->fetch_assoc()['total_items'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os itens para a página atual
$sql_fetch = "SELECT * FROM fornecedores_clientes_lookup" . $where_clause . " ORDER BY nome ASC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params, [$items_per_page, $offset]);

$result_fetch = $conn->execute_query($sql_fetch, $params_fetch);
$fornecedores_clientes = $result_fetch->fetch_all(MYSQLI_ASSOC);

?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-handshake"></i> Clientes & Fornecedores</h2>
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

    <?php if (!empty($fornecedores_clientes)): ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CNPJ/CPF</th>
                    <th>Tipo</th>
                    <th>Contato</th>
                    <th>Email</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fornecedores_clientes as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nome']); ?></td>
                        <td><?php echo htmlspecialchars($item['cnpj']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($item['tipo'])); ?></td>
                        <td><?php echo htmlspecialchars($item['contato']); ?></td>
                        <td><?php echo htmlspecialchars($item['email']); ?></td>
                        <td>
                            <a href="editar.php?id=<?php echo $item['id']; ?>" class="button edit small">Editar</a>
                            <button class="button delete small" onclick="showDeleteModal('fornecedores_clientes', <?php echo $item['id']; ?>)">Excluir</button>
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
        <p style="text-align: center; margin-top: 20px;">Nenhum cliente ou fornecedor encontrado.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
