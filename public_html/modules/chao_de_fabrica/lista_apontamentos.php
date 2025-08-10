<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Lógica de Filtragem e Busca de Dados ---
$search_term = sanitizeInput($_GET['search_term'] ?? '');
$filter_field = sanitizeInput($_GET['filter_field'] ?? 'ap.lote_numero');

$filter_options = [
    'ap.lote_numero' => 'Lote',
    'gm.nome_grupo' => 'Linha',
    't.nome_turno' => 'Turno',
    'o.nome' => 'Operador'
];

// Lógica de Paginação
$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Constrói a cláusula WHERE
$where_clause = " WHERE ap.deleted_at IS NULL ";
$params = [];
$types = '';

if (!empty($search_term) && array_key_exists($filter_field, $filter_options)) {
    $where_clause .= " AND " . $filter_field . " LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

// Contar o total de itens
$sql_count = "SELECT COUNT(ap.id) AS total_items 
              FROM apontamentos_producao ap
              JOIN ordens_producao op ON ap.ordem_producao_id = op.id
              LEFT JOIN grupos_maquinas gm ON op.grupo_id = gm.id
              LEFT JOIN turnos t ON ap.turno_id = t.id
              LEFT JOIN operadores o ON ap.operador_id = o.id
              " . $where_clause;
$result_count = $conn->execute_query($sql_count, $params);
$total_items = $result_count->fetch_assoc()['total_items'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

// Buscar os itens para a página atual
$sql_fetch = "SELECT 
                ap.lote_numero, ap.data_apontamento, p.nome as produto_nome, p.codigo as produto_codigo,
                gm.nome_grupo as linha_nome, m.nome as maquina_nome, t.nome_turno, o.nome as operador_nome,
                ap.quantidade_produzida
              FROM apontamentos_producao ap
              JOIN ordens_producao op ON ap.ordem_producao_id = op.id
              JOIN produtos p ON op.produto_id = p.id
              LEFT JOIN maquinas m ON ap.maquina_id = m.id
              LEFT JOIN grupos_maquinas gm ON op.grupo_id = gm.id
              LEFT JOIN turnos t ON ap.turno_id = t.id
              LEFT JOIN operadores o ON ap.operador_id = o.id
              " . $where_clause . " 
              ORDER BY ap.data_apontamento DESC 
              LIMIT ? OFFSET ?";
$params_fetch = array_merge($params, [$items_per_page, $offset]);
$apontamentos = $conn->execute_query($sql_fetch, $params_fetch)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-history"></i> Histórico de Apontamentos de Produção</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="lista_apontamentos.php" method="GET" class="search-form-inline">
                <input type="text" name="search_term" placeholder="Buscar por Lote, Linha, Turno, Operador..." value="<?php echo htmlspecialchars($search_term); ?>">
                <select name="filter_field">
                    <?php foreach ($filter_options as $field_value => $field_label): ?>
                        <option value="<?php echo htmlspecialchars($field_value); ?>" <?php echo ($filter_field === $field_value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($field_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button add">Filtrar</button>
                <a href="lista_apontamentos.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if (!empty($apontamentos)): ?>
        <table>
            <thead>
                <tr>
                    <th>Lote</th>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Máquina</th>
                    <th>Turno</th>
                    <th>Operador</th>
                    <th class="text-end">Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apontamentos as $apontamento): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($apontamento['lote_numero']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($apontamento['data_apontamento'])); ?></td>
                        <td><?php echo htmlspecialchars($apontamento['produto_nome']); ?></td>
                        <td><?php echo htmlspecialchars($apontamento['maquina_nome']); ?></td>
                        <td><?php echo htmlspecialchars($apontamento['nome_turno']); ?></td>
                        <td><?php echo htmlspecialchars($apontamento['operador_nome']); ?></td>
                        <td class="text-end"><?php echo number_format($apontamento['quantidade_produzida'], 2, ',', '.'); ?></td>
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
        <p class="text-center mt-3">Nenhum apontamento encontrado para os filtros selecionados.</p>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Chão de Fábrica</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
