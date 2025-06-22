<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php'; 

$conn = connectDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
    exit;
}

$produto_id = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);

if (!$produto_id) {
    http_response_code(400); 
    echo json_encode(['error' => 'O ID do produto é obrigatório.']);
    exit;
}

// Busca a máquina da primeira etapa do roteiro ativo para o produto.
$sql = "SELECT re.centro_trabalho_id as maquina_id
        FROM roteiro_etapas re
        JOIN roteiros r ON re.roteiro_id = r.id
        WHERE r.produto_id = ? AND r.ativo = 1 AND r.deleted_at IS NULL AND re.deleted_at IS NULL
        ORDER BY re.sequencia ASC
        LIMIT 1";

try {
    $resultado = $conn->execute_query($sql, [$produto_id])->fetch_assoc();

    if ($resultado) {
        echo json_encode(['maquina_id' => $resultado['maquina_id']]);
    } else {
        // Não é um erro se não encontrar, apenas não retorna nada.
        echo json_encode([]); 
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMess