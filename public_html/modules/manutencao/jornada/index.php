<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/header.php';

$conn = connectDB();

$sql = "SELECT 
            mjl.id, mjl.data_hora_inicio, mjl.data_hora_fim, mjl.duracao_minutos,
            m.nome as maquina_nome, o.nome as operador_nome, t.nome_turno,
            (SELECT SUM(COALESCE(jpl.duracao_minutos, 0)) 
             FROM jornada_pausas_log jpl 
             WHERE jpl.jornada_log_id = mjl.id) as total_pausas_minutos
        FROM maquina_jornada_log mjl
        JOIN maquinas m ON mjl.maquina_id = m.id
        JOIN operadores o ON mjl.operador_id = o.id
        JOIN turnos t ON mjl.turno_id = t.id
        ORDER BY mjl.id DESC";
$jornadas = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check"></i> Jornadas de Máquina</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Iniciar Nova Jornada</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Máquina</th>
                <th>Operador</th>
                <th>Turno</th>
                <th>Início</th>
                <th>Fim</th>
                <th class="text-end">Tempo (min)</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jornadas as $jornada): 
                $duracao_total = (int)($jornada['duracao_minutos'] ?? 0);
                $total_pausas = (int)($jornada['total_pausas_minutos'] ?? 0);
                $duracao_efetiva = $duracao_total - $total_pausas;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($jornada['maquina_nome']); ?></td>
                    <td><?php echo htmlspecialchars($jornada['operador_nome']); ?></td>
                    <td><?php echo htmlspecialchars($jornada['nome_turno']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($jornada['data_hora_inicio'])); ?></td>
                    <td><?php echo $jornada['data_hora_fim'] ? date('d/m/Y H:i', strtotime($jornada['data_hora_fim'])) : 'Em aberto'; ?></td>
                    <td class="text-end"><?php echo $jornada['data_hora_fim'] ? htmlspecialchars($duracao_efetiva) : ''; ?></td>
                    <td>
                        <?php if (empty($jornada['data_hora_fim'])): ?>
                            <a href="apontar_pausa.php?jornada_id=<?php echo $jornada['id']; ?>" class="button small">Pausa</a>
                            <a href="editar.php?id=<?php echo $jornada['id']; ?>" class="button edit small">Finalizar</a>
                        <?php endif; ?>
                        <button class="button delete small" onclick="showDeleteModal('maquina_jornada_log', <?php echo $jornada['id']; ?>)">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <a href="../index.php" class="back-link mt-4">Voltar ao Portal de Manutenção</a>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
