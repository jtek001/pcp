<?php
// modules/fornecedores_clientes/excluir.php
// Esta página é responsável por "excluir" (soft delete) um fornecedor ou cliente.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração
require_once __DIR__ . '/../../config/database.php';

// Variáveis para armazenar a mensagem de feedback
$message = '';
$message_type = '';

// Pega o ID do fornecedor/cliente da URL (GET)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $conn = connectDB();
    
    // Realiza o "soft delete"
    $sql = "UPDATE fornecedores_clientes_lookup SET deleted_at = NOW() WHERE id = ?";
    
    try {
        $result_update = $conn->execute_query($sql, [$id]);

        if ($result_update === TRUE) {
            $message = "Fornecedor/Cliente excluído (logicamente) com sucesso!";
            $message_type = "success";
        } else {
            $message = "Erro ao excluir Fornecedor/Cliente: " . $conn->error;
            $message_type = "error";
            error_log("Erro ao excluir Fornecedor/Cliente: " . $conn->error);
        }
    } catch (mysqli_sql_exception $e) {
        $message = "Erro ao excluir Fornecedor/Cliente (SQL): " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal ao excluir Fornecedor/Cliente: " . $e->getMessage());
    }

    $conn->close();
} else {
    $message = "ID de Fornecedor/Cliente inválido para exclusão.";
    $message_type = "error";
}

// Redireciona de volta para a página de listagem de fornecedores/clientes
header("Location: " . BASE_URL . "/modules/fornecedores_clientes/index.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>
