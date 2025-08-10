<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$pedido_id_retorno = null;

if (!$item_id) {
    $_SESSION['message'] = "ID do item inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$conn->begin_transaction();
try {
    // Primeiro, busca o pedido_id para saber para onde voltar
    $sql_get_pedido_id = "SELECT pedido_venda_id FROM pedidos_venda_itens WHERE id = ?";
    $result = $conn->execute_query($sql_get_pedido_id, [$item_id]);
    if ($row = $result->fetch_assoc()) {
        $pedido_id_retorno = $row['pedido_venda_id'];
    } else {
        throw new Exception("Item do pedido não encontrado.");
    }

    // Lógica de Soft Delete
    $sql = "UPDATE pedidos_venda_itens SET deleted_at = NOW() WHERE id = ?";
    $conn->execute_query($sql, [$item_id]);
    
    // Recalcula o total do pedido após a exclusão do item
    $total_result = $conn->execute_query("SELECT SUM(subtotal) as total FROM pedidos_venda_itens WHERE pedido_venda_id = ? AND deleted_at IS NULL", [$pedido_id_retorno])->fetch_assoc();
    $novo_total = $total_result['total'] ?? 0.00;
    $conn->execute_query("UPDATE pedidos_venda SET valor_total = ? WHERE id = ?", [$novo_total, $pedido_id_retorno]);

    $conn->commit();
    $_SESSION['message'] = "Item do pedido removido com sucesso.";
    $_SESSION['message_type'] = "success";

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao remover item: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Redireciona de volta para a página de edição do pedido correto
if ($pedido_id_retorno) {
    header("Location: editar.php?id=" . $pedido_id_retorno);
} else {
    header("Location: index.php"); // Fallback
}
exit();
?>
