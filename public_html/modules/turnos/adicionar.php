<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDB();
    $nome_turno = sanitizeInput($_POST['nome_turno']);
    $hora_inicio = sanitizeInput($_POST['hora_inicio']);
    $hora_fim = sanitizeInput($_POST['hora_fim']);

    if (!empty($nome_turno) && !empty($hora_inicio) && !empty($hora_fim)) {
        try {
            $sql = "INSERT INTO turnos (nome_turno, hora_inicio, hora_fim) VALUES (?, ?, ?)";
            $conn->execute_query($sql, [$nome_turno, $hora_inicio, $hora_fim]);
            $_SESSION['message'] = "Turno cadastrado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = "Erro ao cadastrar turno: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: adicionar.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- OBSERVAÇÃO DE ESTILO: Adicionada regra de CSS para garantir a mesma altura para todos os campos do formulário -->
<style>
    .form-group input[type="text"],
    .form-group input[type="time"],
    .form-group input[type="date"],
    .form-group input[type="datetime-local"],
    .form-group input[type="number"],
    .form-group select {
        height: 38px; /* Altura padrão para consistência */
        box-sizing: border-box; /* Garante que padding e border não alterem a altura final */
    }
</style>

<div class="container mt-4">
    <h2><i class="fas fa-plus-circle"></i> Adicionar Novo Turno</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="adicionar.php" method="POST">
        <div class="form-group full-width">
            <label for="nome_turno">Nome do Turno*</label>
            <input type="text" name="nome_turno" required>
        </div>
        <div class="form-group">
            <label for="hora_inicio">Hora de Início*</label>
            <input type="time" name="hora_inicio" required>
        </div>
        <div class="form-group">
            <label for="hora_fim">Hora de Fim*</label>
            <input type="time" name="hora_fim" required>
        </div>
        <div class="full-width" style="text-align: center; grid-column: 1 / -1;">
            <button type="submit" class="button submit">Salvar Turno</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
