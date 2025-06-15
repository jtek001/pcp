<?php
// modules/produtos/excluir.php
// Esta página é responsável por "excluir" (soft delete) um produto do banco de dados e redirecionar.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração (para ter acesso à função connectDB e sanitizeInput)
require_once __DIR__ . '/../../config/database.php';

// Variáveis para armazenar a mensagem de feedback
$message = '';
$message_type = '';

// Pega o ID do produto da URL (GET)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Verifica se o ID é válido
if ($id > 0) {
    // Conecta ao banco de dados
    $conn = connectDB();

    // Prepara a consulta SQL para realizar o "soft delete"
    // Atualiza o campo 'deleted_at' com a data e hora atual
    $sql = "UPDATE produtos SET deleted_at = NOW() WHERE id = ?";
    
    // Parâmetros para execute_query
    $params = [$id];

    // Executa a consulta usando execute_query()
    $result_update = $conn->execute_query($sql, $params);

    if ($result_update === TRUE) {
        $message = "Produto marcado como excluído com sucesso!";
        $message_type = "success";
    } else {
        $message = "Erro ao marcar produto como excluído: " . $conn->error;
        $message_type = "error";
    }

    // Fecha a conexão com o banco de dados
    $conn->close();
} else {
    // Se o ID não for válido, define uma mensagem de erro
    $message = "ID do produto inválido para exclusão.";
    $message_type = "error";
}

// Redireciona de volta para a página de listagem de produtos, passando a mensagem de feedback
// O `urlencode` é importante para garantir que a mensagem seja corretamente transmitida via URL
header("Location: " . BASE_URL . "/modules/produtos/index.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit(); // Garante que nenhum outro código PHP seja executado após o redirecionamento
?>
