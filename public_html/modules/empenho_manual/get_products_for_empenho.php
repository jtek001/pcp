<?php
// modules/empenho_manual/get_products_for_empenho.php
// Este script é um endpoint AJAX que busca produtos para seleção no módulo de Empenho Manual.

// Inicia o buffer de saída
ob_start();

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o cabeçalho para retornar JSON
header('Content-Type: application/json');

// sanitizeInput() é carregado via require_once de config/database.php
// Não deve ser redeclarado aqui.

$products = []; // Inicializa para garantir que sempre haja um array para json_encode

try {
    require_once __DIR__ . '/../../config/database.php';
    
    $conn = connectDB(); 

    if ($conn === null || $conn->connect_error) {
        ob_clean();
        echo json_encode(['error' => 'Falha na conexão com o banco de dados. Verifique os logs do servidor e as credenciais.']);
        exit();
    }

    $search_term = sanitizeInput($_GET['term'] ?? '');

    if (!empty($search_term)) {
        // Busca produtos por nome ou código (sem filtro por acabamento 'Acabado' aqui,
        // pois empenho manual pode ser para qualquer material/produto)
        $sql = "SELECT id, nome, codigo, estoque_atual, estoque_empenhado FROM produtos WHERE deleted_at IS NULL AND (nome LIKE ? OR codigo LIKE ?) ORDER BY nome ASC LIMIT 20";
        
        $param_term = '%' . $search_term . '%';
        
        try {
            $result = $conn->execute_query($sql, [$param_term, $param_term]);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $products[] = [
                        'id' => $row['id'],
                        'text' => $row['nome'] . ' (' . $row['codigo'] . ')',
                        'stock' => (float)$row['estoque_atual'],
                        'empenhado' => (float)$row['estoque_empenhado']
                    ];
                }
                $result->free();
            } else {
                ob_clean();
                echo json_encode(['error' => 'Falha na consulta SQL de produtos: ' . $conn->error . '. SQL: ' . $sql]);
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            ob_clean();
            echo json_encode(['error' => 'Exceção SQL fatal ao buscar produtos: ' . $e->getMessage() . '. SQL: ' . $sql]);
            exit();
        }
    }

    ob_clean();
    echo json_encode($products);

    $conn->close();

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['error' => 'Erro PHP inesperado no endpoint de produtos: ' . $e->getMessage() . ' na linha ' . $e->getLine() . ' no arquivo ' . $e->getFile()]);
    exit();
}
