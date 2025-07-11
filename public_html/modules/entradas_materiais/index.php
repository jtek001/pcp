<?php
// modules/entradas_materiais/index.php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'p.nome'); 

// OBSERVAÇÃO: Adicionado 'Código do Produto' às opções de filtro.
$filter_options = [
    'p.nome' => 'Nome do Produto',
    'p.codigo' => 'Código do Produto',
    'mi.numero_nota_fiscal' => 'Número NF',
    'f.nome' => 'Fornecedor'
];

// --- Lógica de Paginação ---
$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE
$where_clause = " WHERE mi.deleted_at IS NULL"; 
$params = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params[] = '%' . $search_term . '%';
}

// Contar o total de Entradas
$sql_count = "SELECT COUNT(mi.id) AS total_items 
              FROM materiais_insumos_entrada mi 
              JOIN produtos p ON mi.produto_id = p.id 
              LEFT JOIN fornecedores_clientes_lookup f ON mi.fornecedor_id = f.id" . $where_clause;
$result_count = $conn->execute_query($sql_count, $params);
$total_items = $result_count->fetch_assoc()['total_items'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

// Buscar as Entradas para a página atual
$sql_fetch = "SELECT mi.id, mi.data_entrada, p.nome AS produto_nome, p.codigo AS produto_codigo, mi.quantidade, mi.numero_nota_fiscal, mi.data_emissao_nota, f.nome AS fornecedor_nome 
              FROM materiais_insumos_entrada mi 
              JOIN produtos p ON mi.produto_id = p.id 
              LEFT JOIN fornecedores_clientes_lookup f ON mi.fornecedor_id = f.id" . $where_clause . " 
              ORDER BY mi.data_entrada DESC 
              LIMIT ? OFFSET ?";
$params_fetch = array_merge($params, [$items_per_page, $offset]);
$entradas = $conn->execute_query($sql_fetch, $params_fetch)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-truck-loading"></i> Controle de Entrada de Materiais</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Registrar Nova Entrada</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="search-container">
        <form action="index.php" method="GET" class="search-form-inline">
            <input type="text" name="search_term" placeholder="Buscar por Produto, Código, NF..." value="<?php echo htmlspecialchars($search_term); ?>">
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

    <?php if (!empty($entradas)): ?>
        <table>
            <thead>
                <tr>
                    <th>Data Entrada</th>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Nota Fiscal</th>
                    <th>Fornecedor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entradas as $entrada): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($entrada['data_entrada'])); ?></td>
                        <td><?php echo htmlspecialchars($entrada['produto_nome'] . ' (' . $entrada['produto_codigo'] . ')'); ?></td>
                        <td class="text-end"><?php echo number_format($entrada['quantidade'], 2, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($entrada['numero_nota_fiscal']); ?></td>
                        <td><?php echo htmlspecialchars($entrada['fornecedor_nome'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="editar.php?id=<?php echo $entrada['id']; ?>" class="button edit small">Editar</a>
                            <a href="excluir.php?id=<?php echo $entrada['id']; ?>" class="button delete small" onclick="return confirm('Tem certeza que deseja excluir esta entrada? Esta ação irá reverter o estoque.');">Excluir</a>
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
        <p style="text-align: center; margin-top: 20px;">Nenhuma entrada de material encontrada.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
