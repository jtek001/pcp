<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();
$etapa_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$etapa = null;

if (!$etapa_id) {
    $_SESSION['message'] = "ID da etapa inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php"); // Redireciona para o index dos roteiros
    exit();
}

// Lógica para processar a atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roteiro_id = filter_input(INPUT_POST, 'roteiro_id', FILTER_VALIDATE_INT);
    $sequencia = filter_input(INPUT_POST, 'sequencia', FILTER_VALIDATE_INT);
    $centro_trabalho_id = filter_input(INPUT_POST, 'centro_trabalho_id', FILTER_VALIDATE_INT);
    $descricao_operacao = sanitizeInput($_POST['descricao_operacao']);
    $tempo_setup = filter_input(INPUT_POST, 'tempo_setup_min', FILTER_VALIDATE_FLOAT);
    $tempo_producao = filter_input(INPUT_POST, 'tempo_producao_min', FILTER_VALIDATE_FLOAT);

    if ($roteiro_id && $sequencia && $centro_trabalho_id && !empty($descricao_operacao)) {
        try {
            $sql = "UPDATE roteiro_etapas SET sequencia = ?, centro_trabalho_id = ?, descricao_operacao = ?, tempo_setup_min = ?, tempo_producao_min = ? WHERE id = ?";
            $conn->execute_query($sql, [$sequencia, $centro_trabalho_id, $descricao_operacao, $tempo_setup, $tempo_producao, $etapa_id]);
            $_SESSION['message'] = "Etapa atualizada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: etapas.php?roteiro_id=" . $roteiro_id);
            exit();
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = "Erro ao atualizar etapa: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos marcados com * são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: editar_etapa.php?id=" . $etapa_id);
    exit();
}

// Busca os dados da etapa para preencher o formulário
try {
    $sql_get = "SELECT * FROM roteiro_etapas WHERE id = ? AND deleted_at IS NULL";
    $etapa = $conn->execute_query($sql_get, [$etapa_id])->fetch_assoc();
    if (!$etapa) {
        $_SESSION['message'] = "Etapa do roteiro não encontrada.";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "Erro ao carregar dados da etapa: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Busca máquinas para o dropdown
$maquinas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Editar Etapa do Roteiro</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if ($etapa): ?>
    <form action="editar_etapa.php?id=<?php echo $etapa['id']; ?>" method="POST">
        <input type="hidden" name="roteiro_id" value="<?php echo $etapa['roteiro_id']; ?>">

        <div class="form-group">
            <label for="sequencia">Sequência*</label>
            <input type="number" name="sequencia" class="form-control" value="<?php echo htmlspecialchars($etapa['sequencia']); ?>" required>
        </div>
        <div class="form-group">
            <label for="centro_trabalho_id">Máquina*</label>
            <select name="centro_trabalho_id" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach($maquinas as $maquina): ?>
                    <option value="<?php echo $maquina['id']; ?>" <?php echo ($etapa['centro_trabalho_id'] == $maquina['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($maquina['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group full-width">
            <label for="descricao_operacao">Descrição da Operação*</label>
            <input type="text" name="descricao_operacao" class="form-control" value="<?php echo htmlspecialchars($etapa['descricao_operacao']); ?>" required>
        </div>
        <div class="form-group">
            <label for="tempo_setup_min">Tempo Setup (min)</label>
            <input type="number" name="tempo_setup_min" class="form-control" step="0.01" value="<?php echo htmlspecialchars($etapa['tempo_setup_min']); ?>">
        </div>
        <div class="form-group">
            <label for="tempo_producao_min">Tempo Produção (min/un)</label>
            <input type="number" name="tempo_producao_min" class="form-control" step="0.01" value="<?php echo htmlspecialchars($etapa['tempo_producao_min']); ?>">
        </div>
        <div class="form-group full-width" style="text-align:center;">
            <button type="submit" class="button submit">Atualizar Etapa</button>
        </div>
    </form>
    <?php endif; ?>
    <a href="etapas.php?roteiro_id=<?php echo $etapa['roteiro_id']; ?>" class="back-link">Voltar para as Etapas</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
ob_end_flush();
?>
