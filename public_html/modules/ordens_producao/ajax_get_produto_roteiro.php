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

// OBSERVAÇÃO: Esta consulta busca o produto e, numa subconsulta, já busca
// a máquina ideal da primeira etapa do roteiro. É mais eficiente que duas chamadas.
$sql = "SELECT 
            p.id, 
            p.nome, 
            p.codigo, 
            p.estoque_atual,
            (SELECT re.centro_trabalho_id 
             FROM roteiro_etapas re
             JOIN roteiros r ON re.roteiro_id = r.id
             WHERE r.produto_id = p.id AND r.ativo = 1 AND re.deleted_at IS NULL
             ORDER BY re.sequencia ASC
             LIMIT 1) as maquina_id
        FROM produtos p
        WHERE 
            p.codigo = ? 
            AND UPPER(p.familia) IN ('ACABADO', 'SEMIACABADO')
            AND p.deleted_at IS NULL
        LIMIT 1";

try {
    $produto = $conn->execute_query($sql, [$codigo])->fetch_assoc();

    if ($produto) {
        echo json_encode($produto);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Produto não encontrado, não é um item de produção ou não possui roteiro ativo.']);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta ao banco de dados.', 'details' => $e->getMessage()]);
}
?>
