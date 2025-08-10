<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['message'] = "ID da entrada inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$conn->begin_transaction();
try {
    // 1. Busca os dados da entrada para saber o que reverter
    $sql_get_entrada = "SELECT produto_id, quantidade FROM materiais_insumos_entrada WHERE id = ?";
    $entrada_data = $conn->execute_query($sql_get_entrada, [$id])->fetch_assoc();

    if (!$entrada_data) {
        throw new Exception("Registro de entrada não encontrado.");
    }
    
    $produto_id = $entrada_data['produto_id'];
    $quantidade_a_reverter = $entrada_data['quantidade'];

    // 2. Reverte o estoque do produto (subtrai o que foi adicionado)
    $sql_reverte_estoque = "UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?";
    $conn->execute_query($sql_reverte_estoque, [$quantidade_a_reverter, $produto_id]);

    // 3. Marca a entrada como deletada (soft delete)
    $sql_delete = "UPDATE materiais_insumos_entrada SET deleted_at = NOW() WHERE id = ?";
    $conn->execute_query($sql_delete, [$id]);

    $conn->commit();
    $_SESSION['message'] = "Entrada de material excluída e estoque revertido com sucesso.";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao excluir entrada: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>
