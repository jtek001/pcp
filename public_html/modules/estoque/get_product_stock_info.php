<?php
// modules/estoque/get_product_stock_info.php
// Este script é um endpoint AJAX que busca informações detalhadas de estoque (atual, empenhado) de um produto.

// Inicia o buffer de saída no início para capturar qualquer output indesejado.
ob_start();

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o cabeçalho para retornar JSON
header('Content-Type: application/json');

// sanitizeInput() é carregado via require_once de config/database.php
// Não deve ser redeclarado aqui.

$product_info = [
    'estoque_atual' => 0.00,
    'estoque_empenhado' => 0.00,
    'estoque_livre' => 0.00,
    'error' => null
];

try {
    require_once __DIR__ . '/../../config/database.php';
    $conn = connectDB(); 

    if ($conn === null || $conn->connect_error) {
        ob_clean();
        echo json_encode(['error' => 'Falha na conexão com o banco de dados. Verifique os logs do servidor e as credenciais.']);
        exit();
    }

    $product_id = sanitizeInput($_GET['id'] ?? '');

    if (!empty($product_id)) {
        $sql = "SELECT estoque_atual, estoque_empenhado FROM produtos WHERE id = ? AND deleted_at IS NULL";
        
        try {
            $result = $conn->execute_query($sql, [$product_id]);
            if ($result && $row = $result->fetch_assoc()) {
                $product_info['estoque_atual'] = (float)$row['estoque_atual'];
                $product_info['estoque_empenhado'] = (float)$row['estoque_empenhado'];
                $product_info['estoque_livre'] = $product_info['estoque_atual'] - $product_info['estoque_empenhado'];
                $result->free();
            } else {
                $product_info['error'] = 'Produto não encontrado ou inativo.';
            }
        } catch (mysqli_sql_exception $e) {
            $product_info['error'] = 'Exceção SQL ao buscar estoque: ' . $e->getMessage();
        }
    } else {
        $product_info['error'] = 'ID do produto não fornecido.';
    }

    ob_clean();
    echo json_encode($product_info);

    $conn->close();

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['error' => 'Erro PHP inesperado no endpoint de estoque: ' . $e->getMessage() . ' na linha ' . $e->getLine() . ' no arquivo ' . $e->getFile()]);
    exit();
}
