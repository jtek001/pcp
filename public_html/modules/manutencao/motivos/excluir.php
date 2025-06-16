<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Verifica se o ID foi fornecido e é numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID do motivo inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];
$conn = connectDB();

// Lógica de Soft Delete: atualiza a coluna 'deleted_at' com a data e hora atuais
$sql = "UPDATE motivos_parada SET deleted_at = NOW() WHERE id = ?";

try {
    $conn->execute_query($sql, [$id]);
    $_SESSION['message'] = "Motivo de parada excluído com sucesso.";
    $_SESSION['message_type'] = "success";
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao excluir motivo de parada: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

$conn->close();
header("Location: index.php");
exit();
?>
