<?php
// modules/produtos/index.php
// Esta página lista todos os produtos cadastrados e oferece opções de CRUD.

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

// Recupera mensagens de redirecionamento (após adicionar, editar ou "excluir" logicamente)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = sanitizeInput($_GET['message']);
    $message_type = sanitizeInput($_GET['type']);
}

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'nome'); // Campo padrão para filtro

// Mapeamento dos campos de exibição para os nomes das colunas no DB
$filter_options = [
    'nome' => 'Nome',
    'codigo' => 'Código',
    'grupo' => 'Grupo',
    'subgrupo' => 'Subgrupo',
    'modelo' => 'Modelo',
    'acabamento' => 'Acabamento',
    'familia' => 'Família',
    'desenho' => 'Desenho',
    'descricao' => 'Descrição',
    'unidade_medida' => 'Unidade de Medida',
    'codigo2' => 'Código 2',
    'unidade_medida2' => 'Unidade de Medida 2'
];

// --- Lógica de Paginação ---
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE para contagem e busca (sem LIMIT ainda)
$where_clause = " WHERE deleted_at IS NULL";
$params_count_and_fetch = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params_count_and_fetch[] = '%' . $search_term . '%';
}

// 1. Contar o total de produtos (para calcular o número de páginas)
$sql_count = "SELECT COUNT(id) AS total_items FROM produtos" . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar produtos: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar produtos: " . $e->getMessage());
    $total_items = 0; // Garante que não haja divisão por zero
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages; // Redireciona para a última página se a atual for inválida
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar os produtos para a página atual
$produtos = [];
$sql_fetch = "SELECT id, nome, codigo, grupo, subgrupo, estoque_atual FROM produtos" . $where_clause . " ORDER BY nome ASC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            $produtos[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar produtos: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar produtos para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar produtos (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar produtos para exibição: " . $e->getMessage());
}
?>

<h2>Gerenciamento de Produtos</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="actions-container">
    <a href="adicionar.php" class="button add">Adicionar Novo Produto</a>
</div>

<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="produtos"> <!-- Garante que o módulo seja 'produtos' -->
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
            <a href="index.php?module=produtos" class="button button-clear">Limpar Pesquisa</a>
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
                <th>Subgrupo</th>
                <th>Estoque</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?php echo htmlspecialchars($produto['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                    <td><?php echo htmlspecialchars($produto['grupo']); ?></td>
                    <td><?php echo htmlspecialchars($produto['subgrupo']); ?></td>
                    <td><?php echo number_format($produto['estoque_atual'], 2, ',', '.'); ?></td>
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
        // Parâmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'produtos',
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

            // Link para a próxima página
            if ($current_page < $total_pages) {
                echo '<a href="?' . $pagination_base_query . '&page=' . ($current_page + 1) . '" class="page-link">Próxima &raquo;</a>';
            }
        }
        ?>
    </div>

<?php else: ?>
    <!-- Colspan ajustado para 6 colunas (Código, Nome, Grupo, Subgrupo, Estoque Atual, Ações) -->
    <tr><td colspan="6" style="text-align: center;">Nenhum produto cadastrado ou encontrado com a pesquisa. <a href="adicionar.php">Adicione um novo produto</a>.</td></tr>
<?php endif; ?>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
