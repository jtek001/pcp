<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$etapa_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$roteiro_id_retorno = null;

if (!$etapa_id) {
    $_SESSION['message'] = "ID da etapa inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

try {
    // Primeiro, busca o roteiro_id para saber para onde voltar
    $sql_get_roteiro = "SELECT roteiro_id FROM roteiro_etapas WHERE id = ?";
    $result = $conn->execute_query($sql_get_roteiro, [$etapa_id]);
    if ($row = $result->fetch_assoc()) {
        $roteiro_id_retorno = $row['roteiro_id'];
    }

    // Lógica de Soft Delete
    $sql = "UPDATE roteiro_etapas SET deleted_at = NOW() WHERE id = ?";
    $conn->execute_query($sql, [$etapa_id]);
    
    $_SESSION['message'] = "Etapa do roteiro excluída com sucesso.";
    $_SESSION['message_type'] = "success";

} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao excluir etapa: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Redireciona de volta para a página de etapas do roteiro correto
if ($roteiro_id_retorno) {
    header("Location: etapas.php?roteiro_id=" . $roteiro_id_retorno);
} else {
    header("Location: index.php"); // Fallback
}
exit();
?>
