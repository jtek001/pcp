<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$lote_numero = sanitizeInput($_GET['lote'] ?? '');

if (empty($lote_numero)) {
    http_response_code(400);
    echo json_encode(['error' => 'O número do lote é obrigatório.']);
    exit;
}

// Busca um lote que não esteja deletado e que ainda tenha saldo.
$sql = "SELECT 
            ap.id, 
            p.nome as produto_nome,
            p.codigo as produto_codigo,
            ap.quantidade_produzida
        FROM apontamentos_producao ap
        JOIN ordens_producao op ON ap.ordem_producao_id = op.id
        JOIN produtos p ON op.produto_id = p.id
        WHERE 
            ap.lote_numero = ?
            AND ap.deleted_at IS NULL
            AND ap.quantidade_produzida > 0
        LIMIT 1";

try {
    $lote = $conn->execute_query($sql, [$lote_numero])->fetch_assoc();

    if ($lote) {
        echo json_encode($lote);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Lote não encontrado, sem saldo ou já consumido.']);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.']);
}
?>
