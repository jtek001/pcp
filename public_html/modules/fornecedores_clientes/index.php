<?php
// modules/fornecedores_clientes/index.php
// Esta página lista todos os fornecedores e clientes cadastrados.

// Inicia a sessão para usar variáveis de sessão
session_start();

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

// Recupera mensagens da sessão se existirem (após sucesso de adicionar/editar/excluir)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
} elseif (isset($_GET['message'])) { // Verifica também se a mensagem veio via URL
    $message = sanitizeInput($_GET['message']);
    $message_type = sanitizeInput($_GET['type'] ?? 'info');
}

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'nome'); 

// Mapeamento dos campos de exibição para os nomes das colunas no DB
$filter_options = [
    'nome' => 'Nome / Razão Social',
    'cnpj' => 'CNPJ',
    'tipo' => 'Tipo',
    'contato' => 'Contato'
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

// 1. Contar o total de Fornecedores/Clientes (para calcular o número de páginas)
$sql_count = "SELECT COUNT(id) AS total_items FROM fornecedores_clientes_lookup" . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar fornecedores/clientes: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar fornecedores/clientes: " . $e->getMessage());
    $total_items = 0;
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar os Fornecedores/Clientes para a página atual
$fornecedores_clientes_list = [];
$sql_fetch = "SELECT id, nome, tipo, cnpj, contato, telefone, email FROM fornecedores_clientes_lookup" . $where_clause . " ORDER BY nome ASC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            $fornecedores_clientes_list[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar fornecedores/clientes: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar fornecedores/clientes para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar fornecedores/clientes (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar fornecedores/clientes para exibição: " . $e->getMessage());
}
?>

<h2>Cadastro de Fornecedores / Clientes</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="actions-container">
    <a href="adicionar.php" class="button add">Adicionar Novo Fornecedor / Cliente</a>
</div>

<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="fornecedores_clientes">
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
            <a href="index.php?module=fornecedores_clientes" class="button button-clear">Limpar Pesquisa</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($fornecedores_clientes_list)): ?>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Tipo</th>
                <th>CNPJ</th>
                <th>Contato</th>
                <th>Telefone</th>
                <th>E-mail</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fornecedores_clientes_list as $fc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fc['nome']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($fc['tipo'])); ?></td>
                    <td><?php echo htmlspecialchars($fc['cnpj'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($fc['contato'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($fc['telefone'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($fc['email'] ?? 'N/A'); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $fc['id']; ?>" class="button edit small">Editar</a>
                        <button class="button delete small" onclick="showDeleteModal('fornecedores_clientes', <?php echo $fc['id']; ?>)">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        // Parâmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'fornecedores_clientes',
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
    <tr><td colspan="7" style="text-align: center;">Nenhum fornecedor/cliente cadastrado ou encontrado com a pesquisa. <a href="adicionar.php">Adicione um novo</a>.</td></tr>
<?php endif; ?>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
