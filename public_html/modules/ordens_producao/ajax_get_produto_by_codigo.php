<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php'; 

$conn = connectDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
    exit;
}

$codigo = sanitizeInput($_GET['codigo'] ?? '');

if (empty($codigo)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'O código do produto é obrigatório.']);
    exit;
}

// A busca agora é apenas pelo código e filtra por produtos que podem ser produzidos.
$sql = "SELECT id, nome, codigo, unidade_medida, estoque_atual 
        FROM produtos 
        WHERE 
            codigo = ? 
            AND UPPER(familia) IN ('Acabado', 'Semiacabado')
            AND deleted_at IS NULL
        LIMIT 1";

try {
    $produto = $conn->execute_query($sql, [$codigo])->fetch_assoc();

    if ($produto) {
        echo json_encode($produto);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Produto não encontrado com este código ou não é um item de produção.']);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMessage()]);
}
?>
