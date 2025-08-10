<?php
ob_start();
session_start();
require_once __DIR__ . '/../../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maquina_id = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT);
    $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
    $turno_id = filter_input(INPUT_POST, 'turno_id', FILTER_VALIDATE_INT);
    $data_hora_inicio = sanitizeInput($_POST['data_hora_inicio']);

    if ($maquina_id && $operador_id && $turno_id && $data_hora_inicio) {
        try {
            // OBSERVAÇÃO: Nova validação para impedir o início de uma jornada se já houver uma ativa.
            $sql_check = "SELECT id FROM maquina_jornada_log WHERE maquina_id = ? AND data_hora_fim IS NULL";
            $existing_jornada = $conn->execute_query($sql_check, [$maquina_id])->fetch_assoc();

            if ($existing_jornada) {
                throw new Exception("Esta máquina já possui uma jornada de trabalho em aberto. Finalize a jornada anterior antes de iniciar uma nova.");
            }

            $sql = "INSERT INTO maquina_jornada_log (maquina_id, operador_id, turno_id, data_hora_inicio) VALUES (?, ?, ?, ?)";
            $conn->execute_query($sql, [$maquina_id, $operador_id, $turno_id, $data_hora_inicio]);
            $_SESSION['message'] = "Jornada iniciada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = "Erro: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: adicionar.php");
    exit();
}

require_once __DIR__ . '/../../../includes/header.php';
$maquinas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$operadores = $conn->query("SELECT id, nome FROM operadores WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$turnos = $conn->query("SELECT id, nome_turno FROM turnos WHERE deleted_at IS NULL ORDER BY nome_turno ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-play-circle"></i> Iniciar Jornada de Máquina</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="adicionar.php" method="POST">
        <div class="form-group"><label for="maquina_id">Máquina*</label><select name="maquina_id" required><option value="">Selecione...</option><?php foreach($maquinas as $m): ?><option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nome']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="operador_id">Operador*</label><select name="operador_id" required><option value="">Selecione...</option><?php foreach($operadores as $o): ?><option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['nome']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="turno_id">Turno*</label><select name="turno_id" required><option value="">Selecione...</option><?php foreach($turnos as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nome_turno']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="data_hora_inicio">Data e Hora de Início*</label><input type="datetime-local" name="data_hora_inicio" value="<?php echo date('Y-m-d\TH:i'); ?>" required></div>
        <div class="full-width text-center"><button type="submit" class="button submit">Iniciar Jornada</button></div>
    </form>
    <a href="index.php" class="back-link">Voltar</a>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ob_end_flush(); ?>
