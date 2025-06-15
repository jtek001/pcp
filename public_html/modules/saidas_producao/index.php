<?php
// modules/saidas_producao/index.php
// Esta página lista todas as baixas (saídas) de produção registradas.

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
    'ap.lote_numero' => 'Número do Lote',
    'op_consumo.numero_op' => 'OP de Consumo',
    'mov.origem_destino' => 'Origem/Destino' // Para buscas por texto livre
];

// --- Lógica de Paginação ---
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE para contagem e busca (sem LIMIT ainda)
$where_clause = " WHERE mov.tipo_movimentacao = 'saida' AND mov.deleted_at IS NULL"; // Filtrar apenas por saídas de produção
$params_count_and_fetch = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params_count_and_fetch[] = '%' . $search_term . '%';
}

// 1. Contar o total de Baixas (para calcular o número de páginas)
$sql_count = "SELECT COUNT(mov.id) AS total_items 
              FROM movimentacoes_estoque mov
              JOIN produtos p ON mov.produto_id = p.id 
              LEFT JOIN apontamentos_producao ap ON mov.observacoes LIKE CONCAT('%Lote: ', ap.lote_numero, '%') AND ap.deleted_at IS NULL -- Tenta vincular ao apontamento pelo texto da observação
              LEFT JOIN ordens_producao op_consumo ON mov.origem_destino LIKE CONCAT('%Consumo OP: ', op_consumo.numero_op, '%') AND op_consumo.deleted_at IS NULL
              " . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar baixas de produção: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar baixas de produção: " . $e->getMessage());
    $total_items = 0;
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar as Baixas para a página atual
$baixas_list = [];
$sql_fetch = "SELECT 
                mov.id, 
                mov.data_hora_movimentacao, 
                mov.quantidade, 
                mov.observacoes,
                p.nome AS produto_nome, 
                p.codigo AS produto_codigo, 
                p.unidade_medida,
                ap.lote_numero, -- Traz o lote_numero do apontamento
                op_consumo.numero_op AS op_consumo_numero -- Traz o numero da OP de consumo
              FROM 
                movimentacoes_estoque mov
              JOIN 
                produtos p ON mov.produto_id = p.id 
              LEFT JOIN 
                apontamentos_producao ap ON mov.observacoes LIKE CONCAT('%Lote: ', ap.lote_numero, '%') AND ap.deleted_at IS NULL -- Tenta vincular ao apontamento pelo texto
              LEFT JOIN 
                ordens_producao op_consumo ON mov.origem_destino LIKE CONCAT('%Consumo OP: ', op_consumo.numero_op, '%') AND op_consumo.deleted_at IS NULL
              " . $where_clause . " 
              ORDER BY mov.data_hora_movimentacao DESC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            $baixas_list[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar baixas de produção: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar baixas de produção para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar baixas de produção (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar baixas de produção para exibição: " . $e->getMessage());
}
?>

<h2>Controle de Baixas de Produção</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="actions-container">
    <a href="adicionar.php" class="button add">Registrar Nova Baixa</a>
</div>

<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="saidas_producao">
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
            <a href="index.php?module=saidas_producao" class="button button-clear">Limpar Pesquisa</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($baixas_list)): ?>
    <table>
        <thead>
            <tr>
                <th>Data Baixa</th>
                <th>Lote</th>
                <th>Produto</th>
                <th>Qtd. Baixada</th>
                <th>OP Consumo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($baixas_list as $baixa): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($baixa['data_hora_movimentacao'])); ?></td>
                    <td><?php echo htmlspecialchars($baixa['lote_numero'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($baixa['produto_nome'] . ' (' . $baixa['produto_codigo'] . ')'); ?></td>
                    <td><?php echo number_format($baixa['quantidade'], 2, ',', '.') . ' ' . htmlspecialchars($baixa['unidade_medida']); ?></td>
                    <td><?php echo htmlspecialchars($baixa['op_consumo_numero'] ?? 'N/A'); ?></td>
                    <td>
                        <!-- Futuros botões de editar/excluir baixa virão aqui -->
                        <!-- <a href="editar.php?id=<?php // echo $baixa['id']; ?>" class="button edit small">Editar</a> -->
                        <!-- <button class="button delete small" onclick="showDeleteModal('saidas_producao', <?php // echo $baixa['id']; ?>)">Excluir</button> -->
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        // Parâmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'saidas_producao',
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
    <tr><td colspan="6" style="text-align: center;">Nenhuma baixa de produção registrada ou encontrada com a pesquisa. <a href="adicionar.php">Registrar uma nova baixa</a>.</td></tr>
<?php endif; ?>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
