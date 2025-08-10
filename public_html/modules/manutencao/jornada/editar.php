<?php
ob_start();
session_start();
require_once __DIR__ . '/../../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();
$log_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$log_id) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_hora_fim = sanitizeInput($_POST['data_hora_fim']);
    
    if ($data_hora_fim) {
        $conn->begin_transaction();
        try {
            $sql_get_start = "SELECT data_hora_inicio FROM maquina_jornada_log WHERE id = ?";
            $inicio_str = $conn->execute_query($sql_get_start, [$log_id])->fetch_assoc()['data_hora_inicio'];
            
            $inicio = new DateTime($inicio_str);
            $fim = new DateTime($data_hora_fim);
            $diff = $inicio->diff($fim);
            $duracao_minutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

            $sql_update = "UPDATE maquina_jornada_log SET data_hora_fim = ?, duracao_minutos = ? WHERE id = ?";
            $conn->execute_query($sql_update, [$data_hora_fim, $duracao_minutos, $log_id]);
            
            $conn->commit();
            $_SESSION['message'] = "Jornada finalizada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: editar.php?id=" . $log_id);
    exit();
}

require_once __DIR__ . '/../../../includes/header.php';
$sql_get = "SELECT mjl.*, m.nome as maquina_nome, o.nome as operador_nome FROM maquina_jornada_log mjl JOIN maquinas m ON mjl.maquina_id = m.id JOIN operadores o ON mjl.operador_id = o.id WHERE mjl.id = ?";
$jornada = $conn->execute_query($sql_get, [$log_id])->fetch_assoc();
if (!$jornada) die("Registro de jornada não encontrado.");
?>

<div class="container mt-4">
    <h2><i class="fas fa-stop-circle"></i> Finalizar Jornada de Máquina</h2>

    <div class="alert alert-secondary">
        <p><strong>Máquina:</strong> <?php echo htmlspecialchars($jornada['maquina_nome']); ?></p>
        <p><strong>Operador:</strong> <?php echo htmlspecialchars($jornada['operador_nome']); ?></p>
        <p><strong>Início:</strong> <?php echo date('d/m/Y H:i', strtotime($jornada['data_hora_inicio'])); ?></p>
    </div>

    <form action="editar.php?id=<?php echo $log_id; ?>" method="POST">
        <div class="form-group">
            <label for="data_hora_fim">Data e Hora de Fim*</label>
            <input type="datetime-local" name="data_hora_fim" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
        </div>
        <div class="full-width text-center"><button type="submit" class="button submit">Finalizar Jornada</button></div>
    </form>
    <a href="index.php" class="back-link">Voltar</a>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ob_end_flush(); ?>
