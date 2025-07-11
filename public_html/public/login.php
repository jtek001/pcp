<?php
// public/login.php
// Esta página contém o formulário de login e processa a autenticacao de usuários (operadores).

// Inicia a sessão no topo do script para usar variáveis de sessão
session_start();

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Brasília.
date_default_timezone_set('America/Sao_Paulo');

// Inclui o arquivo de configuração do banco de dados para a função connectDB() e sanitizeInput()
require_once __DIR__ . '/../config/database.php';

// Variáveis para mensagens de sucesso/erro
$message = '';
$message_type = '';

// Recupera mensagens da sessão (para login bem-sucedido, por exemplo)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Limpa as mensagens da sessão
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
} elseif (isset($_GET['message'])) { // OU recupera mensagens passadas via GET (ex: após logout)
    $message = sanitizeInput($_GET['message']);
    $message_type = sanitizeInput($_GET['type'] ?? 'info'); // 'info' como padrão se tipo não for especificado
}


// Se já estiver logado, redireciona para a página inicial
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/index.php");
    exit();
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Senha não sanitizada para password_verify

    if (empty($username) || empty($password)) {
        $message = "Usuário e senha são obrigatórios.";
        $message_type = "error";
    } else {
        $conn = connectDB();

        if ($conn === null) {
            $message = "Erro de conexão com o banco de dados. Tente novamente mais tarde.";
            $message_type = "error";
        } else {
            // Busca o operador pelo username, cargo e verifica se está ativo
            // --- INÍCIO DA ALTERAÇÃO: Selecionar a coluna 'cargo' (09/06/2025 - IA) ---
            $sql = "SELECT id, username, password_hash, nome, cargo FROM operadores WHERE username = ? AND ativo = 1 AND deleted_at IS NULL LIMIT 1";
            // --- FIM DA ALTERAÇÃO: Selecionar a coluna 'cargo' ---
            
            try {
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    throw new mysqli_sql_exception("Falha ao preparar a consulta: " . $conn->error);
                }
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                $conn->close();

                if ($user) {
                    // Verifica a senha hash
                    if (password_verify($password, $user['password_hash'])) {
                        // Autenticaço bem-sucedida
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_name'] = $user['nome']; // Nome real do operador
                        // --- INÍCIO DA ALTERAÇÃO: Armazenar 'cargo' na sessão (09/06/2025 - IA) ---
                        $_SESSION['user_cargo'] = $user['cargo']; // Armazena o cargo do operador
                        // --- FIM DA ALTERAO: Armazenar 'cargo' ---
                        $_SESSION['message'] = "Login realizado com sucesso!";
                        $_SESSION['message_type'] = "success";

                        // Redireciona para a página inicial
                        header("Location: " . BASE_URL . "/public/index.php");
                        exit();
                    } else {
                        // Senha invlida para usuário existente (e ativo)
                        $message = "Usuário ou senha inválidos.";
                        $message_type = "error";
                    }
                } else {
                    // Usuário não encontrado ou não ativo
                    $message = "Usuário ou senha inválidos.";
                    $message_type = "error";
                }

            } catch (mysqli_sql_exception $e) {
                $message = "Erro ao autenticar: " . $e->getMessage();
                $message_type = "error";
                error_log("Erro durante a autenticao de login: " . $e->getMessage());
                if ($conn && !$conn->is_closed()) $conn->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PCP System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style.css">
    <link rel="icon" type="image/x-icon" href="/public/img/pcp-sys.png">
    <style>
        /* Estilos específicos para a pgina de login */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #2c3e50; /* Fundo azul escuro */
            padding-top: 0; /* Remove padding-top do body global */
        }
        main {
            padding: 40px; /* Aumenta padding */
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3); /* Sombra mais forte */
            max-width: 230px; /* Limita a largura do formulrio */
            width: 90%; /* Responsivo */
            background-color: #fff;
            margin: auto; /* Centraliza */
        }
        h1 {
            color: #3498db; /* Azul vibrante */
            margin-bottom: 30px;
            font-size: 2.2rem;
        }
        .message {
            margin-bottom: 20px;
            padding: 12px;
        }
        /* Layout dos campos de Login */
        form { 
            display: flex;
            flex-direction: column; /* Empilha os form-groups verticalmente */
            gap: 20px; /* Espaçamento entre os form-groups */
            padding: 0; 
            box-shadow: none; 
            background-color: transparent; 
            margin: 0; 
        }
        .form-group {
            margin-bottom: 0; 
        }
        .form-group label {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 8px;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 1rem;
            box-sizing: border-box;
            width: 100%;
        }
        .button.submit {
            width: 100%;
            padding: 15px;
            font-size: 1.2rem;
            margin-top: 25px; 
        }
        .message.error {
            background-color: #f8d7da; /* Vermelho claro */
            color: #721c24; /* Vermelho escuro */
            border: 1px solid #f5c6fb;
        }
    </style>
</head>
<body class="login-page">
    <main>
        <h1><img src="<?php echo BASE_URL; ?>/public/img/pcp-system.png" width="220" /></h1>
        <?php if ($message): // Exibe a mensagem de feedback (sucesso ou erro) ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Usuário:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="button submit">Entrar</button>
        </form>
    </main>
</body>
</html>
