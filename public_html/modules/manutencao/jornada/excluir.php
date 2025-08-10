<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$conn = connectDB();
$log_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$log_id) {
    $_SESSION['message'] = "ID do registro de jornada inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

try {
    // Exclui permanentemente o registro do log, pois é uma ação de correção
    $sql = "DELETE FROM maquina_jornada_log WHERE id = ?";
    $conn->execute_query($sql, [$log_id]);
    
    $_SESSION['message'] = "Registro de jornada excluído com sucesso!";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $_SESSION['message'] = "Erro ao excluir registro: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

header("Location: index.php");
exit();
?>
