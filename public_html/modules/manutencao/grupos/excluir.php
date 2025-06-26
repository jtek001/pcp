<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];
$conn = connectDB();

// OBSERVAÇÃO: Antes de excluir, seria ideal verificar se o grupo não está em uso em algum roteiro.
// Por simplicidade, vamos apenas fazer o soft delete.

$sql = "UPDATE grupos_maquinas SET deleted_at = NOW() WHERE id = ?";

try {
    $conn->execute_query($sql, [$id]);
    $_SESSION['message'] = "Grupo de máquinas excluído com sucesso.";
    $_SESSION['message_type'] = "success";
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao excluir grupo: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

$conn->close();
header("Location: index.php");
exit();
?>
