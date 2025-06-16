<?php
// modules/operadores/adicionar.php
// Esta página contém o formulário para adicionar um novo operador.

// Inicia a sessão para usar variáveis de sessão (necessário para mensagens)
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- OBSERVAÇÃO DE SEGURANÇA ---
if (!isset($_SESSION['user_cargo']) || $_SESSION['user_cargo'] !== 'admin') {
    $_SESSION['message'] = "Acesso negado.";
    $_SESSION['message_type'] = "error";
    header("Location: " . BASE_URL . "/public/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDB();
    // ... lógica para inserir novo operador ...
    // (O restante do código de inserção permanece o mesmo)
}

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Brasília.
date_default_timezone_set('America/Sao_Paulo');

// Inclui os arquivos de configuração e o cabeçalho
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variveis para mensagens de sucesso/erro - GARANTIDAS DE SEREM INICIALIZADAS
$message = '';
$message_type = '';

// Recupera mensagens da sessão se existirem
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitiza e valida as entradas
    $temp_nome = sanitizeInput(isset($_POST['nome']) ? $_POST['nome'] : '');
    $temp_matricula = sanitizeInput(isset($_POST['matricula']) ? $_POST['matricula'] : '');
    $temp_username = sanitizeInput(isset($_POST['username']) ? $_POST['username'] : '');
    $temp_cargo = sanitizeInput(isset($_POST['cargo']) ? $_POST['cargo'] : '');
    $temp_ativo = isset($_POST['ativo']) ? 1 : 0; // Checkbox
    $temp_password = $_POST['password'] ?? '';
    $temp_confirm_password = $_POST['confirm_password'] ?? '';

    // Validação básica de campos obrigatórios
    if (empty($temp_nome) || empty($temp_matricula) || empty($temp_username) || empty($temp_cargo) || empty($temp_password) || empty($temp_confirm_password)) {
        $message = "Todos os campos (incluindo senha) são obrigatórios para um novo cadastro.";
        $message_type = "error";
    } elseif ($temp_password !== $temp_confirm_password) {
        $message = "A senha e a confirmação de senha não coincidem.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // Verifica unicidade do username
            $sql_check_username = "SELECT id FROM operadores WHERE username = ? AND deleted_at IS NULL";
            $result_check = $conn->execute_query($sql_check_username, [$temp_username]);
            if ($result_check && $result_check->num_rows > 0) {
                throw new mysqli_sql_exception("Nome de usuário já existe. Por favor, escolha outro.");
            }

            // Gera o hash da senha
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

            // Insere o novo operador
            $sql_insert = "INSERT INTO operadores (nome, matricula, username, password_hash, cargo, ativo) VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = [
                $temp_nome,
                $temp_matricula,
                $temp_username,
                $hashed_password,
                $temp_cargo,
                $temp_ativo
            ];

            $conn->execute_query($sql_insert, $params);

            $conn->commit();
            $message = "Operador cadastrado com sucesso!";
            $message_type = "success";
            $_POST = array(); // Limpa o formulário

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            // Erro de unicidade pode ser pego aqui também (mas já fizemos a verificação explícita)
            $message = "Erro ao cadastrar Operador (SQL): " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal ao inserir Operador: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

?>

<h2>Cadastrar Novo Operador</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="adicionar.php" method="POST">
    <div class="form-group">
        <label for="nome">Nome Completo:</label>
        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($post_values['nome'] ?? ''); ?>" maxlength="100" required placeholder="Ex: João da Silva">
    </div>

    <div class="form-group">
        <label for="matricula">Matrícula:</label>
        <input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($post_values['matricula'] ?? ''); ?>" maxlength="50" required placeholder="Ex: M12345">
    </div>

    <div class="form-group">
        <label for="username">Usuário (Login):</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($post_values['username'] ?? ''); ?>" maxlength="50" required placeholder="Ex: joao.silva">
    </div>

    <div class="form-group">
        <label for="cargo">Cargo:</label>
        <input type="text" id="cargo" name="cargo" value="<?php echo htmlspecialchars($post_values['cargo'] ?? ''); ?>" maxlength="100" required placeholder="Ex: Operador de Máquina">
    </div>

    <div class="form-group">
        <label for="password">Senha:</label>
        <input type="password" id="password" name="password" maxlength="255" required placeholder="Digite a senha">
    </div>

    <div class="form-group">
        <label for="confirm_password">Confirmar Senha:</label>
        <input type="password" id="confirm_password" name="confirm_password" maxlength="255" required placeholder="Confirme a senha">
    </div>

    <div class="form-group full-width">
        <input type="checkbox" id="ativo" name="ativo" value="1" <?php echo (isset($post_values['ativo']) ? (bool)$post_values['ativo'] : true) ? 'checked' : ''; ?>>
        <label for="ativo" style="display: inline-block; margin-left: 10px;">Operador Ativo</label>
    </div>

    <button type="submit" class="button submit">Cadastrar Operador</button>
</form>

<a href="index.php" class="back-link">Voltar para a lista de Operadores</a>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
