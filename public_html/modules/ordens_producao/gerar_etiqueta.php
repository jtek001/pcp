<?php
// modules/chao_de_fabrica/gerar_etiqueta.php
// Esta pagina gera uma etiqueta de produo para impressão.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Brasilia.
date_default_timezone_set('America/Sao_Paulo');

// Inclui os arquivos de configuração (para ter acesso à função connectDB e COMPANY_NAME)
require_once __DIR__ . '/../../config/database.php';

// Conecta ao banco de dados
$conn = connectDB();

$apontamento_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$op_id_from_get = isset($_GET['op_id']) ? (int) $_GET['op_id'] : 0; // Recebe o ID da OP para redirecionamento
$message_from_get = sanitizeInput($_GET['message'] ?? ''); // Mensagem de sucesso/erro
$message_type_from_get = sanitizeInput($_GET['type'] ?? ''); // Tipo da mensagem

$etiqueta_data = null;

if ($apontamento_id > 0) {
    // A consulta busca as dimensões e chama a função calcularVolume()
    $sql = "SELECT
                ap.quantidade_produzida,
                ap.data_apontamento,
                ap.observacoes,
                ap.id AS apontamento_id,
                op.numero_op,
                op.numero_pedido,
                p.nome AS produto_nome,
                p.codigo AS produto_codigo,
                p.unidade_medida2,
                p.espessura,
                p.largura,
                p.comprimento,
                m.nome AS maquina_nome,
                o.nome AS operador_nome,
                o.matricula AS operador_matricula,
                CASE 
                    WHEN UPPER(p.unidade_medida2) = 'M3' 
                    THEN calcularVolume(ap.quantidade_produzida, p.espessura, p.largura, p.comprimento)
                    ELSE 0 
                END AS volume_calculado
            FROM
                apontamentos_producao ap
            JOIN
                ordens_producao op ON ap.ordem_producao_id = op.id
            JOIN
                produtos p ON op.produto_id = p.id
            LEFT JOIN
                maquinas m ON ap.maquina_id = m.id
            LEFT JOIN
                operadores o ON ap.operador_id = o.id
            WHERE
                ap.id = ? AND ap.deleted_at IS NULL"; 

    try {
        $result = $conn->execute_query($sql, [$apontamento_id]);
        if ($result) {
            $etiqueta_data = $result->fetch_assoc();
            $result->free();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Erro ao buscar dados da etiqueta: " . $e->getMessage());
        $etiqueta_data = null;
    }
}

$conn->close();

if (!$etiqueta_data): ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro ao Gerar Etiqueta</title>
    <style>
        body { font-family: sans-serif; text-align: center; margin-top: 50px; }
        .error-message { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <p class="error-message">Erro: Dados do apontamento não encontrados para gerar a etiqueta.</p>
    <button onclick="window.close()">Fechar</button>
</body>
</html>
<?php else: 
    // Monta o Lote: OP_NUMERO-ID_APONTAMENTO
    $lote = htmlspecialchars($etiqueta_data['numero_op']) . '-' . htmlspecialchars($etiqueta_data['apontamento_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta de Produção - Lote <?php echo $lote; ?></title>
    <!-- Biblioteca JsBarcode para gerar o código de barras -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f0f0;
        }
        .label-container {
            width: 10cm; 
            height: 10cm; 
            border: 1px solid #000;
            padding: 0.5cm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-color: #fff;
            position: relative;
            overflow: hidden;
        }
        .header-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 0.2cm;
        }
        .company-name {
            font-size: 0.8cm;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
            text-align: left;
            margin-bottom: 0.2cm;
        }
        .lote-number {
            font-size: 0.6cm;
            font-weight: bold;
            text-align: left;
            white-space: nowrap;
        }
        .product-name {
            /* OBSERVAÇÃO: Fonte do nome do produto ligeiramente reduzida */
            font-size: 0.8cm; 
            font-weight: bold;
            text-align: left;
            margin: 0.2cm 0;
            word-break: break-word;
        }
        .details-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex-grow: 1;
            justify-content: center;
        }
        .detail-row {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 0.1cm;
            width: 100%;
        }
        .detail-label {
            font-weight: bold;
            font-size: 0.4cm;
            white-space: nowrap;
            margin-right: 0.2cm;
            text-align: left;
        }
        .detail-value {
            font-size: 0.4cm;
            text-align: left;
            word-break: break-all;
        }
        .footer-section {
            text-align: center;
           
        }
        #barcode {
            width: 100%;
            height: 60px;
        }
        
        @page {
            size: 10cm 10cm;
            margin: 0;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                display: block;
                background-color: #fff;
            }
            .label-container {
                border: none;
                box-shadow: none;
                page-break-after: always;
                width: 10cm;
                height: 10cm;
                padding: 0.5cm;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label-container">
        <div class="header-section">
            <div class="company-name"><img src="../../public/img/logo-etiq.png" height="40" /></div>
            <div class="lote-number">Lote: <?php echo $lote; ?></div>
        </div>

        <div class="product-name">
            <?php echo htmlspecialchars($etiqueta_data['produto_nome']); ?>
        </div>

        <div class="details-section">
            <div class="detail-row">
                <span class="detail-label">Máquina:</span>
                <span class="detail-value"><?php echo htmlspecialchars($etiqueta_data['maquina_nome'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Operador:</span>
                <span class="detail-value"><?php echo htmlspecialchars($etiqueta_data['operador_nome'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Qtde:</span>
                <span class="detail-value">
                    <?php 
                    echo number_format($etiqueta_data['quantidade_produzida'], 2, ',', '.'); 
                    if ($etiqueta_data['volume_calculado'] > 0) {
                        echo ' <span style="font-style: italic;">(' . number_format($etiqueta_data['volume_calculado'], 2, ',', '.') . ' M³)</span>';
                    }
                    ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Data:</span>
                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($etiqueta_data['data_apontamento'])); ?></span>
            </div>
        </div>

        <div class="footer-section">
            <svg id="barcode"></svg>
        </div>
    </div>
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Imprimir Novamente</button>
        <?php if ($op_id_from_get > 0): ?>
            <a href="<?php echo BASE_URL; ?>/modules/ordens_producao/apontar.php?id=<?php echo htmlspecialchars($op_id_from_get); ?>" style="padding: 10px 20px; font-size: 16px; cursor: pointer; text-decoration: none; background-color: #007bff; color: white; border-radius: 5px; margin-left: 10px;">Voltar à OP</a>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loteNumero = "<?php echo $lote; ?>";
            if (loteNumero) {
                JsBarcode("#barcode", loteNumero, {
                    format: "CODE128",
                    lineColor: "#000",
                    width: 3.5,
                    height: 80,
                    displayValue: true
                });
            }
        });
    </script>
</body>
</html>
<?php endif; ?>
