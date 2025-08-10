<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// Lógica de Paginação
$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Contar o total de itens
$total_items = $conn->query("SELECT COUNT(*) AS total FROM roteiros WHERE deleted_at IS NULL")->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os roteiros para a página atual, juntando com o nome do produto
$sql = "SELECT r.id, r.descricao, r.ativo, p.nome as produto_nome, p.codigo as produto_codigo
        FROM roteiros r
        JOIN produtos p ON r.produto_id = p.id
        WHERE r.deleted_at IS NULL
        ORDER BY p.nome ASC
        LIMIT ? OFFSET ?";
$roteiros = $conn->execute_query($sql, [$items_per_page, $offset])->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-route"></i> Roteiros de Produção</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Novo Roteiro</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (!empty($roteiros)): ?>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Descrição do Roteiro</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roteiros as $roteiro): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($roteiro['produto_nome'] . ' (' . $roteiro['produto_codigo'] . ')'); ?></td>
                        <td><?php echo htmlspecialchars($roteiro['descricao']); ?></td>
                        <td><?php echo $roteiro['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                        <td>
                            <a href="etapas.php?roteiro_id=<?php echo $roteiro['id']; ?>" class="button small">Ver/Editar Etapas</a>
                            <a href="editar.php?id=<?php echo $roteiro['id']; ?>" class="button edit small">Editar</a>
                            <!-- OBSERVAÇÃO: Botão atualizado para usar o deleteModal -->
                            <button class="button delete small" onclick="showDeleteModal('roteiros', <?php echo $roteiro['id']; ?>)">Excluir</button>
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
        <p style="text-align: center; margin-top: 20px;">Nenhum roteiro de produção cadastrado ainda.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
