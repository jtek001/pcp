<?php
ob_start();
session_start();
// OBSERVAÇÃO: Fuso horrio definido para garantir a hora local correta.
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../../../config/database.php';

$conn = connectDB();

// Busca dados para os dropdowns
$maquinas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$motivos = $conn->query("SELECT id, nome, codigo, grupo FROM motivos_parada WHERE deleted_at IS NULL ORDER BY codigo ASC")->fetch_all(MYSQLI_ASSOC);
$operadores = $conn->query("SELECT id, nome FROM operadores WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maquina_id = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT);
    $motivo_id = filter_input(INPUT_POST, 'motivo_id', FILTER_VALIDATE_INT);
    $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
    $data_hora_inicio = sanitizeInput($_POST['data_hora_inicio']);
    $data_hora_fim = sanitizeInput($_POST['data_hora_fim']) ?: null;
    $observacoes = sanitizeInput($_POST['observacoes']);
    
    // Calcula a duração em minutos se ambas as datas estiverem presentes
    $duracao_minutos = null;
    if ($data_hora_inicio && $data_hora_fim) {
        $inicio = new DateTime($data_hora_inicio);
        $fim = new DateTime($data_hora_fim);
        $diferenca = $inicio->diff($fim);
        $duracao_minutos = ($diferenca->days * 24 * 60) + ($diferenca->h * 60) + $diferenca->i;
    }

    if ($maquina_id && $motivo_id && $data_hora_inicio) {
        $conn->begin_transaction();
        try {
            // 1. Inserir o registo da parada
            $sql = "INSERT INTO paradas_maquina (maquina_id, motivo_id, operador_id, data_hora_inicio, data_hora_fim, duracao_minutos, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $conn->execute_query($sql, [$maquina_id, $motivo_id, $operador_id, $data_hora_inicio, $data_hora_fim, $duracao_minutos, $observacoes]);
            
            // O status da máquina só é alterado se a parada for registrada sem data de fim.
            if (empty($data_hora_fim)) {
                $sql_get_motivo = "SELECT grupo FROM motivos_parada WHERE id = ?";
                $motivo_info = $conn->execute_query($sql_get_motivo, [$motivo_id])->fetch_assoc();
                
                $novo_status_maquina = 'parada'; // Status padrão
                if ($motivo_info && strtoupper($motivo_info['grupo']) === 'MANUTENCAO') {
                    $novo_status_maquina = 'manutencao';
                }

                // Atualizar o status na tabela 'maquinas'
                $sql_update_maquina = "UPDATE maquinas SET status = ? WHERE id = ?";
                $conn->execute_query($sql_update_maquina, [$novo_status_maquina, $maquina_id]);
                
                $_SESSION['message'] = "Parada de máquina registrada e status da máquina atualizado!";
            } else {
                $_SESSION['message'] = "Parada de máquina registrada com sucesso! (Status da máquina não foi alterado).";
            }

            $conn->commit();
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao registrar parada: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Máquina, Motivo e Data/Hora de Início são campos obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: adicionar.php");
    exit();
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-plus-circle"></i> Registrar Nova Parada de Máquina</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="adicionar.php" method="POST">
        <div class="form-group">
            <label for="maquina_id">Máquina</label>
            <select name="maquina_id" id="maquina_id" required>
                <option value="">Selecione a máquina</option>
                <?php foreach($maquinas as $maquina): ?>
                    <option value="<?php echo $maquina['id']; ?>"><?php echo htmlspecialchars($maquina['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="motivo_id">Motivo da Parada</label>
            <select name="motivo_id" id="motivo_id" required>
                <option value="">Selecione o motivo</option>
                <?php foreach($motivos as $motivo): ?>
                    <option value="<?php echo $motivo['id']; ?>"><?php echo htmlspecialchars($motivo['codigo'] . ' - ' . $motivo['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="data_hora_inicio">Data e Hora de Início</label>
            <input type="datetime-local" name="data_hora_inicio" id="data_hora_inicio" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
        </div>
        <div class="form-group">
            <label for="data_hora_fim">Data e Hora de Fim (opcional)</label>
            <input type="datetime-local" name="data_hora_fim" id="data_hora_fim">
        </div>
        <div class="form-group">
            <label for="operador_id">Operador</label>
            <select name="operador_id" id="operador_id">
                <option value="">Nenhum / Selecione</option>
                 <?php foreach ($operadores as $operador): ?>
                    <option value="<?php echo $operador['id']; ?>"><?php echo htmlspecialchars($operador['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group full-width">
            <label for="observacoes">Observações</label>
            <textarea name="observacoes" id="observacoes" rows="3"></textarea>
        </div>
        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Salvar Registro</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
$conn->close();
ob_end_flush();
?>
