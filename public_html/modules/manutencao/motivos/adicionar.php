<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// OBSERVAÇÃO: A lógica de processamento do formulário foi movida para o topo do ficheiro.
// Isto garante que o redirecionamento (header) funcione corretamente antes de qualquer HTML ser enviado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDB();
    $codigo = sanitizeInput($_POST['codigo']);
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $grupo = sanitizeInput($_POST['grupo']);

    if (!empty($codigo) && !empty($nome)) {
        try {
            $sql = "INSERT INTO motivos_parada (codigo, nome, descricao, grupo) VALUES (?, ?, ?, ?)";
            $conn->execute_query($sql, [$codigo, $nome, $descricao, $grupo]);
            $_SESSION['message'] = "Motivo de parada cadastrado com sucesso!";
            $_SESSION['message_type'] = "success";
        } catch (mysqli_sql_exception $e) {
            if ($conn->errno == 1062) {
                $_SESSION['message'] = "Erro: Já existe um motivo com este código.";
            } else {
                $_SESSION['message'] = "Erro ao cadastrar motivo: " . $e->getMessage();
            }
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Código e Nome são campos obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    // CORREÇÃO: Redireciona sempre de volta para a página de adicionar para mostrar a mensagem.
    header("Location: adicionar.php");
    exit();
}

// O cabeçalho agora é incluído depois da lógica de processamento
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-plus-circle"></i> Adicionar Motivo de Parada</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="adicionar.php" method="POST">
        <div class="form-group">
            <label for="codigo">Código</label>
            <input type="text" name="codigo" id="codigo" required>
        </div>
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" name="nome" id="nome" required>
        </div>
        <div class="form-group">
            <label for="grupo">Grupo</label>
            <input type="text" name="grupo" id="grupo" placeholder="Ex: Elétrica, Mecânica, Operacional">
        </div>
        <div class="form-group full-width">
            <label for="descricao">Descrição</label>
            <textarea name="descricao" id="descricao" rows="3"></textarea>
        </div>
        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Salvar Motivo</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
