<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/header.php';

$conn = connectDB();

$sql = "SELECT 
            pm.id, pm.data_hora_inicio, pm.data_hora_fim, pm.duracao_minutos,
            m.nome as maquina_nome, mp.nome as motivo_nome, o.nome as operador_nome
        FROM paradas_maquina pm
        JOIN maquinas m ON pm.maquina_id = m.id
        JOIN motivos_parada mp ON pm.motivo_id = mp.id
        LEFT JOIN operadores o ON pm.operador_id = o.id
        WHERE pm.deleted_at IS NULL
        ORDER BY pm.data_hora_inicio DESC";
$paradas = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-stopwatch-20"></i> Paradas de Máquina</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Registrar Nova Parada</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Máquina</th>
                <th>Motivo</th>
                <th>Início</th>
                <th>Fim</th>
                <th class="text-end">Tempo (min)</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paradas as $parada): ?>
                <tr>
                    <td><?php echo htmlspecialchars($parada['maquina_nome']); ?></td>
                    <td><?php echo htmlspecialchars($parada['motivo_nome']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($parada['data_hora_inicio'])); ?></td>
                    <td><?php echo $parada['data_hora_fim'] ? date('d/m/Y H:i', strtotime($parada['data_hora_fim'])) : 'Em aberto'; ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($parada['duracao_minutos']); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $parada['id']; ?>" class="button edit small">Editar</a>
                        <button class="button delete small" onclick="showDeleteModal('paradas_maquina', <?php echo $parada['id']; ?>)">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <a href="../index.php" class="back-link mt-4">Voltar ao Portal de Manutenção</a>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
