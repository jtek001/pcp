<?php
// config/database.php

// Configurações de conexão com o banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'pcp_user');     // Seu usuário do MySQL
define('DB_PASS', 'G021567e');         // Sua senha do MySQL
define('DB_NAME', 'pcp_system'); // Nome do seu banco de dados

// Define a URL base do seu projeto.
// Se a pasta 'pcp_system' estiver diretamente em 'htdocs' (ex: http://localhost/pcp_system/),
// ento a BASE_URL deve ser '/pcp_system'.
// Se seus arquivos estiverem na raiz do 'htdocs' (ex: http://localhost/), então a BASE_URL deve ser '/'.
define('BASE_URL', 'https://pcp.pcpsystem.com.br'); // AJUSTE AQUI conforme a sua configuração

// Define o nome da empresa
define('COMPANY_NAME', 'JtekInfo'); // Nome da empresa para uso global

/**
 * Estabelece uma conexão com o banco de dados MySQL.
 * Em caso de erro, loga o erro e retorna null em vez de encerrar a execução.
 *
 * @return mysqli|null Objeto de conexão MySQLi em caso de sucesso, ou null em caso de falha.
 */
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Verifica se houve erro na conexão
    if ($conn->connect_error) {
        error_log("Erro de conexão com o banco de dados: " . $conn->connect_error);
        return null; // Retorna null em vez de die()
    }

    // Define o charset para UTF-8 para garantir a correta exibição de caracteres especiais
    $conn->set_charset("utf8");

    // Define o fuso horário da conexão MySQL para corresponder ao fuso horrio do PHP.
    $conn->query("SET time_zone = '-03:00'"); // '-03:00' para America/Sao_Paulo (GMT-3)

    return $conn;
}

/**
 * Sanitiza e valida strings de entrada para prevenir ataques como XSS.
 *
 * @param string|null $data A string a ser sanitizada. Pode ser nula.
 * @return string A string sanitizada.
 */
function sanitizeInput($data) {
    // Garante que $data seja uma string, mesmo que seja nula, para evitar o aviso do trim().
    $data = (string) $data;
    // Remove espaços em branco do início e fim da string
    $data = trim($data);
    // Remove barras invertidas adicionadas por magic_quotes_gpc (se estiver ativo)
    $data = stripslashes($data);
    // Converte caracteres especiais em entidades HTML para prevenir Cross-Site Scripting (XSS)
    // ENT_QUOTES converte aspas simples e duplas.
    // 'UTF-8' especifica a codificação dos caracteres.
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
?>
