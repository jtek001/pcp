<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$conn = connectDB();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['message'] = "ID do motivo de parada inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Lógica de Soft Delete: atualiza a coluna 'deleted_at' com a data e hora atuais.
$sql = "UPDATE motivos_parada SET deleted_at = NOW() WHERE id = ?";

try {
    $conn->execute_query($sql, [$id]);
    $_SESSION['message'] = "Motivo de parada excluído com sucesso.";
    $_SESSION['message_type'] = "success";
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao excluir motivo de parada: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>
