<?php
// modules/bom/get_products_for_bom.php
// Este script é um endpoint AJAX que busca produtos para os campos de seleção da BoM.

// Inicia o buffer de saída no início para capturar qualquer output indesejado.
// Isso é CRUCIAL para evitar que erros PHP ou outros outputs corrompam a saída JSON.
ob_start();

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Manter display_errors para ver o erro no navegador em caso de falha no ob_start() ou erro muito precoce.

// Define o cabeçalho para retornar JSON - Tentar enviar o mais cedo possível
header('Content-Type: application/json');

// A função sanitizeInput() FOI REMOVIDA DESTE ARQUIVO porque já é declarada em config/database.php
// e será acessível após o require_once.

$products = []; // Inicializa para garantir que sempre haja um array para json_encode

try {
    // Inclui os arquivos de configuração
    // Se este require_once falhar (ex: caminho errado), o catch abaixo DEVE capturar.
    require_once __DIR__ . '/../../config/database.php';
    
    // Conecta ao banco de dados.
    // connectDB() agora retorna null em caso de falha, em vez de die().
    $conn = connectDB(); 

    // Se a conexão falhou (connectDB() retornou null), retorna JSON de erro
    if ($conn === null || $conn->connect_error) {
        ob_clean(); // Limpa qualquer output capturado antes de retornar JSON
        echo json_encode(['error' => 'Falha na conexão com o banco de dados. Verifique os logs do servidor e as credenciais.']);
        exit(); // Encerra o script aqui
    }

    $search_term = sanitizeInput($_GET['term'] ?? ''); // Agora sanitizeInput() vem de database.php
    $product_type = sanitizeInput($_GET['type'] ?? ''); // Agora sanitizeInput() vem de database.php

    if (!empty($search_term)) {
        $sql_conditions = " WHERE deleted_at IS NULL AND (nome LIKE ? OR codigo LIKE ?)";
        $params = ['%' . $search_term . '%', '%' . $search_term . '%'];

        if ($product_type === 'pai') {
            // Para produto pai, queremos produtos acabados ou sub-montagens
            $sql_conditions .= " AND acabamento = 'Acabado'"; 
        } 
        // Para 'filho', não há filtro adicional de acabamento.

        $sql = "SELECT id, nome, codigo FROM produtos" . $sql_conditions . " ORDER BY nome ASC LIMIT 20";
        
        try {
            $result = $conn->execute_query($sql, $params);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $products[] = [
                        'id' => $row['id'],
                        'text' => $row['nome'] . ' (' . $row['codigo'] . ')', // Texto a ser exibido na lista
                    ];
                }
                $result->free();
            } else {
                ob_clean(); // Limpa qualquer output capturado
                echo json_encode(['error' => 'Falha na consulta SQL: ' . $conn->error . '. SQL: ' . $sql]);
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            ob_clean(); // Limpa qualquer output capturado
            echo json_encode(['error' => 'Exceção SQL fatal durante a consulta: ' . $e->getMessage() . '. SQL: ' . $sql]);
            exit();
        }
    }

    ob_clean(); // Limpa o buffer de saída antes de enviar o JSON final
    echo json_encode($products); // Retorna os produtos em JSON

    $conn->close();

} catch (Throwable $e) { // Captura qualquer tipo de erro PHP (Exception ou Error)
    ob_clean(); // Limpa o buffer de saída antes de enviar nosso JSON de erro
    echo json_encode(['error' => 'Erro PHP inesperado no endpoint: ' . $e->getMessage() . ' na linha ' . $e->getLine() . ' no arquivo ' . $e->getFile()]);
    exit();
}
