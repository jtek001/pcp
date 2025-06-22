<?php
// modules/chao_de_fabrica/index.php
// Esta página lista as Ordens de Produção prontas para apontamento no Chão de Fbrica.

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

// Recupera mensagens da sessão se existirem (após sucesso de apontamento)
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
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'op.numero_op'); 

// Mapeamento dos campos de exibião para os nomes das colunas no DB
$filter_options = [
    'op.numero_op' => 'Número da OP',
    'p.nome' => 'Produto',
    'p.codigo' => 'Código Produto',
    'm.nome' => 'Máquina'
];

// --- Lógica de Paginaão ---
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE para contagem e busca (sem LIMIT ainda)
$where_clause = " WHERE op.deleted_at IS NULL AND op.status IN ('pendente', 'em_producao', 'concluida')"; 
$params_count_and_fetch = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params_count_and_fetch[] = '%' . $search_term . '%';
}

// 1. Contar o total de OPs (para calcular o número de páginas)
$sql_count = "SELECT COUNT(DISTINCT op.id) AS total_items 
              FROM ordens_producao op 
              JOIN produtos p ON op.produto_id = p.id 
              LEFT JOIN maquinas m ON op.maquina_id = m.id" . $where_clause;
try {
    $result_count = $conn->execute_query($sql_count, $params_count_and_fetch);
    $total_items = 0;
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_items = $row_count['total_items'];
        $result_count->free();
    } else {
        error_log("Erro ao contar OPs para Chão de Fábrica: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao contar OPs para Chão de Fábrica: " . $e->getMessage());
    $total_items = 0;
}

$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// 2. Buscar as OPs para a página atual
$ops_list = [];
$sql_fetch = "SELECT 
                op.id, op.numero_op, op.quantidade_produzir, op.status, 
                p.nome AS produto_nome, p.codigo AS produto_codigo, 
                m.nome AS maquina_nome,
                SUM(COALESCE(ap.quantidade_produzida, 0)) AS quantidade_apontada 
              FROM 
                ordens_producao op
              JOIN 
                produtos p ON op.produto_id = p.id
              LEFT JOIN 
                maquinas m ON op.maquina_id = m.id
              LEFT JOIN 
                apontamentos_producao ap ON op.id = ap.ordem_producao_id AND ap.deleted_at IS NULL
              " . $where_clause . " 
              GROUP BY op.id 
              ORDER BY op.id DESC LIMIT ? OFFSET ?";
$params_fetch = array_merge($params_count_and_fetch, [$items_per_page, $offset]);

try {
    $stmt_fetch = $conn->execute_query($sql_fetch, $params_fetch);
    if ($stmt_fetch) {
        while ($row = $stmt_fetch->fetch_assoc()) {
            $row['necessidade_real'] = $row['quantidade_produzir'] - $row['quantidade_apontada'];
            $ops_list[] = $row;
        }
        $stmt_fetch->free();
    } else {
        $message = "Erro ao carregar Ordens de Produção: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar OPs para exibição: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro ao carregar Ordens de Produção (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar OPs para exibião: " . $e->getMessage());
}
?>

<h2><i class="fas fa-cogs"></i> Chão de Fábrica - Apontamento de Produção</h2>

<?php if ($message): ?>
    <div class="message <?php echo htmlspecialchars($message_type); ?>">
        <?php echo $message; // A mensagem de sessão pode conter HTML (ex: <strong>) ?>
    </div>
<?php endif; ?>

<div class="search-container">
    <form action="index.php" method="GET" class="search-form-inline">
        <input type="hidden" name="module" value="chao_de_fabrica">
        <input type="text" name="search_term" placeholder="Buscar OP, Produto ou Máquina..." value="<?php echo htmlspecialchars($search_term); ?>">
        <select name="filter_field">
            <?php foreach ($filter_options as $field_value => $field_label): ?>
                <option value="<?php echo htmlspecialchars($field_value); ?>" <?php echo ($filter_field === $field_value) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($field_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button">Pesquisar</button>
        <?php if (!empty($search_term)): ?>
            <a href="index.php?module=chao_de_fabrica" class="button button-clear">Limpar Pesquisa</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($ops_list)): ?>
    <table>
        <thead>
            <tr>
                <th>OP</th>
                <th>Produto</th>
                <th>Programado</th>
                <th>Apontado</th>
                <th>Necessidade</th>
                <th>Máquina</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ops_list as $op): ?>
                <tr>
                    <td><?php echo htmlspecialchars($op['numero_op']); ?></td>
                    <td><?php echo htmlspecialchars($op['produto_nome'] . ' (' . $op['produto_codigo'] . ')'); ?></td>
                    <td><?php echo number_format($op['quantidade_produzir'], 2, ',', '.'); ?></td>
                    <td><?php echo number_format($op['quantidade_apontada'], 2, ',', '.'); ?></td>
                    <td><?php echo number_format($op['necessidade_real'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($op['maquina_nome'] ?? 'N/A'); ?></td>
                    <td><span class="status-<?php echo htmlspecialchars(strtolower($op['status'])); ?>"><?php echo htmlspecialchars(ucfirst($op['status'])); ?></span></td>
                    <td style="display: flex; gap: 5px; align-items: center;">
                        <?php 
                        // Desativa o botão "Apontar" se a OP estiver concluída, cancelada ou já atingiu a quantidade
                        $apontar_disabled_class = '';
                        $apontar_onclick = '';
                        if ($op['status'] === 'concluida' || $op['status'] === 'cancelada' || $op['necessidade_real'] <= 0) {
                            $apontar_disabled_class = 'disabled';
                            $apontar_onclick = 'onclick="return false;"'; 
                        }
                        ?>
                        <a href="apontar.php?id=<?php echo $op['id']; ?>" class="button small add <?php echo $apontar_disabled_class; ?>" <?php echo $apontar_onclick; ?>>Apontar</a>
                        <a href="consumo.php?op_id=<?php echo $op['id']; ?>" class="button small">Consumir</a>
                        <a href="insumos.php?id=<?php echo $op['id']; ?>" class="button small edit">Materiais</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        // Parâmetros base para os links de paginação (mantém pesquisa e filtro)
        $pagination_base_query = http_build_query(array_filter([
            'module' => 'chao_de_fabrica',
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
                echo '<a href="?' . $pagination_base_query . '&page=' . ($current_page + 1) . '" class="page-link">Prxima &raquo;</a>';
            }
        }
        ?>
    </div>

<?php else: ?>
    <p style="text-align: center; margin-top: 20px;">Nenhuma Ordem de Produção ativa ou encontrada com a pesquisa para apontamento.</p>
<?php endif; ?>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
