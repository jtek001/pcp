<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$term = sanitizeInput($_GET['term'] ?? '');

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

// OBSERVAÇÃO: A consulta usa LEFT JOIN e WHERE r.id IS NULL para encontrar
// apenas os produtos que ainda não têm um roteiro associado.
$sql = "SELECT p.id, p.nome, p.codigo
        FROM produtos p
        LEFT JOIN roteiros r ON p.id = r.produto_id AND r.deleted_at IS NULL
        WHERE 
            r.id IS NULL
            AND (p.nome LIKE ? OR p.codigo LIKE ?)
            AND p.deleted_at IS NULL
        LIMIT 10";

$searchTerm = "%" . $term . "%";
$result = $conn->execute_query($sql, [$searchTerm, $searchTerm]);
$produtos = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($produtos);
?>
