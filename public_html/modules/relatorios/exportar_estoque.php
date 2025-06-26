<?php
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();

try {
    $params = [];
    $types = '';
    
    // OBSERVAÇÃO: A consulta foi alterada para p.* para buscar todas as colunas da tabela de produtos.
    // A função calcularVolume foi mantida para que o campo 'volume_m3' também seja exportado.
    $sql_base = "SELECT 
                    p.*, 
                    calcularVolume(p.estoque_atual, p.espessura, p.largura, p.comprimento) AS volume_m3
                 FROM produtos p
                 WHERE p.deleted_at IS NULL";

    if (!empty($_GET['grupo'])) {
        $sql_base .= " AND p.grupo = ?";
        $params[] = $_GET['grupo'];
        $types .= 's';
    }
    if (!empty($_GET['modelo'])) {
        $sql_base .= " AND p.modelo = ?";
        $params[] = $_GET['modelo'];
        $types .= 's';
    }
    if (!empty($_GET['acabamento'])) {
        $sql_base .= " AND p.acabamento = ?";
        $params[] = $_GET['acabamento'];
        $types .= 's';
    }
    if (!empty($_GET['familia'])) {
        $sql_base .= " AND p.familia = ?";
        $params[] = $_GET['familia'];
        $types .= 's';
    }
    if (!empty($_GET['filtro_saldo']) && $_GET['filtro_saldo'] === 'com_saldo') {
        $sql_base .= " AND p.estoque_atual >= 1";
    }

    $sql_base .= " ORDER BY p.grupo ASC, p.nome ASC";
    
    $stmt = $conn->prepare($sql_base);
    if ($stmt === false) throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    
    $stmt->execute();
    $result = $stmt->get_result();

    // Define os cabeçalhos para forçar o download do arquivo
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_completo_estoque_' . date('Y-m-d') . '.csv"');

    // OBSERVAÇÃO: Adiciona o BOM para que o Excel entenda o UTF-8 corretamente e exiba os acentos.
    echo "\xEF\xBB\xBF";

    // Abre o fluxo de saída do PHP
    $output = fopen('php://output', 'w');

    // Escreve o cabeçalho do CSV
    $header_sent = false;
    while ($row = $result->fetch_assoc()) {
        if (!$header_sent) {
            // Escreve os nomes das colunas como cabeçalho
            fputcsv($output, array_keys($row), ';');
            $header_sent = true;
        }
        // Escreve os dados da linha
        fputcsv($output, $row, ';');
    }

    fclose($output);
    $stmt->close();
    $conn->close();
    exit();

} catch (Exception $e) {
    die("Erro ao gerar o relatório: " . $e->getMessage());
}
?>
