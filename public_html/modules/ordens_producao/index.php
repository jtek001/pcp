<?php
// modules/ordens_producao/index.php
// Esta página lista todas as ordens de produção cadastradas.

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

// Recupera mensagens de redirecionamento
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Lógica para pesquisa e filtro
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'op.numero_op'); 

$filter_options = [
    'op.numero_op' => 'Número da OP',
    'p.nome' => 'Produto',
    'op.status' => 'Status'
];

// Lógica de Paginação
$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE
$where_clause = " WHERE op.deleted_at IS NULL ";
$params = [];

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params[] = '%' . $search_term . '%';
}

// Contar o total de itens
$sql_count = "SELECT COUNT(op.id) AS total_items 
              FROM ordens_producao op
              JOIN produtos p ON op.produto_id = p.id
              " . $where_clause;
$result_count = $conn->execute_query($sql_count, $params);
$total_items = $result_count->fetch_assoc()['total_items'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os itens para a página atual
$sql_fetch = "SELECT 
                op.id, 
                op.numero_op, 
                p.nome as produto_nome, 
                op.quantidade_produzir,
                (SELECT SUM(quantidade_produzida) FROM apontamentos_producao WHERE ordem_producao_id = op.id AND deleted_at IS NULL) as quantidade_apontada,
                op.data_emissao,
                op.status
              FROM ordens_producao op
              JOIN produtos p ON op.produto_id = p.id
              " . $where_clause . " 
              ORDER BY op.id DESC 
              LIMIT ? OFFSET ?";
$params_fetch = array_merge($params, [$items_per_page, $offset]);

$result_fetch = $conn->execute_query($sql_fetch, $params_fetch);
$ops = $result_fetch->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-industry"></i> Ordens de Produção</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Nova OP</a>
    </div>

    <?php if (isset($message) && !empty($message)): ?>
    <div class="message <?php echo htmlspecialchars($message_type); ?>">
        <?php echo $message; ?>
    </div>
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

    <?php if (!empty($ops)): ?>
        <table>
            <thead>
                <tr>
                    <th>Ordem</th>
                    <th>Produto</th>
                    <th class="text-end">Programado</th>
                    <th>Emissão</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ops as $op): 
                    $status_class = strtolower(htmlspecialchars($op['status']));
                ?>
                    <tr class="status-row-<?php echo $status_class; ?>">
                        <td><?php echo htmlspecialchars($op['numero_op']); ?></td>
                        <td><?php echo htmlspecialchars($op['produto_nome']); ?></td>
                        <td class="text-end"><?php echo number_format($op['quantidade_produzir'], 2, ',', '.'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($op['data_emissao'])); ?></td>
                        <td class="text-center">
                            <?php
                            // Lógica para exibir ícones de status
                            if ($op['status'] === 'concluida') {
                                echo '<i class="fas fa-check-circle text-success" title="Concluída" style="font-size: 1.3em;"></i>';
                            } elseif ($op['status'] === 'cancelada') {
                                echo '<i class="fas fa-times-circle text-danger" title="Cancelada" style="font-size: 1.3em;"></i>';
                            } else {
                                echo '<span class="status-' . $status_class . '">' . htmlspecialchars(ucfirst($op['status'])) . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="apontar.php?id=<?php echo $op['id']; ?>" class="button small add">Apontar</a>
                            <a href="editar.php?id=<?php echo $op['id']; ?>" class="button edit small">Editar</a>
                            <?php
                            $excluir_disabled = in_array($op['status'], ['concluida', 'em_producao', 'cancelada']) || $op['quantidade_apontada'] > 0;
                            ?>
                            <button class="button delete small" <?php echo $excluir_disabled ? 'disabled' : ''; ?> onclick="showDeleteModal('ordens_producao', <?php echo $op['id']; ?>)">Excluir</button>
                            <a href="imprimir_ordem.php?id=<?php echo $op['id']; ?>" target="_blank" class="button small">Imprimir</a>
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
        <p style="text-align: center; margin-top: 20px;">Nenhuma Ordem de Produção encontrada.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
