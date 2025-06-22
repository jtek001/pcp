<?php
// modules/estoque/index.php
// Esta página lista o estoque atual de todos os produtos, incluindo o empenho.

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
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'nome'); 

// Mapeamento dos campos de exibição para os nomes das colunas no DB
$filter_options = [
    'nome' => 'Nome do Produto',
    'codigo' => 'Código do Produto',
    'grupo' => 'Grupo',
    'subgrupo' => 'Subgrupo'
];

// --- Lógica de Paginação ---
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE para contagem e busca (sem LIMIT ainda)
$where_clause = " WHERE p.deleted_at IS NULL"; // Alias 'p' para a tabela produtos
$params_count_and_fetch = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params_count_and_fetch[] = '%' . $search_term . '%';
}

// 1. Contar o total de Produtos (para calcular o número de páginas)
$sql_count = "SELECT COUNT(p.id) AS total_items FROM produtos p" . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar produtos para estoque: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar produtos para estoque: " . $e->getMessage());
    $total_items = 0;
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar os produtos para a página atual, incluindo dados de estoque e empenho
$produtos_estoque = [];
$sql_fetch = "SELECT 
                p.id, 
                p.nome, 
                p.codigo, 
                p.unidade_medida,
                p.estoque_minimo, 
                p.estoque_atual, 
                p.estoque_empenhado 
            FROM 
                produtos p" . $where_clause . " ORDER BY p.nome ASC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            // Calcula o estoque livre
            $row['estoque_livre'] = $row['estoque_atual'] - $row['estoque_empenhado'];
            $produtos_estoque[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar dados do estoque: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar dados do estoque para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar dados do estoque (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar dados do estoque para exibição: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-boxes-stacked"></i> Visão Geral do Estoque</h2>
    <a href="movimentar.php" class="button add"><i class="fas fa-arrows-alt-h"></i> Movimentação Manual</a>
</div>


<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="estoque">
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
            <a href="index.php?module=estoque" class="button button-clear">Limpar Pesquisa</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($produtos_estoque)): ?>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nome</th>
                <th>Unidade</th>
                <th>Estoque Mín.</th>
                <th>Estoque Atual</th>
                <th>Estoque Empenhado</th>
                <th>Estoque Livre</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos_estoque as $produto): ?>
                <tr>
                    <td><?php echo htmlspecialchars($produto['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                    <td><?php echo htmlspecialchars($produto['unidade_medida']); ?></td>
                    <td><?php echo number_format($produto['estoque_minimo'], 2, ',', '.'); ?></td>
                    <td><?php echo number_format($produto['estoque_atual'], 2, ',', '.'); ?></td>
                    <td><?php echo number_format($produto['estoque_empenhado'], 2, ',', '.'); ?></td>
                    <td><?php echo number_format($produto['estoque_livre'], 2, ',', '.'); ?></td>
                    <td>
                        <?php
                        // Condição para exibir o status visual
                        if ($produto['estoque_livre'] <= $produto['estoque_minimo']) {
                            echo '<i class="fas fa-exclamation-triangle text-warning" title="Estoque livre abaixo ou igual ao mínimo!"></i>';
                        } else {
                            echo '<i class="fas fa-check-circle text-success" title="Estoque OK"></i>';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        // Parâmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'estoque',
            'search_term' => $search_term,
            'filter_field' => $filter_field
        ]));

        if ($total_pages > 1) {
            // Link para a pgina anterior
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
    <p style="text-align: center; margin-top: 20px;">Nenhum produto cadastrado ou encontrado com a pesquisa no estoque.</p>
<?php endif; ?>

<?php
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
