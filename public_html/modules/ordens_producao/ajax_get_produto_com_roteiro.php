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
    http_response_code(400); 
    echo json_encode(['error' => 'O código do produto é obrigatório.']);
    exit;
}

// Busca o produto
$sql_produto = "SELECT id, nome FROM produtos WHERE codigo = ? AND UPPER(familia) IN ('ACABADO', 'SEMIACABADO') AND deleted_at IS NULL LIMIT 1";
$produto = $conn->execute_query($sql_produto, [$codigo])->fetch_assoc();

if (!$produto) {
    http_response_code(404);
    echo json_encode(['error' => 'Produto não encontrado ou não é um item de produção.']);
    exit;
}

// Busca os grupos de máquinas do roteiro do produto
$sql_grupos = "SELECT DISTINCT gm.id, gm.nome_grupo
               FROM grupos_maquinas gm
               JOIN roteiro_etapas re ON gm.id = re.grupo_id
               JOIN roteiros r ON re.roteiro_id = r.id
               WHERE r.produto_id = ? AND r.ativo = 1 AND r.deleted_at IS NULL
               ORDER BY re.sequencia ASC";
$grupos = $conn->execute_query($sql_grupos, [$produto['id']])->fetch_all(MYSQLI_ASSOC);

// Monta a resposta final
$response = [
    'id' => $produto['id'],
    'nome' => $produto['nome'],
    'grupos' => $grupos
];

echo json_encode($response);
?>
