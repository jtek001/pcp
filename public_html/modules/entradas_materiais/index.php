<?php
// modules/materiais/index.php
// Esta página lista todas as entradas de materiais/insumos cadastradas.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração e o cabeçalho
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variáveis para mensagens de sucesso/erro (podem vir de redirecionamentos)
$message = '';
$message_type = '';

// Recupera mensagens de redirecionamento
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = sanitizeInput($_GET['message']);
    $message_type = sanitizeInput($_GET['type']);
}

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'p.nome'); 

// Mapeamento dos campos de exibição para os nomes das colunas no DB
$filter_options = [
    'p.nome' => 'Produto',
    'mi.numero_nota_fiscal' => 'Número NF',
    'f.nome' => 'Fornecedor',
    'mi.local_armazenamento' => 'Local Armazenamento'
];

// --- Lógica de Paginaão ---
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE para contagem e busca (sem LIMIT ainda)
$where_clause = " WHERE mi.deleted_at IS NULL"; 
$params_count_and_fetch = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params_count_and_fetch[] = '%' . $search_term . '%';
}

// 1. Contar o total de Entradas (para calcular o número de páginas)
$sql_count = "SELECT COUNT(mi.id) AS total_items FROM materiais_insumos_entrada mi JOIN produtos p ON mi.produto_id = p.id LEFT JOIN fornecedores_clientes_lookup f ON mi.fornecedor_id = f.id" . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar entradas de materiais: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar entradas de materiais: " . $e->getMessage());
    $total_items = 0;
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar as Entradas para a página atual
$entradas = [];
$sql_fetch = "SELECT mi.id, mi.data_entrada, p.nome AS produto_nome, p.codigo AS produto_codigo, mi.quantidade, mi.numero_nota_fiscal, mi.data_emissao_nota, f.nome AS fornecedor_nome FROM materiais_insumos_entrada mi JOIN produtos p ON mi.produto_id = p.id LEFT JOIN fornecedores_clientes_lookup f ON mi.fornecedor_id = f.id" . $where_clause . " ORDER BY mi.data_entrada DESC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            $entradas[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar entradas de materiais: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar entradas de materiais para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar entradas de materiais (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar entradas de materiais para exibição: " . $e->getMessage());
}
?>

<h2>Controle de Entrada de Materiais e Insumos</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="actions-container">
    <a href="adicionar.php?module=materiais" class="button add">Registrar Nova Entrada</a>
</div>

<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="materiais"> <!-- Novo nome do módulo -->
        <input type="text" name="search_term" placeholder="Termo de pesquisa..." value="<?php echo htmlspecialchars($search_term); ?>">
        <select name="filter_field">
            <?php foreach ($filter_options as $field_value => $field_label): ?>
                <option value="<?php echo htmlspecialchars($field_value); ?>" <?php echo ($filter_field === $field_value) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($field_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button">Pesquisar</button>
        <?php if (!empty($search_term)): ?>
            <a href="index.php?module=materiais" class="button button-clear">Limpar Pesquisa</a> <!-- Novo nome do módulo -->
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
                <th>Açes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entradas as $entrada): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($entrada['data_entrada'])); ?></td>
                    <td><?php echo htmlspecialchars($entrada['produto_nome'] . ' (' . $entrada['produto_codigo'] . ')'); ?></td>
                    <td><?php echo number_format($entrada['quantidade'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($entrada['numero_nota_fiscal']); ?></td>
                    <td><?php echo htmlspecialchars($entrada['fornecedor_nome'] ?? 'N/A'); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $entrada['id']; ?>" class="button edit small">Editar</a>
                        <button class="button delete small" onclick="showDeleteModal('materiais', <?php echo $entrada['id']; ?>)">Excluir</button> <!-- Novo nome do módulo -->
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        // Parâmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'materiais', // Novo nome do módulo
            'search_term' => $search_term,
            'filter_field' => $filter_field
        ]));

        if ($total_pages > 1) {
            // Link para a página anterior
            if ($current_page > 1) {
                echo '<a href="?' . $pagination_base_query . '&page=' . ($current_page - 1) . '" class="page-link">&laquo; Anterior</a>';
            }

            // Links para as páginas
            for ($i = 1; $i <= $total_pages; $i++) {
                $active_class = ($i == $current_page) ? 'active' : '';
                echo '<a href="?' . $pagination_base_query . '&page=' . $i . '" class="page-link ' . $active_class . '">' . $i . '</a>';
            }

            // Link para a prxima página
            if ($current_page < $total_pages) {
                echo '<a href="?' . $pagination_base_query . '&page=' . ($current_page + 1) . '" class="page-link">Próxima &raquo;</a>';
            }
        }
        ?>
    </div>

<?php else: ?>
    <tr><td colspan="7" style="text-align: center;">Nenhuma entrada de material/insumo cadastrada ou encontrada com a pesquisa. <a href="adicionar.php">Registrar uma nova entrada</a>.</td></tr>
<?php endif; ?>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
