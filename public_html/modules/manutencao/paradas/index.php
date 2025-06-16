<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/header.php';

$conn = connectDB();

// Lógica de Paginação
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Contar o total de itens
$total_items = $conn->query("SELECT COUNT(*) AS total FROM paradas_maquina WHERE deleted_at IS NULL")->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os itens para a página atual
$sql = "SELECT 
            pm.*, 
            m.nome as maquina_nome, 
            mp.nome as motivo_nome
        FROM paradas_maquina pm
        JOIN maquinas m ON pm.maquina_id = m.id
        JOIN motivos_parada mp ON pm.motivo_id = mp.id
        WHERE pm.deleted_at IS NULL
        ORDER BY pm.data_hora_inicio DESC
        LIMIT ? OFFSET ?";
$paradas = $conn->execute_query($sql, [$items_per_page, $offset])->fetch_all(MYSQLI_ASSOC);

?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-stopwatch-20"></i> Controle de Paradas de Máquina</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Registrar Nova Parada</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (!empty($paradas)): ?>
        <table>
            <thead>
                <tr>
                    <th>Máquina</th>
                    <th>Motivo</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Duração (min)</th>
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
                        <td><?php echo htmlspecialchars($parada['duracao_minutos']); ?></td>
                        <td>
                            <a href="editar.php?id=<?php echo $parada['id']; ?>" class="button edit small">Editar</a>
                            <a href="excluir.php?id=<?php echo $parada['id']; ?>" class="button delete small" onclick="return confirm('Tem certeza que deseja excluir este registro de parada?');">Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo ($i == $current_page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; margin-top: 20px;">Nenhuma parada de máquina registrada ainda.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
$conn->close();
?>
