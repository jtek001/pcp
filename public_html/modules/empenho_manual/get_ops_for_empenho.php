<?php
// modules/empenho_manual/get_ops_for_empenho.php
// Este script é um endpoint AJAX que busca Ordens de Produção (OPs) para seleção em empenho manual.

ob_start(); // Inicia o buffer de saída

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// sanitizeInput() é carregado via require_once de config/database.php
// Não deve ser redeclarado aqui.

$ops = []; // Inicializa para garantir que sempre haja um array para json_encode

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
        // Busca OPs por número, que não estejam concluídas ou canceladas (ainda podem precisar de empenho/desempenho)
        $sql = "SELECT id, numero_op FROM ordens_producao WHERE deleted_at IS NULL AND status IN ('pendente', 'em_producao', 'cancelada') AND numero_op LIKE ? ORDER BY numero_op ASC LIMIT 20";
        $param_term = '%' . $search_term . '%';
        
        try {
            $result = $conn->execute_query($sql, [$param_term]);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $ops[] = [
                        'id' => $row['id'],
                        'text' => $row['numero_op'], // Exibe apenas o número da OP
                    ];
                }
                $result->free();
            } else {
                ob_clean();
                echo json_encode(['error' => 'Falha na consulta SQL de OPs: ' . $conn->error . '. SQL: ' . $sql]);
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            ob_clean();
            echo json_encode(['error' => 'Exceção SQL fatal ao buscar OPs: ' . $e->getMessage() . '. SQL: ' . $sql]);
            exit();
        }
    }

    ob_clean();
    echo json_encode($ops);

    $conn->close();

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['error' => 'Erro PHP inesperado no endpoint de OPs: ' . $e->getMessage() . ' na linha ' . $e->getLine() . ' no arquivo ' . $e->getFile()]);
    exit();
}
