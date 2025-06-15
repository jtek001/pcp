<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$search_term = sanitizeInput($_GET['q'] ?? '');

if (strlen($search_term) < 1) {
    echo json_encode([]);
    exit;
}

// ALTERAÇÃO: A consulta agora é global e verifica a existência de empenho para o produto do lote.
// Não filtra mais por 'deleted_at' nos apontamentos.
$sql = "SELECT 
            ap.id, 
            ap.lote_numero, 
            ap.quantidade_produzida,
            p.nome AS produto_nome
        FROM apontamentos_producao ap
        JOIN ordens_producao op ON ap.ordem_producao_id = op.id
        JOIN produtos p ON op.produto_id = p.id
        WHERE 
            ap.lote_numero LIKE ?
            AND ap.quantidade_produzida > 0
            AND EXISTS (
                SELECT 1 
                FROM empenho_materiais em 
                WHERE em.produto_id = op.produto_id 
                AND em.deleted_at IS NULL
            )
        LIMIT 10";

$param_search = "%" . $search_term . "%";
$result = $conn->execute_query($sql, [$param_search]);
$lotes = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($lotes);
?>
