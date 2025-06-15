<?php
// modules/operadores/editar.php
// Esta página contém o formulário para editar um operador existente.

// Inicia a sessão para usar variáveis de sessão (necessário para mensagens)
session_start();

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

// Variáveis para mensagens de sucesso/erro
$message = '';
$message_type = '';
$operador_data = null; // Para armazenar os dados do operador a ser editado

// Pega o ID do operador da URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Se o ID for inválido, redireciona
if ($id <= 0) {
    header("Location: " . BASE_URL . "/modules/operadores/index.php?message=" . urlencode("ID de Operador inválido para edição.") . "&type=error");
    exit();
}

// --- Lógica para buscar os dados do operador para preencher o formulário ---
// NOTA: A coluna 'cargo' é esperada aqui. Certifique-se de que ela exista no banco de dados.
$sql_select = "SELECT id, nome, matricula, username, cargo, ativo FROM operadores WHERE id = ? AND deleted_at IS NULL";
try {
    $result_operador_data = $conn->execute_query($sql_select, [$id]);
    if ($result_operador_data) {
        $operador_data = $result_operador_data->fetch_assoc();
        $result_operador_data->free();
        if (!$operador_data) { // Se não encontrou o operador
            $message = "Operador não encontrado para edição.";
            $message_type = "error";
        }
    } else {
        $message = "Erro ao carregar dados do Operador: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar dados do Operador: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro fatal ao carregar dados do Operador (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar dados do Operador: " . $e->getMessage());
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0 && $operador_data) {
    // Sanitiza e valida as entradas
    $temp_nome = sanitizeInput(isset($_POST['nome']) ? $_POST['nome'] : '');
    $temp_matricula = sanitizeInput(isset($_POST['matricula']) ? $_POST['matricula'] : '');
    $temp_username = sanitizeInput(isset($_POST['username']) ? $_POST['username'] : '');
    $temp_cargo = sanitizeInput(isset($_POST['cargo']) ? $_POST['cargo'] : '');
    $temp_ativo = isset($_POST['ativo']) ? 1 : 0; // Checkbox
    $temp_password = $_POST['password'] ?? '';
    $temp_confirm_password = $_POST['confirm_password'] ?? '';

    // Validação básica de campos obrigatórios
    if (empty($temp_nome) || empty($temp_matricula) || empty($temp_username) || empty($temp_cargo)) {
        $message = "Nome, Matrícula, Usuário e Cargo são campos obrigatórios.";
        $message_type = "error";
    } elseif (!empty($temp_password) && $temp_password !== $temp_confirm_password) {
        $message = "A senha e a confirmação de senha não coincidem.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // Verifica unicidade do username (apenas se for diferente do original)
            if ($temp_username !== $operador_data['username']) {
                $sql_check_username = "SELECT id FROM operadores WHERE username = ? AND deleted_at IS NULL AND id != ?";
                $result_check = $conn->execute_query($sql_check_username, [$temp_username, $id]);
                if ($result_check && $result_check->num_rows > 0) {
                    throw new mysqli_sql_exception("Nome de usuário já existe. Por favor, escolha outro.");
                }
            }

            // Constrói a parte do SQL para atualização de senha
            $password_update_sql = "";
            $password_params = [];
            if (!empty($temp_password)) {
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                $password_update_sql = ", password_hash = ?";
                $password_params[] = $hashed_password;
            }

            // Prepara a consulta SQL para atualização do operador
            // NOTA: A coluna 'cargo' é atualizada aqui.
            $sql_update = "UPDATE operadores SET nome = ?, matricula = ?, username = ?, cargo = ?, ativo = ?{$password_update_sql} WHERE id = ?";
            
            // Array de parâmetros para execute_query (ordem importa!)
            $params = array_merge(
                [$temp_nome, $temp_matricula, $temp_username, $temp_cargo, $temp_ativo],
                $password_params, // Adiciona o hash da senha, se houver
                [$id] // ID do registro a ser atualizado
            );

            $conn->execute_query($sql_update, $params);

            $conn->commit();
            $message = "Operador atualizado com sucesso!";
            $message_type = "success";
            
            // Recarrega os dados do operador após o sucesso para refletir na tela
            $result_operador_data_reloaded = $conn->execute_query($sql_select, [$id]);
            if ($result_operador_data_reloaded) {
                $operador_data = $result_operador_data_reloaded->fetch_assoc();
                $result_operador_data_reloaded->free();
            }

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            // Erro de unicidade pode ser pego aqui também (mas já fizemos a verificação explícita)
            $message = "Erro ao atualizar Operador (SQL): " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal ao atualizar Operador: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

?>

<h2>Editar Operador</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($operador_data): ?>
    <form action="editar.php?id=<?php echo $operador_data['id']; ?>" method="POST">
        <div class="form-group">
            <label for="nome">Nome Completo:</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($post_values['nome'] ?? $operador_data['nome']); ?>" maxlength="100" required placeholder="Ex: João da Silva">
        </div>

        <div class="form-group">
            <label for="matricula">Matrícula:</label>
            <input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($post_values['matricula'] ?? $operador_data['matricula']); ?>" maxlength="50" required placeholder="Ex: M12345">
        </div>

        <div class="form-group">
            <label for="username">Usuário (Login):</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($post_values['username'] ?? $operador_data['username']); ?>" maxlength="50" required placeholder="Ex: joao.silva">
        </div>

        <div class="form-group">
            <label for="cargo">Cargo:</label>
            <input type="text" id="cargo" name="cargo" value="<?php echo htmlspecialchars($post_values['cargo'] ?? $operador_data['cargo']); ?>" maxlength="100" required placeholder="Ex: Operador de Máquina">
        </div>

        <div class="form-group">
            <label for="password">Nova Senha (deixe em branco para não alterar):</label>
            <input type="password" id="password" name="password" maxlength="255" placeholder="*******">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirmar Nova Senha:</label>
            <input type="password" id="confirm_password" name="confirm_password" maxlength="255" placeholder="*******">
        </div>

        <div class="form-group full-width">
            <input type="checkbox" id="ativo" name="ativo" value="1" <?php echo (isset($post_values['ativo']) ? (bool)$post_values['ativo'] : (bool)$operador_data['ativo']) ? 'checked' : ''; ?>>
            <label for="ativo" style="display: inline-block; margin-left: 10px;">Operador Ativo</label>
        </div>

        <button type="submit" class="button submit">Atualizar Operador</button>
    </form>
<?php else: ?>
    <p class="error" style="text-align: center;">Operador não encontrado para edição.</p>
<?php endif; ?>

<a href="index.php" class="back-link">Voltar para a lista de Operadores</a>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
