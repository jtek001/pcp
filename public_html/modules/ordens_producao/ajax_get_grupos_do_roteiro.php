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

// Busca os grupos de máquinas associados às etapas do roteiro ativo do produto.
$sql = "SELECT DISTINCT gm.id, gm.nome_grupo
        FROM grupos_maquinas gm
        JOIN roteiro_etapas re ON gm.id = re.grupo_id
        JOIN roteiros r ON re.roteiro_id = r.id
        WHERE r.produto_id = ? AND r.ativo = 1 AND r.deleted_at IS NULL AND re.deleted_at IS NULL
        ORDER BY re.sequencia ASC";

try {
    $grupos = $conn->execute_query($sql, [$produto_id])->fetch_all(MYSQLI_ASSOC);
    echo json_encode($grupos);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMessage()]);
}
?>
