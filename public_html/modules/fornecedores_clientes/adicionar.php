<?php
// modules/fornecedores_clientes/adicionar.php
// Esta página contém o formulário para adicionar um novo fornecedor ou cliente.

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

// Variáveis para mensagens de sucesso/erro - GARANTIDAS DE SEREM INICIALIZADAS
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
    $temp_tipo = sanitizeInput(isset($_POST['tipo']) ? $_POST['tipo'] : '');
    $temp_cnpj = sanitizeInput(isset($_POST['cnpj']) ? $_POST['cnpj'] : '');
    $temp_contato = sanitizeInput(isset($_POST['contato']) ? $_POST['contato'] : '');
    $temp_email = sanitizeInput(isset($_POST['email']) ? $_POST['email'] : '');
    $temp_telefone = sanitizeInput(isset($_POST['telefone']) ? $_POST['telefone'] : '');
    $temp_endereco = sanitizeInput(isset($_POST['endereco']) ? $_POST['endereco'] : '');
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');

    // Validação básica de campos obrigatórios
    if (empty($temp_nome) || empty($temp_tipo)) {
        $message = "Nome e Tipo são campos obrigatórios.";
        $message_type = "error";
    } elseif (!in_array($temp_tipo, ['fornecedor', 'cliente', 'ambos'])) {
        $message = "Tipo inválido. Selecione 'Fornecedor', 'Cliente' ou 'Ambos'.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // Insere o novo fornecedor/cliente
            $sql_insert = "INSERT INTO fornecedores_clientes_lookup (nome, tipo, cnpj, contato, email, telefone, endereco, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $temp_nome,
                $temp_tipo,
                empty($temp_cnpj) ? NULL : $temp_cnpj, // Armazena NULL se CNPJ for vazio
                empty($temp_contato) ? NULL : $temp_contato,
                empty($temp_email) ? NULL : $temp_email,
                empty($temp_telefone) ? NULL : $temp_telefone,
                empty($temp_endereco) ? NULL : $temp_endereco,
                empty($temp_observacoes) ? NULL : $temp_observacoes
            ];

            $conn->execute_query($sql_insert, $params);

            $conn->commit();
            $message = "Cadastro de " . ucfirst($temp_tipo) . " realizado com sucesso!";
            $message_type = "success";
            $_POST = array(); // Limpa o formulário

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            // Erro de duplicidade na restrição UNIQUE (nome ou cnpj)
            if ($conn->errno == 1062) { 
                $message = "Erro: Já existe um cadastro com este Nome ou CNPJ.";
            } else {
                $message = "Erro ao cadastrar " . ucfirst($temp_tipo) . " (SQL): " . $e->getMessage();
            }
            $message_type = "error";
            error_log("Erro fatal ao inserir Fornecedor/Cliente: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

?>

<h2>Cadastro de Fornecedor / Cliente</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="adicionar.php" method="POST">
    <div class="form-group">
        <label for="nome">Nome / Razão Social:</label>
        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($post_values['nome'] ?? ''); ?>" maxlength="100" required placeholder="Ex: ABC Indústria e Comércio Ltda.">
    </div>

    <div class="form-group">
        <label for="tipo">Tipo:</label>
        <select id="tipo" name="tipo" required>
            <option value="">Selecione o Tipo</option>
            <option value="fornecedor" <?php echo (isset($post_values['tipo']) && $post_values['tipo'] == 'fornecedor') ? 'selected' : ''; ?>>Fornecedor</option>
            <option value="cliente" <?php echo (isset($post_values['tipo']) && $post_values['tipo'] == 'cliente') ? 'selected' : ''; ?>>Cliente</option>
            <option value="ambos" <?php echo (isset($post_values['tipo']) && $post_values['tipo'] == 'ambos') ? 'selected' : ''; ?>>Ambos</option>
        </select>
    </div>

    <div class="form-group">
        <label for="cnpj">CNPJ:</label>
        <input type="text" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($post_values['cnpj'] ?? ''); ?>" maxlength="18" placeholder="Ex: 00.000.000/0000-00">
    </div>

    <div class="form-group">
        <label for="contato">Nome do Contato:</label>
        <input type="text" id="contato" name="contato" value="<?php echo htmlspecialchars($post_values['contato'] ?? ''); ?>" maxlength="100" placeholder="Ex: João da Silva">
    </div>

    <div class="form-group">
        <label for="email">E-mail:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($post_values['email'] ?? ''); ?>" maxlength="100" placeholder="Ex: contato@empresa.com">
    </div>

    <div class="form-group">
        <label for="telefone">Telefone:</label>
        <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($post_values['telefone'] ?? ''); ?>" maxlength="20" placeholder="Ex: (XX) XXXX-XXXX">
    </div>

    <div class="form-group full-width">
        <label for="endereco">Endereço Completo:</label>
        <textarea id="endereco" name="endereco" placeholder="Rua, número, bairro, cidade, estado, CEP..."><?php echo htmlspecialchars($post_values['endereco'] ?? ''); ?></textarea>
    </div>

    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes" placeholder="Informações adicionais sobre este fornecedor/cliente..."><?php echo htmlspecialchars($post_values['observacoes'] ?? ''); ?></textarea>
    </div>

    <button type="submit" class="button submit">Cadastrar</button>
</form>

<a href="index.php" class="back-link">Voltar para a lista de Fornecedores/Clientes</a>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
