<?php
// modules/materiais/get_products_for_materials.php
// Este script é um endpoint AJAX que busca produtos para o campo de seleção de Entrada de Materiais.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração
require_once __DIR__ . '/../../config/database.php';

// Define o cabeçalho para retornar JSON
header('Content-Type: application/json');

$conn = connectDB();

$search_term = sanitizeInput($_GET['term'] ?? '');
$products = [];

if (!empty($search_term)) {
    // Busca produtos por nome ou código (sem filtro por acabamento 'Acabado')
    $sql = "SELECT id, nome, codigo, estoque_atual FROM produtos WHERE deleted_at IS NULL AND (nome LIKE ? OR codigo LIKE ?) ORDER BY nome ASC LIMIT 20";
    
    $param_term = '%' . $search_term . '%';
    
    try {
        $result = $conn->execute_query($sql, [$param_term, $param_term]);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $products[] = [
                    'id' => $row['id'],
                    'text' => $row['nome'] . ' (' . $row['codigo'] . ')', // Texto a ser exibido na lista
                    'stock' => $row['estoque_atual'] // Estoque para preencher o campo
                ];
            }
            $result->free();
        } else {
            error_log("Erro ao buscar produtos para AJAX (materiais): " . $conn->error);
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Erro fatal ao buscar produtos para AJAX (materiais): " . $e->getMessage());
    }
}

echo json_encode($products);

$conn->close();
?>
