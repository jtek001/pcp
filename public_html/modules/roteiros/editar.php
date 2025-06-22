<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$roteiro_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$roteiro = null;

if (!$roteiro_id) {
    $_SESSION['message'] = "ID do roteiro inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Lógica para processar a atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao = sanitizeInput($_POST['descricao']);
    $ativo = filter_input(INPUT_POST, 'ativo', FILTER_VALIDATE_INT);

    if (!empty($descricao)) {
        try {
            $sql = "UPDATE roteiros SET descricao = ?, ativo = ? WHERE id = ?";
            $conn->execute_query($sql, [$descricao, $ativo, $roteiro_id]);
            $_SESSION['message'] = "Roteiro atualizado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = "Erro ao atualizar roteiro: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "O campo Descrição é obrigatório.";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: editar.php?id=" . $roteiro_id);
    exit();
}

// Busca os dados do roteiro para preencher o formulário
try {
    $sql_get = "SELECT r.*, p.nome as produto_nome, p.codigo as produto_codigo 
                FROM roteiros r
                JOIN produtos p ON r.produto_id = p.id
                WHERE r.id = ? AND r.deleted_at IS NULL";
    $roteiro = $conn->execute_query($sql_get, [$roteiro_id])->fetch_assoc();
    if (!$roteiro) {
        $_SESSION['message'] = "Roteiro não encontrado.";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao carregar dados do roteiro: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Editar Roteiro de Produção</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if ($roteiro): ?>
    <div class="alert alert-secondary">
        <strong>Produto:</strong> <?php echo htmlspecialchars($roteiro['produto_nome'] . ' (' . $roteiro['produto_codigo'] . ')'); ?>
    </div>
    
    <form action="editar.php?id=<?php echo $roteiro['id']; ?>" method="POST">
        <div class="form-group full-width">
            <label for="descricao">Descrição do Roteiro*</label>
            <input type="text" name="descricao" id="descricao" value="<?php echo htmlspecialchars($roteiro['descricao']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="ativo">Status</label>
            <select name="ativo" id="ativo" required>
                <option value="1" <?php echo ($roteiro['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                <option value="0" <?php echo ($roteiro['ativo'] == 0) ? 'selected' : ''; ?>>Inativo</option>
            </select>
        </div>

        <div class="form-group full-width" style="text-align:center;">
            <button type="submit" class="button submit">Atualizar Roteiro</button>
        </div>
    </form>
    <?php endif; ?>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
ob_end_flush();
?>
