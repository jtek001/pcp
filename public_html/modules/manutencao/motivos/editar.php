<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$conn = connectDB();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$motivo = null;

if (!$id) {
    $_SESSION['message'] = "ID do motivo inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Lógica para processar a atualização do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = sanitizeInput($_POST['codigo']);
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $grupo = sanitizeInput($_POST['grupo']);

    if (!empty($codigo) && !empty($nome)) {
        try {
            $sql = "UPDATE motivos_parada SET codigo = ?, nome = ?, descricao = ?, grupo = ? WHERE id = ?";
            $conn->execute_query($sql, [$codigo, $nome, $descricao, $grupo, $id]);
            $_SESSION['message'] = "Motivo de parada atualizado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (mysqli_sql_exception $e) {
            if ($conn->errno == 1062) {
                $_SESSION['message'] = "Erro: Já existe outro motivo com este código.";
            } else {
                $_SESSION['message'] = "Erro ao atualizar motivo: " . $e->getMessage();
            }
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Código e Nome são campos obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    // Em caso de erro, redireciona para a mesma página para exibir a mensagem
    header("Location: editar.php?id=" . $id);
    exit();
}

// Busca os dados do motivo para preencher o formulário
try {
    $sql_get = "SELECT * FROM motivos_parada WHERE id = ? AND deleted_at IS NULL";
    $motivo = $conn->execute_query($sql_get, [$id])->fetch_assoc();
    if (!$motivo) {
        $_SESSION['message'] = "Motivo de parada não encontrado.";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao carregar os dados do motivo: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// O header é incluído aqui, antes do HTML começar
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Editar Motivo de Parada</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if ($motivo): ?>
    <form action="editar.php?id=<?php echo $motivo['id']; ?>" method="POST">
        <div class="form-group">
            <label for="codigo">Código</label>
            <input type="text" name="codigo" id="codigo" value="<?php echo htmlspecialchars($motivo['codigo']); ?>" required>
        </div>
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" name="nome" id="nome" value="<?php echo htmlspecialchars($motivo['nome']); ?>" required>
        </div>
        <div class="form-group">
            <label for="grupo">Grupo</label>
            <input type="text" name="grupo" id="grupo" value="<?php echo htmlspecialchars($motivo['grupo']); ?>" placeholder="Ex: Elétrica, Mecânica, Operacional">
        </div>
        <div class="form-group full-width">
            <label for="descricao">Descrição</label>
            <textarea name="descricao" id="descricao" rows="3"><?php echo htmlspecialchars($motivo['descricao']); ?></textarea>
        </div>
        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Atualizar Motivo</button>
        </div>
    </form>
    <?php endif; ?>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
// CORREÇÃO: O footer agora é incluído no final do arquivo.
require_once __DIR__ . '/../../../includes/footer.php';
?>
