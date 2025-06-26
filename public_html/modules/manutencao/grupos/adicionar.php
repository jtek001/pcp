<?php
ob_start();
session_start();
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDB();
    $nome_grupo = sanitizeInput($_POST['nome_grupo']);
    $descricao = sanitizeInput($_POST['descricao']);

    if (!empty($nome_grupo)) {
        try {
            $sql = "INSERT INTO grupos_maquinas (nome_grupo, descricao) VALUES (?, ?)";
            $conn->execute_query($sql, [$nome_grupo, $descricao]);
            $_SESSION['message'] = "Grupo de máquinas cadastrado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (mysqli_sql_exception $e) {
            if ($conn->errno == 1062) { // Erro de entrada duplicada
                $_SESSION['message'] = "Erro: Já existe um grupo com este nome.";
            } else {
                $_SESSION['message'] = "Erro ao cadastrar grupo: " . $e->getMessage();
            }
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "O nome do grupo é obrigatório.";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: adicionar.php");
    exit();
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-plus-circle"></i> Adicionar Novo Grupo de Máquinas</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="adicionar.php" method="POST">
        <div class="form-group">
            <label for="nome_grupo">Nome do Grupo*</label>
            <input type="text" name="nome_grupo" id="nome_grupo" required>
        </div>
        <div class="form-group full-width">
            <label for="descricao">Descrição</label>
            <textarea name="descricao" id="descricao" rows="3"></textarea>
        </div>
        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Salvar Grupo</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
ob_end_flush();
?>
