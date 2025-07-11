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
    http_response_code(400);
    echo json_encode(['error' => 'O número do lote é obrigatório.']);
    exit;
}

// OBSERVAÇÃO: Esta consulta calcula o saldo de um lote específico na expedição.
// Ela soma todas as entradas e subtrai todas as saídas para obter o saldo real.
$sql = "SELECT 
            el.lote_numero,
            p.id as produto_id,
            p.nome as produto_nome,
            p.codigo as produto_codigo,
            SUM(CASE WHEN el.tipo_movimentacao = 'entrada' THEN el.quantidade ELSE -el.quantidade END) as saldo_disponivel
        FROM expedicao_log el
        JOIN produtos p ON el.produto_id = p.id
        WHERE el.lote_numero = ?
        GROUP BY el.lote_numero, p.id, p.nome, p.codigo
        HAVING saldo_disponivel > 0";

try {
    $lote = $conn->execute_query($sql, [$lote_numero])->fetch_assoc();

    if ($lote) {
        echo json_encode($lote);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Lote não encontrado na expedição ou sem saldo disponível.']);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMessage()]);
}
?>
