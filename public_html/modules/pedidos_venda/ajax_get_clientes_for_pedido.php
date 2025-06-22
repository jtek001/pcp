<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$term = sanitizeInput($_GET['term'] ?? '');

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, nome, cnpj 
        FROM fornecedores_clientes_lookup
        WHERE (nome LIKE ? OR cnpj LIKE ?) 
        AND (tipo = 'cliente' OR tipo = 'ambos') 
        AND deleted_at IS NULL 
        LIMIT 10";

$searchTerm = "%" . $term . "%";
$result = $conn->execute_query($sql, [$searchTerm, $searchTerm]);
$clientes = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($clientes);
?>
