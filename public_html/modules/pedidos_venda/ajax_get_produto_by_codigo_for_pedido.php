<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$codigo = sanitizeInput($_GET['codigo'] ?? '');

if (empty($codigo)) {
    http_response_code(400);
    echo json_encode(['error' => 'O código do produto é obrigatório.']);
    exit;
}

$sql = "SELECT id, nome, codigo FROM produtos WHERE codigo = ? AND deleted_at IS NULL LIMIT 1";

try {
    $produto = $conn->execute_query($sql, [$codigo])->fetch_assoc();
    if ($produto) {
        echo json_encode($produto);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Nenhum produto encontrado com este código.']);
    }
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.']);
}
?>
