<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();
$roteiro_id = filter_input(INPUT_GET, 'roteiro_id', FILTER_VALIDATE_INT);

if (!$roteiro_id) {
    $_SESSION['message'] = "ID do roteiro inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Lógica para adicionar uma nova etapa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_etapa'])) {
    $sequencia = filter_input(INPUT_POST, 'sequencia', FILTER_VALIDATE_INT);
    $centro_trabalho_id = filter_input(INPUT_POST, 'centro_trabalho_id', FILTER_VALIDATE_INT);
    $descricao_operacao = sanitizeInput($_POST['descricao_operacao']);
    $tempo_setup = filter_input(INPUT_POST, 'tempo_setup_min', FILTER_VALIDATE_FLOAT);
    $tempo_producao = filter_input(INPUT_POST, 'tempo_producao_min', FILTER_VALIDATE_FLOAT);

    if ($sequencia && $centro_trabalho_id && !empty($descricao_operacao)) {
        try {
            $sql = "INSERT INTO roteiro_etapas (roteiro_id, sequencia, centro_trabalho_id, descricao_operacao, tempo_setup_min, tempo_producao_min) VALUES (?, ?, ?, ?, ?, ?)";
            $conn->execute_query($sql, [$roteiro_id, $sequencia, $centro_trabalho_id, $descricao_operacao, $tempo_setup, $tempo_producao]);
            $_SESSION['message'] = "Etapa adicionada com sucesso!";
            $_SESSION['message_type'] = "success";
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = "Erro ao adicionar etapa: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos marcados com * são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: etapas.php?roteiro_id=" . $roteiro_id);
    exit();
}

// Lógica para excluir uma etapa
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $etapa_id = (int)$_GET['delete_id'];
    try {
        // Usando soft delete
        $sql_delete = "UPDATE roteiro_etapas SET deleted_at = NOW() WHERE id = ?";
        $conn->execute_query($sql_delete, [$etapa_id]);
        $_SESSION['message'] = "Etapa excluída com sucesso.";
        $_SESSION['message_type'] = "success";
    } catch (mysqli_sql_exception $e) {
        $_SESSION['message'] = "Erro ao excluir etapa: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: etapas.php?roteiro_id=" . $roteiro_id);
    exit();
}

// Busca os detalhes do roteiro e do produto associado
$sql_roteiro = "SELECT r.id, r.descricao, p.nome as produto_nome, p.codigo as produto_codigo 
                FROM roteiros r 
                JOIN produtos p ON r.produto_id = p.id 
                WHERE r.id = ?";
$roteiro_details = $conn->execute_query($sql_roteiro, [$roteiro_id])->fetch_assoc();

if (!$roteiro_details) {
    die("Roteiro não encontrado.");
}

// Busca as etapas existentes para este roteiro
$sql_etapas = "SELECT re.*, m.nome as maquina_nome 
               FROM roteiro_etapas re
               JOIN maquinas m ON re.centro_trabalho_id = m.id
               WHERE re.roteiro_id = ? AND re.deleted_at IS NULL
               ORDER BY re.sequencia ASC";
$etapas = $conn->execute_query($sql_etapas, [$roteiro_id])->fetch_all(MYSQLI_ASSOC);

// Busca máquinas para o dropdown do formulário
$maquinas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2><i class="fas fa-sitemap"></i> Etapas do Roteiro</h2>
        <a href="index.php" class="button">Voltar para Roteiros</a>
    </div>

    <div class="alert alert-secondary">
        <strong>Produto:</strong> <?php echo htmlspecialchars($roteiro_details['produto_nome'] . ' (' . $roteiro_details['produto_codigo'] . ')'); ?><br>
        <strong>Roteiro:</strong> <?php echo htmlspecialchars($roteiro_details['descricao']); ?>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Formulário para Adicionar Nova Etapa -->
    <div class="card mb-4">
        <div class="card-header">
            Adicionar Nova Etapa
        </div>
        <div class="card-body">
            <!-- ALTERAÇÃO: Formulário agora usa a estrutura de form-group -->
            <form action="etapas.php?roteiro_id=<?php echo $roteiro_id; ?>" method="POST">
                <input type="hidden" name="add_etapa" value="1">

                <div class="form-group">
                    <label for="sequencia">Sequência*</label>
                    <input type="number" name="sequencia" class="form-control" placeholder="Ex: 10" required>
                </div>
                <div class="form-group">
                    <label for="centro_trabalho_id">Máquina*</label>
                    <select name="centro_trabalho_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach($maquinas as $maquina): ?>
                            <option value="<?php echo $maquina['id']; ?>"><?php echo htmlspecialchars($maquina['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label for="descricao_operacao">Descrição da Operação*</label>
                    <input type="text" name="descricao_operacao" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="tempo_setup_min">Tempo Setup (min)</label>
                    <input type="number" name="tempo_setup_min" class="form-control" step="0.01" value="0.00">
                </div>
                <div class="form-group">
                    <label for="tempo_producao_min">Tempo Produção (min/un)</label>
                    <input type="number" name="tempo_producao_min" class="form-control" step="0.01" value="0.00">
                </div>
                <div class="form-group full-width" style="text-align:center;">
                    <button type="submit" class="button add">Adicionar Etapa</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Etapas Existentes -->
    <div class="card">
        <div class="card-header">
            Etapas Cadastradas
        </div>
        <div class="card-body">
            <?php if (!empty($etapas)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Seq.</th>
                        <th>Máquina</th>
                        <th>Operação</th>
                        <th class="text-end">T. Setup (min)</th>
                        <th class="text-end">T. Produção (min)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etapas as $etapa): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($etapa['sequencia']); ?></td>
                        <td><?php echo htmlspecialchars($etapa['maquina_nome']); ?></td>
                        <td><?php echo htmlspecialchars($etapa['descricao_operacao']); ?></td>
                        <td class="text-end"><?php echo number_format($etapa['tempo_setup_min'], 2, ',', '.'); ?></td>
                        <td class="text-end"><?php echo number_format($etapa['tempo_producao_min'], 2, ',', '.'); ?></td>
                        <td>
                            <a href="editar_etapa.php?id=<?php echo $etapa['id']; ?>" class="button edit small">Editar</a>
                            <a href="etapas.php?roteiro_id=<?php echo $roteiro_id; ?>&delete_id=<?php echo $etapa['id']; ?>" class="button delete small" onclick="return confirm('Tem certeza que deseja excluir esta etapa?');">Excluir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p class="text-center">Nenhuma etapa cadastrada para este roteiro ainda.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
ob_end_flush();
?>
