<?php
// modules/empenho_manual/excluir.php
// Esta página é responsável por "excluir" (soft delete) um empenho manual.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração
require_once __DIR__ . '/../../config/database.php';

// Variáveis para armazenar a mensagem de feedback
$message = '';
$message_type = '';

// Pega o ID do empenho da URL (GET)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $conn = connectDB();
    $conn->begin_transaction();

    try {
        // 1. Obter detalhes do empenho original antes de marcá-lo como excluído
        $empenho_original_data = null;
        $sql_get_original_empenho = "SELECT produto_id, quantidade_empenhada, ordem_producao_id FROM empenho_materiais WHERE id = ? AND deleted_at IS NULL";
        $result_original_empenho = $conn->execute_query($sql_get_original_empenho, [$id]);
        if ($result_original_empenho && $row = $result_original_empenho->fetch_assoc()) {
            $empenho_original_data = $row;
            $result_original_empenho->free();
        } else {
            throw new mysqli_sql_exception("Empenho manual não encontrado ou já excluído logicamente.");
        }

        $produto_id_empenho = $empenho_original_data['produto_id'];
        $quantidade_empenhada_original = (float)$empenho_original_data['quantidade_empenhada'];
        $ordem_producao_id_empenho = $empenho_original_data['ordem_producao_id'];

        // 2. Marcar o empenho como excluído (soft delete)
        $sql_soft_delete_empenho = "UPDATE empenho_materiais SET deleted_at = NOW() WHERE id = ?";
        $conn->execute_query($sql_soft_delete_empenho, [$id]);

        // 3. Ajustar o estoque_empenhado na tabela produtos
        // A quantidade original empenhada é liberada do estoque_empenhado total do produto
        $sql_update_produto_empenhado = "UPDATE produtos SET estoque_empenhado = GREATEST(0, estoque_empenhado - ?) WHERE id = ?";
        $conn->execute_query($sql_update_produto_empenhado, [$quantidade_empenhada_original, $produto_id_empenho]);

        // 4. Registrar a movimentação de estoque como "desempenho" manual
        $sql_mov = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
        
        // Para fins de log, tentar buscar o número da OP
        $numero_op_log = 'N/A';
        $sql_get_op_num = "SELECT numero_op FROM ordens_producao WHERE id = ?";
        $result_op_num = $conn->execute_query($sql_get_op_num, [$ordem_producao_id_empenho]);
        if ($result_op_num && $row_op_num = $result_op_num->fetch_assoc()) {
            $numero_op_log = $row_op_num['numero_op'];
            $result_op_num->free();
        }

        $origem_destino_log = "Desempenho Manual OP: " . $numero_op_log . " (Empenho ID: " . $id . ")";
        
        $conn->execute_query($sql_mov, [
            $produto_id_empenho,
            'desempenho', // Tipo de movimentação
            $quantidade_empenhada_original,
            date('Y-m-d H:i:s'), // Data/hora da exclusão/desempenho
            $origem_destino_log,
            "Empenho manual excluído (ID: " . $id . ") - Quantidade liberada do empenho."
        ]);

        $conn->commit();
        $message = "Empenho manual excluído (logicamente) com sucesso! Estoque empenhado ajustado.";
        $message_type = "success";

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $message = "Erro na transação de exclusão de empenho manual: " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal na exclusão de Empenho Manual: " . $e->getMessage());
    }

    $conn->close();
} else {
    $message = "ID do empenho manual inválido para exclusão.";
    $message_type = "error";
}

// Redireciona de volta para a página de listagem de empenhos manuais
header("Location: " . BASE_URL . "/modules/empenho_manual/index.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>
