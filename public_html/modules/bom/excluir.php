<?php
// modules/bom/excluir.php
// Esta página é responsável por "excluir" (soft delete) um item da Lista de Materiais (BoM).

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração
require_once __DIR__ . '/../../config/database.php';

// Variáveis para armazenar a mensagem de feedback
$message = '';
$message_type = '';

// Pega o ID do item da BoM da URL (GET)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $conn = connectDB();
    
    // Realiza o "soft delete"
    $sql = "UPDATE lista_materiais SET deleted_at = NOW() WHERE id = ?";
    
    try {
        $result_update = $conn->execute_query($sql, [$id]);

        if ($result_update === TRUE) {
            $message = "Item da Lista de Materiais excluído (logicamente) com sucesso!";
            $message_type = "success";
        } else {
            $message = "Erro ao excluir item da Lista de Materiais: " . $conn->error;
            $message_type = "error";
            error_log("Erro ao excluir item da BoM: " . $conn->error);
        }
    } catch (mysqli_sql_exception $e) {
        $message = "Erro ao excluir item da Lista de Materiais (SQL): " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal ao excluir item da BoM: " . $e->getMessage());
    }

    $conn->close();
} else {
    $message = "ID do item da Lista de Materiais inválido para exclusão.";
    $message_type = "error";
}

// Redireciona de volta para a página de listagem da BoM
header("Location: " . BASE_URL . "/modules/bom/index.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>
