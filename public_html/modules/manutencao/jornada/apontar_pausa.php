<?php
ob_start();
session_start();
require_once __DIR__ . '/../../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();
$jornada_id = filter_input(INPUT_GET, 'jornada_id', FILTER_VALIDATE_INT);

if (!$jornada_id) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
    $data_hora_inicio_pausa = sanitizeInput($_POST['data_hora_inicio_pausa']);
    $data_hora_fim_pausa = sanitizeInput($_POST['data_hora_fim_pausa']);
    $observacoes = sanitizeInput($_POST['observacoes']);

    if ($operador_id && $data_hora_inicio_pausa && $data_hora_fim_pausa) {
        try {
            $inicio = new DateTime($data_hora_inicio_pausa);
            $fim = new DateTime($data_hora_fim_pausa);
            $diff = $inicio->diff($fim);
            $duracao_minutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

            $sql = "INSERT INTO jornada_pausas_log (jornada_log_id, operador_id, data_hora_inicio_pausa, data_hora_fim_pausa, duracao_minutos, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
            $conn->execute_query($sql, [$jornada_id, $operador_id, $data_hora_inicio_pausa, $data_hora_fim_pausa, $duracao_minutos, $observacoes]);
            
            $_SESSION['message'] = "Pausa registrada com sucesso!";
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
    header("Location: apontar_pausa.php?jornada_id=" . $jornada_id);
    exit();
}

require_once __DIR__ . '/../../../includes/header.php';
$sql_get = "SELECT mjl.*, m.nome as maquina_nome, o.nome as operador_nome FROM maquina_jornada_log mjl JOIN maquinas m ON mjl.maquina_id = m.id JOIN operadores o ON mjl.operador_id = o.id WHERE mjl.id = ?";
$jornada = $conn->execute_query($sql_get, [$jornada_id])->fetch_assoc();
if (!$jornada) die("Registro de jornada não encontrado.");

$operadores = $conn->query("SELECT id, nome FROM operadores WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-coffee"></i> Apontar Pausa na Jornada</h2>

    <div class="alert alert-secondary">
        <p><strong>Máquina:</strong> <?php echo htmlspecialchars($jornada['maquina_nome']); ?></p>
        <p><strong>Operador da Jornada:</strong> <?php echo htmlspecialchars($jornada['operador_nome']); ?></p>
        <p><strong>Início da Jornada:</strong> <?php echo date('d/m/Y H:i', strtotime($jornada['data_hora_inicio'])); ?></p>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="apontar_pausa.php?jornada_id=<?php echo $jornada_id; ?>" method="POST">
        <div class="form-group full-width"><label for="operador_id">Operador da Pausa*</label><select name="operador_id" required><option value="">Selecione...</option><?php foreach($operadores as $o): ?><option value="<?php echo $o['id']; ?>" <?php echo ($jornada['operador_id'] == $o['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($o['nome']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="data_hora_inicio_pausa">Início da Pausa*</label><input type="datetime-local" name="data_hora_inicio_pausa" value="<?php echo date('Y-m-d\TH:i'); ?>" required></div>
        <div class="form-group"><label for="data_hora_fim_pausa">Fim da Pausa*</label><input type="datetime-local" name="data_hora_fim_pausa" value="<?php echo date('Y-m-d\TH:i'); ?>" required></div>
        <div class="form-group full-width"><label for="observacoes">Observações (Opcional)</label><input type="text" name="observacoes"></div>
        <div class="full-width text-center"><button type="submit" class="button submit">Salvar Pausa</button></div>
    </form>
    <a href="index.php" class="back-link">Voltar</a>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ob_end_flush(); ?>
