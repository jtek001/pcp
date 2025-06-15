<?php
// public/logout.php
// Este script destrói a sessão do usuário e o redireciona para a página de login.

session_start(); // Inicia a sessão

// Define a mensagem e o tipo ANTES de destruir a sessão
// e passá-los via GET
$message_to_pass = "Você foi desconectado com sucesso.";
$message_type_to_pass = "success";

// Destrói todas as variáveis de sessão
$_SESSION = array();

// Se for preciso destruir a sessão, também apaga o cookie de sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão
session_destroy();

// Inclui o arquivo de configuração para ter BASE_URL
require_once __DIR__ . '/../config/database.php';

// Redireciona para a página de login, passando a mensagem e o tipo via GET
// --- INÍCIO DA ALTERAÇÃO: Passar mensagem via GET (09/06/2025 - IA) ---
header("Location: " . BASE_URL . "/public/login.php?message=" . urlencode($message_to_pass) . "&type=" . urlencode($message_type_to_pass));
// --- FIM DA ALTERAÇÃO: Passar mensagem via GET ---
exit();

?>
