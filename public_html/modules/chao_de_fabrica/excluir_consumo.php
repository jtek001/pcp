<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$consumo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$op_id_retorno = null;

if (!$consumo_id) {
    $_SESSION['message'] = "ID do consumo inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$conn->begin_transaction();
try {
    $sql_get_consumo = "SELECT * FROM consumo_producao WHERE id = ?";
    $consumo_data = $conn->execute_query($sql_get_consumo, [$consumo_id])->fetch_assoc();

    if (!$consumo_data) {
        throw new Exception("Registro de consumo não encontrado.");
    }

    $op_id_retorno = (int)$consumo_data['ordem_producao_id'];
    $quantidade_estornada = (float)$consumo_data['quantidade_consumida'];
    $produto_id_estornado = (int)$consumo_data['produto_material_id'];
    $apontamento_id_original = (int)$consumo_data['apontamento_id'];

    // 1. Reativa o lote original, limpando a data de consumo
    $conn->execute_query("UPDATE apontamentos_producao SET data_consumo = NULL WHERE id = ?", [$apontamento_id_original]);
    
    // 2. Devolve a quantidade ao estoque geral do produto
    $conn->execute_query("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?", [$quantidade_estornada, $produto_id_estornado]);

    // 3. OBSERVAÇÃO: Procura e remove o lote de devolução, se existir
    $lote_original_data = $conn->execute_query("SELECT lote_numero FROM apontamentos_producao WHERE id = ?", [$apontamento_id_original])->fetch_assoc();
    if ($lote_original_data) {
        $obs_devolucao_esperada = 'Devolução do lote ' . $lote_original_data['lote_numero'];
        $conn->execute_query("DELETE FROM apontamentos_producao WHERE observacoes = ?", [$obs_devolucao_esperada]);
    }

    // 4. Exclui o registro de consumo (soft delete)
    $conn->execute_query("UPDATE consumo_producao SET deleted_at = NOW() WHERE id = ?", [$consumo_id]);
    
    $conn->commit();
    $_SESSION['message'] = "Consumo estornado com sucesso! O lote está disponível novamente.";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao estornar consumo: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

header("Location: consumo.php?op_id=" . $op_id_retorno);
exit();
?>
