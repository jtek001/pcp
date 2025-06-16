<?php
ob_start();
session_start();
// Adicionado para garantir o fuso horário correto
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../../../config/database.php';

$conn = connectDB();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$parada = null;

if (!$id) {
    header("Location: index.php");
    exit();
}

// Processa a atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_hora_fim = sanitizeInput($_POST['data_hora_fim']);
    $maquina_id = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT);

    if ($data_hora_fim && $maquina_id) {
        $conn->begin_transaction();
        try {
            // 1. Busca a data de início para calcular a duração
            $sql_get_inicio = "SELECT data_hora_inicio FROM paradas_maquina WHERE id = ?";
            $data_hora_inicio = $conn->execute_query($sql_get_inicio, [$id])->fetch_assoc()['data_hora_inicio'];

            // 2. Calcula a duração em minutos
            $inicio = new DateTime($data_hora_inicio);
            $fim = new DateTime($data_hora_fim);
            $diferenca = $inicio->diff($fim);
            $duracao_minutos = ($diferenca->days * 24 * 60) + ($diferenca->h * 60) + $diferenca->i;

            // 3. Atualiza o registro da parada
            $sql_update = "UPDATE paradas_maquina SET data_hora_fim = ?, duracao_minutos = ? WHERE id = ?";
            $conn->execute_query($sql_update, [$data_hora_fim, $duracao_minutos, $id]);

            // 4. OBSERVAÇÃO: Atualiza o status da máquina de volta para 'operacional'
            $sql_update_maquina = "UPDATE maquinas SET status = 'operacional' WHERE id = ?";
            $conn->execute_query($sql_update_maquina, [$maquina_id]);

            $conn->commit();
            $_SESSION['message'] = "Parada finalizada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao finalizar parada: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Data e Hora de Fim são obrigatórias para finalizar.";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: editar.php?id=" . $id);
    exit();
}

// Busca os dados da parada para preencher o formulário
$sql_get = "SELECT pm.*, m.nome as maquina_nome, mp.nome as motivo_nome FROM paradas_maquina pm 
            JOIN maquinas m ON pm.maquina_id = m.id
            JOIN motivos_parada mp ON pm.motivo_id = mp.id
            WHERE pm.id = ?";
$parada = $conn->execute_query($sql_get, [$id])->fetch_assoc();

if (!$parada) {
    $_SESSION['message'] = "Registro de parada não encontrado.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Finalizar Parada de Máquina</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="op-details mb-4">
        <p><strong>Máquina:</strong> <?php echo htmlspecialchars($parada['maquina_nome']); ?></p>
        <p><strong>Motivo:</strong> <?php echo htmlspecialchars($parada['motivo_nome']); ?></p>
        <p><strong>Início da Parada:</strong> <?php echo date('d/m/Y H:i', strtotime($parada['data_hora_inicio'])); ?></p>
    </div>

    <form action="editar.php?id=<?php echo $id; ?>" method="POST">
        <input type="hidden" name="maquina_id" value="<?php echo $parada['maquina_id']; ?>">
        <div class="form-group">
            <label for="data_hora_fim">Data e Hora de Fim</label>
            <input type="datetime-local" name="data_hora_fim" id="data_hora_fim" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
        </div>
        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Finalizar Parada</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
$conn->close();
ob_end_flush();
?>
