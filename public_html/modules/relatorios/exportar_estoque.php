<?php
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();

try {
    $params = [];
    $types = '';
    $sql_base = "SELECT 
                    p.codigo, p.nome, p.grupo, p.modelo, p.acabamento, p.familia, 
                    p.unidade_medida, p.estoque_atual, p.estoque_minimo, p.unidade_medida2,
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
    header('Content-Disposition: attachment; filename="relatorio_estoque_' . date('Y-m-d') . '.csv"');

    // Abre o fluxo de saída do PHP
    $output = fopen('php://output', 'w');

    // Escreve o cabeçalho do CSV
    fputcsv($output, ['Codigo', 'Produto', 'Grupo', 'Un. Medida', 'Estoque Atual', 'Volume (M3)'], ';');

    // Escreve os dados
    while ($row = $result->fetch_assoc()) {
        $volume_display = (strtoupper($row['unidade_medida2']) === 'M3') ? number_format($row['volume_m3'], 2, ',', '.') : '';
        
        fputcsv($output, [
            $row['codigo'],
            $row['nome'],
            $row['grupo'],
            $row['unidade_medida'],
            number_format($row['estoque_atual'], 2, ',', '.'),
            $volume_display
        ], ';');
    }

    fclose($output);
    $stmt->close();
    $conn->close();
    exit();

} catch (Exception $e) {
    die("Erro ao gerar o relatório: " . $e->getMessage());
}
?>
