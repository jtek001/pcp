<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
    exit;
}

$lote_numero = sanitizeInput($_GET['lote'] ?? '');

if (empty($lote_numero)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'O número do lote é obrigatório.']);
    exit;
}

// A consulta agora é mais simples.
$sql = "SELECT 
            ap.id, 
            ap.lote_numero, 
            ap.quantidade_produzida,
            p.nome AS produto_nome
        FROM apontamentos_producao ap
        JOIN ordens_producao op ON ap.ordem_producao_id = op.id
        JOIN produtos p ON op.produto_id = p.id
        WHERE 
            ap.lote_numero = ?
            AND ap.quantidade_produzida > 0
            AND ap.deleted_at IS NULL
        LIMIT 1";

try {
    $lote = $conn->execute_query($sql, [$lote_numero])->fetch_assoc();

    if ($lote) {
        echo json_encode($lote);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Lote não encontrado, sem saldo ou já consumido.']);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMessage()]);
}
?>
