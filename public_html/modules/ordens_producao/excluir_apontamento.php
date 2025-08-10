<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$apontamento_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$op_id_retorno = null; // Para saber para qual OP voltar

if (!$apontamento_id) {
    $_SESSION['message'] = "ID do apontamento inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$conn->begin_transaction();
try {
    $sql_get_apontamento = "SELECT * FROM apontamentos_producao WHERE id = ?";
    $apontamento_data = $conn->execute_query($sql_get_apontamento, [$apontamento_id])->fetch_assoc();

    if ($apontamento_data) {
        $op_id_retorno = (int)$apontamento_data['ordem_producao_id'];
        $quantidade_a_estornar = (float)$apontamento_data['quantidade_produzida'];
        
        $sql_get_op_produto = "SELECT produto_id FROM ordens_producao WHERE id = ?";
        $produto_op_id = $conn->execute_query($sql_get_op_produto, [$op_id_retorno])->fetch_assoc()['produto_id'];

        // Reverte o estoque do produto acabado
        $conn->execute_query("UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?", [$quantidade_a_estornar, $produto_op_id]);

        // Reverte o empenho dos materiais
        $sql_bom_items = "SELECT produto_filho_id, quantidade_necessaria FROM lista_materiais WHERE produto_pai_id = ? AND deleted_at IS NULL";
        $result_bom_items = $conn->execute_query($sql_bom_items, [$produto_op_id]);
        if ($result_bom_items) {
            while ($bom_item = $result_bom_items->fetch_assoc()) {
                $material_id = (int)$bom_item['produto_filho_id'];
                $quantidade_a_reverter = (float)$bom_item['quantidade_necessaria'] * $quantidade_a_estornar;
                if ($quantidade_a_reverter > 0) {
                    $conn->execute_query("UPDATE empenho_materiais SET quantidade_empenhada = quantidade_empenhada + ? WHERE produto_id = ? AND ordem_producao_id = ?", [$quantidade_a_reverter, $material_id, $op_id_retorno]);
                    $conn->execute_query("UPDATE produtos SET estoque_empenhado = estoque_empenhado + ? WHERE id = ?", [$quantidade_a_reverter, $material_id]);
                }
            }
        }
        
        // CORREÇÃO: Usa "soft delete" em vez de apagar o registro permanentemente.
        $conn->execute_query("UPDATE apontamentos_producao SET deleted_at = NOW() WHERE id = ?", [$apontamento_id]);
        
        // A movimentação de estoque pode ser removida ou também marcada como "estornada".
        // Por simplicidade e consistência com a reverso, vamos removê-la.
        $obs_movimentacao = "Entrada por apontamento. Lote: " . $apontamento_data['lote_numero'];
        $conn->execute_query("DELETE FROM movimentacoes_estoque WHERE observacoes = ?", [$obs_movimentacao]);

        // Reabre a OP se estiver concluída
        $sql_get_op_status = "SELECT status FROM ordens_producao WHERE id = ?";
        $op_status = $conn->execute_query($sql_get_op_status, [$op_id_retorno])->fetch_assoc()['status'];
        if ($op_status === 'concluida') {
            $conn->execute_query("UPDATE ordens_producao SET status = 'em_producao', data_conclusao = NULL WHERE id = ?", [$op_id_retorno]);
        }

        $conn->commit();
        $_SESSION['message'] = "Apontamento excluído e estoques revertidos com sucesso.";
        $_SESSION['message_type'] = "success";
    }
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao excluir apontamento: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Redireciona de volta para a página de apontamento da OP correta
// O caminho relativo é ajustado para encontrar a página 'apontar.php' no módulo correto.
header("Location: ../ordens_producao/apontar.php?id=" . $op_id_retorno);
exit();
?>
