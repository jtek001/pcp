<?php
// modules/ordens_producao/index.php
// Esta página lista todas as ordens de produção cadastradas.

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
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'numero_op'); 

// Mapeamento dos campos de exibição para os nomes das colunas no DB
$filter_options = [
    'numero_op' => 'Número da OP',
    'p.nome' => 'Produto', // Referencia a coluna da tabela de produtos
    'status' => 'Status',
    'op.numero_pedido' => 'Número do Pedido' // Novo campo de filtro
];

// --- Lgica de Paginação ---
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE para contagem e busca (sem LIMIT ainda)
$where_clause = " WHERE op.deleted_at IS NULL"; // Usar alias 'op' para a tabela ordens_producao
$params_count_and_fetch = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params_count_and_fetch[] = '%' . $search_term . '%';
}

// 1. Contar o total de Ordens de Produção (para calcular o número de páginas)
// A contagem deve considerar o JOIN para filtragem, mas sem GROUP BY para contar as OPs totais
$sql_count = "SELECT COUNT(DISTINCT op.id) AS total_items FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id" . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar ordens de produção: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar ordens de produção: " . $e->getMessage());
    $total_items = 0;
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar as Ordens de Produção para a pgina atual
$ordens_producao = [];
// Inclui SUM de apontamentos e GROUP BY, e o número do pedido
$sql_fetch = "SELECT op.id, op.numero_op, op.numero_pedido, p.nome AS produto_nome, op.quantidade_produzir, op.data_emissao, op.status, SUM(COALESCE(ap.quantidade_produzida, 0)) AS quantidade_apontada FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id LEFT JOIN apontamentos_producao ap ON op.id = ap.ordem_producao_id AND ap.deleted_at IS NULL" . $where_clause . " GROUP BY op.id ORDER BY op.id DESC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            $ordens_producao[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar ordens de produção: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar ordens de produção para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar ordens de produção (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar ordens de produção para exibição: " . $e->getMessage());
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-industry"></i> Ordens de Produção</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Nova Ordem</a>
    </div>
<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php 
            // A mensagem pode conter HTML (como <strong>), então não usamos htmlspecialchars aqui.
            echo $_SESSION['message']; 
        ?>
    </div>
    <?php 
        // Limpa a mensagem da sessão para que não apareça novamente
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    ?>
<?php endif; ?>
<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>


<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="ordens_producao">
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
            <a href="index.php?module=ordens_producao" class="button button-clear">Limpar Pesquisa</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($ordens_producao)): ?>
    <table>
        <thead>
            <tr>
                <th>ORDEM</th>
                <th>Produto</th>
                <th>PROGRAMADO</th>
                <th>Emissão</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordens_producao as $op): ?>
                <tr>
                    <td><?php echo htmlspecialchars($op['numero_op']); ?></td>
                    <td><?php echo htmlspecialchars($op['produto_nome']); ?></td>
                    <td><?php echo number_format($op['quantidade_produzir'], 2, ',', '.'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($op['data_emissao'])); ?></td>
                    <td><?php echo htmlspecialchars($op['status']); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $op['id']; ?>" class="button edit small">Editar</a>
                        <?php 
                        // Desativa o botão "Apontar" se a OP estiver concluda ou cancelada
                        $apontar_disabled_class = '';
                        $apontar_onclick = '';
                        if ($op['status'] === 'concluida' || $op['status'] === 'cancelada') {
                            $apontar_disabled_class = 'disabled';
                            $apontar_onclick = 'onclick="return false;"'; // Previne o clique
                        }
                        ?>
                        <a href="apontar.php?id=<?php echo $op['id']; ?>" class="button small <?php echo $apontar_disabled_class; ?>" <?php echo $apontar_onclick; ?>>Apontar</a>
                        <?php
                        // Desativa o botão "Excluir" se a OP estiver concluída, em produção, OU se tiver apontamentos
                        $excluir_disabled_class = '';
                        $excluir_onclick = '';
                        if ($op['status'] === 'concluida' || $op['status'] === 'em_producao' || $op['quantidade_apontada'] > 0) { // Adicionado 'quantidade_apontada > 0'
                            $excluir_disabled_class = 'disabled';
                            $excluir_onclick = 'onclick="return false;"';
                        }
                        ?>
                        <button class="button delete small <?php echo $excluir_disabled_class; ?>" <?php echo $excluir_onclick; ?> onclick="showDeleteModal('ordens_producao', <?php echo $op['id']; ?>)">Excluir</button>
                        <a href="imprimir_ordem.php?id=<?php echo $op['id']; ?>" target="_blank" class="button small">Imprimir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        // Parmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'ordens_producao',
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
    <tr><td colspan="7" style="text-align: center;">Nenhuma Ordem de Produção cadastrada ou encontrada com a pesquisa. <a href="adicionar.php">Crie uma nova OP</a>.</td></tr>
<?php endif; ?>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
