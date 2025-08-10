<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/header.php';

$conn = connectDB();

// A consulta foi atualizada para contar as máquinas operacionais dentro de cada grupo.
$sql = "SELECT 
            g.id, 
            g.nome_grupo, 
            g.descricao, 
            COUNT(mga.maquina_id) as total_maquinas,
            SUM(CASE WHEN m.status = 'operacional' THEN 1 ELSE 0 END) as maquinas_operacionais
        FROM grupos_maquinas g
        LEFT JOIN maquina_grupo_associacao mga ON g.id = mga.grupo_id
        LEFT JOIN maquinas m ON mga.maquina_id = m.id
        WHERE g.deleted_at IS NULL
        GROUP BY g.id, g.nome_grupo, g.descricao
        ORDER BY g.nome_grupo ASC";

$grupos = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-layer-group"></i> Grupos de Máquinas (Centros de Trabalho)</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Novo Grupo</a>
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
                <th>Nome do Grupo</th>
                <th>Descrição</th>
                <th>Máquinas</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $grupo): ?>
                <tr>
                    <td><?php echo htmlspecialchars($grupo['nome_grupo']); ?></td>
                    <td><?php echo htmlspecialchars($grupo['descricao']); ?></td>
                    <td><?php echo ($grupo['maquinas_operacionais'] ?? 0) . ' / ' . $grupo['total_maquinas']; ?></td>
                    <td>
                        <a href="gerir_maquinas.php?id=<?php echo $grupo['id']; ?>" class="button small">Gerir Máquinas</a>
                        <a href="editar.php?id=<?php echo $grupo['id']; ?>" class="button edit small">Editar</a>
                        <!-- OBSERVAÇÃO: Botão atualizado para usar o deleteModal -->
                        <button class="button delete small" onclick="showDeleteModal('grupos_maquinas', <?php echo $grupo['id']; ?>)">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <a href="../index.php" class="back-link mt-4">Voltar ao Portal de Manutenção</a>
<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
