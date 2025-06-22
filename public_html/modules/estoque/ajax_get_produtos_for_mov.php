<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
    exit;
}

$search_term = sanitizeInput($_GET['q'] ?? '');

if (strlen($search_term) < 2) {
    echo json_encode([]);
    exit;
}

// Busca todos os produtos ativos pelo nome ou código
$sql = "SELECT id, nome, codigo, estoque_atual 
        FROM produtos 
        WHERE 
            (nome LIKE ? OR codigo LIKE ?) 
            AND deleted_at IS NULL 
        LIMIT 10";

try {
    $param_search = "%" . $search_term . "%";
    $result = $conn->execute_query($sql, [$param_search, $param_search]);
    $produtos = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($produtos);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMessage()]);
}
?>
