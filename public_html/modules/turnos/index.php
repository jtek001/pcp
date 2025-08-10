<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();
$turnos = $conn->query("SELECT * FROM turnos WHERE deleted_at IS NULL ORDER BY nome_turno ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-clock"></i> Cadastro de Turnos</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Novo Turno</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Nome do Turno</th>
                <th>Hora de Início</th>
                <th>Hora de Fim</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($turnos as $turno): ?>
                <tr>
                    <td><?php echo htmlspecialchars($turno['nome_turno']); ?></td>
                    <td><?php echo date('H:i', strtotime($turno['hora_inicio'])); ?></td>
                    <td><?php echo date('H:i', strtotime($turno['hora_fim'])); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $turno['id']; ?>" class="button edit small">Editar</a>
                        <!-- OBSERVAÇÃO: Botão atualizado para usar o deleteModal -->
                        <button class="button delete small" onclick="showDeleteModal('turnos', <?php echo $turno['id']; ?>)">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
