<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$conn = connectDB();
$associacao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$grupo_id_retorno = null;

if (!$associacao_id) {
    $_SESSION['message'] = "ID da associação inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

try {
    // Primeiro, busca o grupo_id para saber para onde voltar
    $sql_get_grupo = "SELECT grupo_id FROM maquina_grupo_associacao WHERE id = ?";
    $result = $conn->execute_query($sql_get_grupo, [$associacao_id]);
    if ($row = $result->fetch_assoc()) {
        $grupo_id_retorno = $row['grupo_id'];
    }

    // Exclui a associação
    $sql = "DELETE FROM maquina_grupo_associacao WHERE id = ?";
    $conn->execute_query($sql, [$associacao_id]);
    
    $_SESSION['message'] = "Máquina removida do grupo com sucesso.";
    $_SESSION['message_type'] = "success";

} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao remover máquina do grupo: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Redireciona de volta para a página de gestão do grupo correto
if ($grupo_id_retorno) {
    header("Location: gerir_maquinas.php?id=" . $grupo_id_retorno);
} else {
    header("Location: index.php"); // Fallback
}
exit();
?>
