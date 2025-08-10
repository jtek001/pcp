<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$turno_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$turno_id) {
    header("Location: index.php");
    exit();
}

// Lógica de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_turno = sanitizeInput($_POST['nome_turno']);
    $hora_inicio = sanitizeInput($_POST['hora_inicio']);
    $hora_fim = sanitizeInput($_POST['hora_fim']);

    if (!empty($nome_turno) && !empty($hora_inicio) && !empty($hora_fim)) {
        try {
            $sql = "UPDATE turnos SET nome_turno = ?, hora_inicio = ?, hora_fim = ? WHERE id = ?";
            $conn->execute_query($sql, [$nome_turno, $hora_inicio, $hora_fim, $turno_id]);
            $_SESSION['message'] = "Turno atualizado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = "Erro ao atualizar turno: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: editar.php?id=" . $turno_id);
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

// Busca os dados do turno para preencher o formulário
$turno = $conn->execute_query("SELECT * FROM turnos WHERE id = ? AND deleted_at IS NULL", [$turno_id])->fetch_assoc();
if (!$turno) {
    die("Turno não encontrado.");
}
?>

<!-- Estilo para garantir a mesma altura para todos os campos do formulário -->
<style>
    .form-group input[type="text"],
    .form-group input[type="time"] {
        height: 38px;
        box-sizing: border-box;
    }
</style>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Editar Turno</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="editar.php?id=<?php echo $turno['id']; ?>" method="POST">
        <div class="form-group full-width">
            <label for="nome_turno">Nome do Turno*</label>
            <input type="text" name="nome_turno" value="<?php echo htmlspecialchars($turno['nome_turno']); ?>" required>
        </div>
        <div class="form-group">
            <label for="hora_inicio">Hora de Início*</label>
            <input type="time" name="hora_inicio" value="<?php echo htmlspecialchars($turno['hora_inicio']); ?>" required>
        </div>
        <div class="form-group">
            <label for="hora_fim">Hora de Fim*</label>
            <input type="time" name="hora_fim" value="<?php echo htmlspecialchars($turno['hora_fim']); ?>" required>
        </div>
        <div class="full-width" style="text-align: center; grid-column: 1 / -1;">
            <button type="submit" class="button submit">Salvar Alterações</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
