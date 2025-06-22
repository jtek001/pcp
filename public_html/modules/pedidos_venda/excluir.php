<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Verifica se o ID foi fornecido e é numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID do pedido inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];
$conn = connectDB();

// Lógica de Soft Delete: atualiza a coluna 'deleted_at' com a data e hora atuais.
// É importante notar que, como a tabela de itens (pedidos_venda_itens) tem a regra ON DELETE CASCADE,
// se usássemos um DELETE físico aqui, todos os itens do pedido seriam apagados.
// O soft delete é mais seguro e mantém a integridade dos dados históricos.
$sql = "UPDATE pedidos_venda SET deleted_at = NOW() WHERE id = ?";

try {
    $conn->execute_query($sql, [$id]);
    $_SESSION['message'] = "Pedido excluído com sucesso.";
    $_SESSION['message_type'] = "success";
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao excluir pedido: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

$conn->close();
header("Location: index.php");
exit();
?>
