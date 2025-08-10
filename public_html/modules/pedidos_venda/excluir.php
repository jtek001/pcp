<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['message'] = "ID do pedido inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

try {
    // Primeiro, busca o número do pedido para a verificação
    $sql_get_pedido = "SELECT numero_pedido FROM pedidos_venda WHERE id = ?";
    $pedido_data = $conn->execute_query($sql_get_pedido, [$id])->fetch_assoc();

    if ($pedido_data) {
        $numero_pedido = $pedido_data['numero_pedido'];

        // OBSERVAÇÃO: A validação foi corrigida para verificar se existem OPs ativas (deleted_at IS NULL).
        $sql_check_ops = "SELECT COUNT(id) as total FROM ordens_producao WHERE numero_pedido = ? AND deleted_at IS NULL";
        $ops_count = $conn->execute_query($sql_check_ops, [$numero_pedido])->fetch_assoc()['total'] ?? 0;

        if ($ops_count > 0) {
            // Se houver OPs ativas, bloqueia a exclusão
            throw new Exception("Não é possível excluir este pedido, pois ele possui Ordens de Produção ativas associadas.");
        }

        // Se não houver OPs, prossegue com o soft delete
        $sql_delete = "UPDATE pedidos_venda SET deleted_at = NOW() WHERE id = ?";
        $conn->execute_query($sql_delete, [$id]);
        
        $_SESSION['message'] = "Pedido excluído com sucesso.";
        $_SESSION['message_type'] = "success";

    } else {
        throw new Exception("Pedido não encontrado.");
    }

} catch (Exception $e) {
    $_SESSION['message'] = "Erro ao excluir pedido: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>
