<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['message'] = "ID da Ordem de Produção inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$conn->begin_transaction();
try {
    // 1. Verificação de segurança: A OP já teve produção apontada?
    $sql_check_apontamentos = "SELECT COUNT(id) as total FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL";
    $apontamentos_count = $conn->execute_query($sql_check_apontamentos, [$id])->fetch_assoc()['total'] ?? 0;

    if ($apontamentos_count > 0) {
        throw new Exception("Não é possível excluir esta OP, pois ela já possui apontamentos de produção registrados.");
    }

    // 2. Verificação de segurança: A OP possui OPs filhas ativas?
    $sql_check_children = "SELECT COUNT(id) as total FROM ordens_producao WHERE op_mae_id = ? AND deleted_at IS NULL";
    $children_count = $conn->execute_query($sql_check_children, [$id])->fetch_assoc()['total'] ?? 0;

    if ($children_count > 0) {
        throw new Exception("Não é possível excluir esta OP, pois ela possui OPs filhas ativas. Exclua as OPs filhas primeiro.");
    }

    // 3. Busca e reverte todos os empenhos associados a esta OP
    $sql_get_empenhos = "SELECT produto_id, quantidade_empenhada FROM empenho_materiais WHERE ordem_producao_id = ? AND deleted_at IS NULL";
    $empenhos = $conn->execute_query($sql_get_empenhos, [$id])->fetch_all(MYSQLI_ASSOC);

    foreach ($empenhos as $empenho) {
        $produto_id = $empenho['produto_id'];
        $quantidade = $empenho['quantidade_empenhada'];

        // Devolve a quantidade ao estoque empenhado do produto
        $conn->execute_query("UPDATE produtos SET estoque_empenhado = GREATEST(0, estoque_empenhado - ?) WHERE id = ?", [$quantidade, $produto_id]);
    }

    // 4. Marca os registros de empenho como excluídos (soft delete)
    $conn->execute_query("UPDATE empenho_materiais SET deleted_at = NOW() WHERE ordem_producao_id = ?", [$id]);

    // 5. Finalmente, marca a Ordem de Produção como excluída (soft delete)
    $sql_delete_op = "UPDATE ordens_producao SET deleted_at = NOW() WHERE id = ?";
    $conn->execute_query($sql_delete_op, [$id]);
    
    $conn->commit();
    $_SESSION['message'] = "Ordem de Produção e seus empenhos foram excluídos com sucesso.";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao excluir Ordem de Produção: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>
