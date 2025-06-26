<?php
ob_start();
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/header.php';

$conn = connectDB();
$grupo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$grupo_id) {
    $_SESSION['message'] = "ID do grupo inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Lógica para adicionar ou remover máquinas do grupo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_maquina') {
        $maquina_id = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT);
        if ($maquina_id) {
            try {
                $sql = "INSERT INTO maquina_grupo_associacao (grupo_id, maquina_id) VALUES (?, ?)";
                $conn->execute_query($sql, [$grupo_id, $maquina_id]);
                $_SESSION['message'] = "Máquina adicionada ao grupo com sucesso!";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['message'] = "Erro ao adicionar máquina: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        }
    } elseif ($action === 'remove_maquina') {
        $maquina_id = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT);
        if ($maquina_id) {
            try {
                $sql = "DELETE FROM maquina_grupo_associacao WHERE grupo_id = ? AND maquina_id = ?";
                $conn->execute_query($sql, [$grupo_id, $maquina_id]);
                $_SESSION['message'] = "Máquina removida do grupo com sucesso!";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['message'] = "Erro ao remover máquina: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        }
    }
    header("Location: gerir_maquinas.php?id=" . $grupo_id);
    exit();
}


// Busca os detalhes do grupo
$grupo_details = $conn->execute_query("SELECT * FROM grupos_maquinas WHERE id = ?", [$grupo_id])->fetch_assoc();
if (!$grupo_details) die("Grupo não encontrado.");

// Busca as máquinas que já estão neste grupo
$sql_maquinas_no_grupo = "SELECT m.id, m.nome FROM maquinas m JOIN maquina_grupo_associacao mga ON m.id = mga.maquina_id WHERE mga.grupo_id = ? ORDER BY m.nome";
$maquinas_no_grupo = $conn->execute_query($sql_maquinas_no_grupo, [$grupo_id])->fetch_all(MYSQLI_ASSOC);

// Busca as máquinas que ainda não pertencem a NENHUM grupo para adicionar
$sql_maquinas_disponiveis = "SELECT id, nome FROM maquinas WHERE id NOT IN (SELECT maquina_id FROM maquina_grupo_associacao)";
$maquinas_disponiveis = $conn->query($sql_maquinas_disponiveis)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-cogs"></i> Gerir Máquinas no Grupo: <?php echo htmlspecialchars($grupo_details['nome_grupo']); ?></h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Coluna para adicionar máquinas -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Adicionar Máquina ao Grupo</div>
                <div class="card-body">
                    <form action="gerir_maquinas.php?id=<?php echo $grupo_id; ?>" method="POST" class="addmaquina">
                        <input type="hidden" name="action" value="add_maquina">
                        <div class="form-group">
                            <label for="maquina_id">Máquinas Disponíveis</label>
                            <select name="maquina_id" id="maquina_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach($maquinas_disponiveis as $maquina): ?>
                                    <option value="<?php echo $maquina['id']; ?>"><?php echo htmlspecialchars($maquina['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="button add mt-2">Adicionar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Coluna para listar máquinas no grupo -->
        <div class="col-md-7">
             <div class="card">
                <div class="card-header">Máquinas Neste Grupo</div>
                <div class="card-body">
                    <?php if (!empty($maquinas_no_grupo)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome da Máquina</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($maquinas_no_grupo as $maquina): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($maquina['nome']); ?></td>
                                    <td class="text-end">
                                        <button type="button" class="button delete small" onclick="removerMaquina(<?php echo $maquina['id']; ?>)">Remover</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Nenhuma mquina associada a este grupo ainda.</p>
                    <?php endif; ?>
                </div>
             </div>
        </div>
    </div>
    
    <a href="index.php" class="back-link mt-4">Voltar para a Lista de Grupos</a>
</div>

<script>
// NOVA FUNÇÃO: Lida com a remoção de máquinas do grupo via JS
function removerMaquina(maquinaId) {
    if (confirm('Tem certeza que deseja remover esta máquina do grupo?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'gerir_maquinas.php?id=<?php echo $grupo_id; ?>';
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'remove_maquina';
        form.appendChild(actionInput);

        const maquinaIdInput = document.createElement('input');
        maquinaIdInput.type = 'hidden';
        maquinaIdInput.name = 'maquina_id';
        maquinaIdInput.value = maquinaId;
        form.appendChild(maquinaIdInput);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
ob_end_flush();
?>
