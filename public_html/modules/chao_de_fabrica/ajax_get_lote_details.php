<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$lote_numero = sanitizeInput($_GET['lote'] ?? '');
$op_id = filter_input(INPUT_GET, 'op_id', FILTER_VALIDATE_INT);

if (empty($lote_numero) || !$op_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Número do lote e ID da OP são obrigatórios.']);
    exit;
}

$sql = "SELECT 
            ap.id, 
            ap.lote_numero, 
            ap.quantidade_produzida,
            p.nome AS produto_nome
        FROM apontamentos_producao ap
        JOIN ordens_producao op ON ap.ordem_producao_id = op.id
        JOIN produtos p ON op.produto_id = p.id
        WHERE 
            op.id = ?
            AND ap.lote_numero = ?
            AND ap.quantidade_produzida > 0
            AND ap.deleted_at IS NULL
        LIMIT 1";

$lote = $conn->execute_query($sql, [$op_id, $lote_numero])->fetch_assoc();

if ($lote) {
    echo json_encode($lote);
} else {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Lote não encontrado, com saldo, ou não pertence a esta OP.']);
}
?>
