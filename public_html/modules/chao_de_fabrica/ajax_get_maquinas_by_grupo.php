<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão.']);
    exit;
}

$grupo_id = filter_input(INPUT_GET, 'grupo_id', FILTER_VALIDATE_INT);

if (!$grupo_id) {
    echo json_encode([]);
    exit;
}

// Busca as máquinas que pertencem ao grupo fornecido e estão operacionais.
$sql = "SELECT m.id, m.nome 
        FROM maquinas m
        JOIN maquina_grupo_associacao mga ON m.id = mga.maquina_id
        WHERE mga.grupo_id = ? AND m.deleted_at IS NULL AND m.status = 'operacional'
        ORDER BY m.nome";

$maquinas = $conn->execute_query($sql, [$grupo_id])->fetch_all(MYSQLI_ASSOC);

echo json_encode($maquinas);
?>
