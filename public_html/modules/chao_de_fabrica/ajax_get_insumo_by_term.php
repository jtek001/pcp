<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
    exit;
}

$term = sanitizeInput($_GET['term'] ?? '');

if (empty($term)) {
    http_response_code(400);
    echo json_encode(['error' => 'O código do insumo é obrigatório.']);
    exit;
}

// ALTERAÇÃO: A busca agora é apenas pela coluna 'codigo'.
$sql = "SELECT id, nome, codigo, estoque_atual 
        FROM produtos 
        WHERE 
            codigo = ?
            AND UPPER(familia) IN ('MATERIA-PRIMA', 'COMPONENTES')
            AND estoque_atual > 0 
            AND deleted_at IS NULL 
        LIMIT 1";

try {
    // A busca agora usa apenas um parâmetro.
    $insumo = $conn->execute_query($sql, [$term])->fetch_assoc();

    if ($insumo) {
        echo json_encode($insumo);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Nenhuma matéria-prima ou componente com saldo encontrada para este código.']);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMessage()]);
}
?>
