<?php
// modules/ordens_producao/gerar_etiqueta.php
// Esta pgina gera uma etiqueta de produção para impressão.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Braslia.
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
    $sql = "SELECT
                ap.quantidade_produzida,
                ap.data_apontamento,
                ap.observacoes,
                ap.id AS apontamento_id, -- Adiciona o ID do apontamento para o lote
                op.numero_op,
                op.numero_pedido,
                p.nome AS produto_nome,
                p.codigo AS produto_codigo,
                m.nome AS maquina_nome,
                o.nome AS operador_nome,
                o.matricula AS operador_matricula
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
                ap.id = ? AND ap.deleted_at IS NULL"; // Apenas apontamentos não excluídos

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
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column; /* Coloca os elementos em coluna */
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Garante que o corpo ocupe a altura total da viewport */
            background-color: #f0f0f0; /* Fundo cinza claro para visualização */
        }
        .label-container {
            width: 10cm; /* 100mm */
            height: 10cm; /* 100mm */
            border: 1px solid #000;
            padding: 0.5cm; /* Margem interna da etiqueta */
            box-sizing: border-box; /* Inclui padding na largura/altura */
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Espaça os elementos */
            background-color: #fff; /* Fundo da etiqueta branco */
            position: relative; /* Para positioning absoluto de elementos internos */
            overflow: hidden; /* Garante que nada saia do container */
        }
        /* --- INÍCIO DA ALTERAO: Estilo da Etiqueta (07/06/2025 - IA) --- */
        .header-section {
            display: flex;
            flex-direction: column; /* Empresa e Lote um abaixo do outro */
            align-items: flex-start; /* Alinha tudo à esquerda */
            margin-bottom: 0.2cm; /* Espao maior após cabeçalho */
        }
        .company-name {
            font-size: 0.8cm; /* Aumenta o tamanho da fonte da empresa */
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
            text-align: left;
            margin-bottom: 0.2cm; /* Pula uma linha após o nome da empresa */
        }
        .lote-number {
            font-size: 0.6cm;
            font-weight: bold;
            text-align: left; /* Alinhado à esquerda agora */
            white-space: nowrap; /* Evita quebra de linha */
        }
        .product-name {
            font-size: 1cm; /* Fonte grande e destacada para o nome do produto */
            font-weight: bold;
            text-align: left; /* Permanece centralizado */
            margin: 0.2cm 0; /* Espaçamento vertical */
            word-break: break-word; /* Quebra palavras longas */
        }
        .details-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Alinha detalhes à esquerda */
            flex-grow: 1; /* Permite que esta seção ocupe o espaço restante */
            justify-content: center; /* Centraliza verticalmente se houver espao */
        }
        .detail-row {
            display: flex; /* Mantém label e valor na mesma linha */
            justify-content: flex-start; /* Alinha a linha de detalhe à esquerda */
            margin-bottom: 0.1cm;
            width: 100%; /* Ocupa a largura total para alinhamento */
        }
        .detail-label {
            font-weight: bold;
            font-size: 0.5cm;
            white-space: nowrap;
            margin-right: 0.2cm; /* Espaço entre label e valor */
            text-align: left; /* Garante alinhamento à esquerda */
        }
        .detail-value {
            font-size: 0.5cm;
            /* flex-grow: 1; Removido para que o valor não ocupe todo o espaço à direita */
            text-align: left; /* Alinha o valor  esquerda */
            word-break: break-all; /* Quebra palavras muito longas */
        }
        .obs-section { /* Esta seão será removida do HTML, mas os estilos ficam caso queira re-adicionar no futuro */
            display: none; /* Oculta a seção de observações */
            margin-top: 0.3cm;
            font-size: 0.5cm;
            word-break: break-word;
        }
        .obs-label {
            font-weight: bold;
        }
        /* --- FIM DA ALTERAÃO: Estilo da Etiqueta --- */

        /* Estilos para impresso */
        @page {
            size: 10cm 10cm; /* Define o tamanho da página de impressão */
            margin: 0; /* Remove margens da página */
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                display: block; /* Garante que o corpo não seja flexbox na impressão */
                background-color: #fff; /* Remove fundo cinza na impressão */
            }
            .label-container {
                border: none; /* Remove a borda da caixa na impressão se desejar */
                box-shadow: none; /* Remove a sombra na impressão */
                page-break-after: always; /* Garante que cada etiqueta esteja em uma nova página se houver mais de uma */
                width: 10cm;
                height: 10cm;
                padding: 0.5cm;
            }
            /* Oculta elementos que no devem ser impressos, como botões */
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label-container">
        <div class="header-section">
            <div class="company-name"><img src="../../public/img/logo-jtek.png" height="40" /></div>
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
                <span class="detail-value"><?php echo number_format($etiqueta_data['quantidade_produzida'], 2, ',', '.'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Data:</span>
                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($etiqueta_data['data_apontamento'])); ?></span>
            </div>
        </div>

        <!-- Removido o obs-section completo conforme solicitado -->
        <!-- <div class="obs-section">
            <span class="obs-label">Obs:</span>
            <span><?php echo htmlspecialchars($etiqueta_data['observacoes'] ?? 'N/A'); ?></span>
        </div> -->
    </div>
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Imprimir Novamente</button>
        <?php if ($op_id_from_get > 0): // Botão para voltar apenas se houver OP ID ?>
            <a href="<?php echo BASE_URL; ?>/modules/chao_de_fabrica/apontar.php?id=<?php echo htmlspecialchars($op_id_from_get); ?>&message=<?php echo urlencode($message_from_get); ?>&type=<?php echo urlencode($message_type_from_get); ?>" style="padding: 10px 20px; font-size: 16px; cursor: pointer; text-decoration: none; background-color: #007bff; color: white; border-radius: 5px; margin-left: 10px;">Voltar à OP</a>
        <?php endif; ?>
    </div>
</body>
</html>
<?php endif; ?>
