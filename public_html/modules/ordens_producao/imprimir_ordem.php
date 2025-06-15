<?php
// Define o fuso horário para garantir que a data e hora de impressão estejam corretas
date_default_timezone_set('America/Sao_Paulo');

require_once '../../config/database.php';

// Verifica se o ID da OP foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID da Ordem de Produço inválido.");
}

$op_id = intval($_GET['id']);
$conn = connectDB();

if (!$conn) {
    die("Falha na conexão com o banco de dados.");
}

// Busca os detalhes da Ordem de Produço, incluindo as dimensões do produto principal
$sql_op = "SELECT 
                op.id, op.numero_op, op.quantidade_produzir, op.data_emissao, op.data_prevista_conclusao, op.status, op.observacoes,
                p.nome as produto_nome, p.codigo as produto_codigo, p.unidade_medida2, p.espessura, p.largura, p.comprimento,
                m.nome as maquina_nome
           FROM ordens_producao op
           JOIN produtos p ON op.produto_id = p.id
           LEFT JOIN maquinas m ON op.maquina_id = m.id
           WHERE op.id = ?";

$stmt_op = $conn->prepare($sql_op);
$stmt_op->bind_param("i", $op_id);
$stmt_op->execute();
$result_op = $stmt_op->get_result();

if ($result_op->num_rows === 0) {
    die("Ordem de Produção não encontrada.");
}
$op = $result_op->fetch_assoc();
$stmt_op->close();


// --- OBSERVAÇÃO PARA ANÁLISE (1/2) ---
// A consulta SQL foi modificada para usar a função `calcularVolume()` diretamente no banco de dados.
// O `CASE` garante que a função só é chamada quando a unidade_medida2 do produto é 'M3'.
// O resultado do cálculo já vem pronto do banco de dados na coluna 'volume_calculado'.
$sql_materiais_empenhados = "SELECT
                                p.codigo,
                                p.nome,
                                em.quantidade_inicial,
                                p.unidade_medida,
                                CASE 
                                    WHEN UPPER(p.unidade_medida2) = 'M3' 
                                    THEN calcularVolume(em.quantidade_inicial, p.espessura, p.largura, p.comprimento)
                                    ELSE 0 
                                END AS volume_calculado
                           FROM empenho_materiais em
                           JOIN produtos p ON em.produto_id = p.id
                           WHERE em.ordem_producao_id = ? 
                           ORDER BY p.nome";

$stmt_materiais = $conn->prepare($sql_materiais_empenhados);
$stmt_materiais->bind_param("i", $op_id);
$stmt_materiais->execute();
$result_materiais = $stmt_materiais->get_result();
$materiais_empenhados = $result_materiais->fetch_all(MYSQLI_ASSOC);
$stmt_materiais->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Produção - <?php echo htmlspecialchars($op['numero_op']); ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #fff; color: #333; font-size: 12px; }
        .container { width: 95%; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #222; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .header-info div { flex-basis: 48%; }
        .info-section { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; }
        .info-section h2 { margin-top: 0; font-size: 16px; color: #444; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .info-section p { margin: 5px 0; line-height: 1.6; }
        .info-section strong { display: inline-block; width: 150px; }
        .materials-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .materials-table th, .materials-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .materials-table th { background-color: #f2f2f2; font-weight: bold; }
        .materials-table .text-end { text-align: right; }
        .page-footer-container { margin-top: 40px; }
        .footer { display: flex; justify-content: space-between; text-align: center; }
        .footer .signature { border-top: 1px solid #333; padding-top: 10px; width: 40%; }
        .print-timestamp { text-align: center; font-style: italic; color: #666; margin-top: 30px; font-size: 10px; }
        @media print {
            body { font-size: 10pt; margin: 0; padding: 0; }
            .container { box-shadow: none; border: none; width: 100%; max-width: 100%; margin: 0; padding: 20px; padding-bottom: 150px; }
            .no-print { display: none; }
            .page-footer-container { position: fixed; bottom: 15px; left: 20px; right: 20px; width: auto; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ordem de Produão</h1>
        
        <div class="header-info">
            <div>
                <p><strong>Número da OP:</strong> <?php echo htmlspecialchars($op['numero_op']); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($op['status']); ?></p>
            </div>
            <div>
                <p><strong>Data de Emissão:</strong> <?php echo date('d/m/Y', strtotime($op['data_emissao'])); ?></p>
                <p><strong>Previsão de Conclusão:</strong> <?php echo $op['data_prevista_conclusao'] ? date('d/m/Y', strtotime($op['data_prevista_conclusao'])) : 'N/A'; ?></p>
            </div>
        </div>

        <div class="info-section">
            <h2>Produto a ser Produzido</h2>
            <p><strong>Cdigo do Produto:</strong> <?php echo htmlspecialchars($op['produto_codigo']); ?></p>
            <p><strong>Nome do Produto:</strong> <?php echo htmlspecialchars($op['produto_nome']); ?></p>
            <p>
                <strong>Quantidade a Produzir:</strong>
                <?php
                echo htmlspecialchars(number_format($op['quantidade_produzir'], 2, ',', '.'));
                // OBSERVAÇO: Adicionado cálculo de volume para o produto principal da OP.
                // O volume só é exibido se a unidade_medida2 for 'M3'.
                if (isset($op['unidade_medida2']) && strtoupper($op['unidade_medida2']) === 'M3') {
                    $volume_produto = ($op['quantidade_produzir'] * $op['espessura'] * $op['largura'] * $op['comprimento']) / 1000000000;
                    echo ' <span style="font-style: italic; color: #555;">(' . number_format($volume_produto, 2, ',', '.') . ' M³)</span>';
                }
                ?>
            </p>
        </div>

        <div class="info-section">
            <h2>Detalhes da Produção</h2>
            <p><strong>Máquina Ideal:</strong> <?php echo htmlspecialchars($op['maquina_nome'] ?? 'Não especificada'); ?></p>
            <p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($op['observacoes'])); ?></p>
        </div>

        <div class="info-section">
            <h2>Lista de Materiais Empenhados</h2>
            <?php if (!empty($materiais_empenhados)): ?>
                <table class="materials-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome do Material</th>
                            <th>Qtd. Empenhada</th>
                            <th>Unidade</th>
                            <th class="text-end">Volume (M³)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materiais_empenhados as $material): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($material['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($material['nome']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($material['quantidade_inicial'], 2, ',', '.')); ?></td>
                                <td><?php echo htmlspecialchars($material['unidade_medida']); ?></td>
                                <td class="text-end">
                                    <?php
                                    // --- OBSERVAÇÃO PARA ANLISE (2/2) ---
                                    // O cálculo já não é mais feito aqui no PHP.
                                    // Apenas exibimos o valor do campo 'volume_calculado' que veio do banco, se for maior que zero.
                                    if ($material['volume_calculado'] > 0) {
                                        echo number_format($material['volume_calculado'], 2, ',', '.');
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhum material empenhado para esta Ordem de Produção.</p>
            <?php endif; ?>
        </div>
        
        <div class="page-footer-container">
            <div class="footer">
                <div class="signature">
                    <p>Responsável PCP</p>
                </div>
                <div class="signature">
                    <p>Responsável Produção</p>
                </div>
            </div>
            <div class="print-timestamp">
                <p>Impresso em <?php echo date('d/m/Y \à\s H:i'); ?></p>
            </div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 20px;" class="no-print">
        <button onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
