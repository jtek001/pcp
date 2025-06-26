<?php
ob_start();
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/header.php';

$conn = connectDB();
$grupo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$grupo_id) {
    header("Location: index.php");
    exit();
}

// Lógica de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_grupo = sanitizeInput($_POST['nome_grupo']);
    $descricao = sanitizeInput($_POST['descricao']);

    if (!empty($nome_grupo)) {
        try {
            $sql = "UPDATE grupos_maquinas SET nome_grupo = ?, descricao = ? WHERE id = ?";
            $conn->execute_query($sql, [$nome_grupo, $descricao, $grupo_id]);
            $_SESSION['message'] = "Grupo de máquinas atualizado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = "Erro ao atualizar grupo: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "O nome do grupo é obrigatório.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: editar.php?id=" . $grupo_id);
    exit();
}

// Busca os dados do grupo
$grupo = $conn->execute_query("SELECT * FROM grupos_maquinas WHERE id = ? AND deleted_at IS NULL", [$grupo_id])->fetch_assoc();
if (!$grupo) {
    die("Grupo de máquinas não encontrado.");
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Editar Grupo de Máquinas</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="editar.php?id=<?php echo $grupo['id']; ?>" method="POST">
        <div class="form-group">
            <label for="nome_grupo">Nome do Grupo*</label>
            <input type="text" name="nome_grupo" id="nome_grupo" value="<?php echo htmlspecialchars($grupo['nome_grupo']); ?>" required>
        </div>
        <div class="form-group full-width">
            <label for="descricao">Descrição</label>
            <textarea name="descricao" id="descricao" rows="3"><?php echo htmlspecialchars($grupo['descricao']); ?></textarea>
        </div>
        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Salvar Alterações</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
ob_end_flush();
?>
