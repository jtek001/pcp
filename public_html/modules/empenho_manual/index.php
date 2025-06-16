<?php
// modules/empenho_manual/index.php
// Esta página lista todos os empenhos manuais cadastrados.

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
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'p.nome'); 

// Mapeamento dos campos de exibição para os nomes das colunas no DB
$filter_options = [
    'p.nome' => 'Produto',
    'p.codigo' => 'Código Produto',
    'op.numero_op' => 'Número OP'
];

// --- Lógica de Paginação ---
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE para contagem e busca (sem LIMIT ainda)
$where_clause = " WHERE em.deleted_at IS NULL"; // Alias 'em' para empenho_materiais
$params_count_and_fetch = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params_count_and_fetch[] = '%' . $search_term . '%';
}

// 1. Contar o total de Empenhos (para calcular o número de páginas)
$sql_count = "SELECT COUNT(em.id) AS total_items FROM empenho_materiais em 
              JOIN produtos p ON em.produto_id = p.id 
              JOIN ordens_producao op ON em.ordem_producao_id = op.id" . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar empenhos: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar empenhos: " . $e->getMessage());
    $total_items = 0;
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar os empenhos para a página atual
$empenhos_list = [];
$sql_fetch = "SELECT em.id, em.quantidade_empenhada, em.data_empenho, em.observacoes,
                     p.nome AS produto_nome, p.codigo AS produto_codigo, p.unidade_medida,
                     op.numero_op AS ordem_producao_numero
              FROM empenho_materiais em 
              JOIN produtos p ON em.produto_id = p.id 
              JOIN ordens_producao op ON em.ordem_producao_id = op.id" . $where_clause . " 
              ORDER BY em.data_empenho DESC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            $empenhos_list[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar empenhos: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar empenhos para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar empenhos (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar empenhos para exibição: " . $e->getMessage());
}
?>

<h2>Controle Manual de Empenho de Materiais</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="actions-container">
    <a href="adicionar.php" class="button add">Registrar Novo Empenho Manual</a>
</div>

<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="empenho_manual">
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
            <a href="index.php?module=empenho_manual" class="button button-clear">Limpar Pesquisa</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($empenhos_list)): ?>
    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th>OP</th>
                <th>Qtd. Empenhada</th>
                <th>Data Empenho</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($empenhos_list as $empenho): ?>
                <tr>
                    <td><?php echo htmlspecialchars($empenho['produto_nome'] . ' (' . $empenho['produto_codigo'] . ')'); ?></td>
                    <td><?php echo htmlspecialchars($empenho['ordem_producao_numero']); ?></td>
                    <td><?php echo number_format($empenho['quantidade_empenhada'], 2, ',', '.') . ' ' . htmlspecialchars($empenho['unidade_medida']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($empenho['data_empenho'])); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $empenho['id']; ?>" class="button edit small">Editar</a>
                        <button class="button delete small" onclick="showDeleteModal('empenho_manual', <?php echo $empenho['id']; ?>)">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        // Parâmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'empenho_manual',
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
    <tr><td colspan="6" style="text-align: center;">Nenhum empenho manual cadastrado ou encontrado com a pesquisa. <a href="adicionar.php">Registrar um novo empenho manual</a>.</td></tr>
<?php endif; ?>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
