<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- OBSERVAÇÃO DE SEGURANÇA ---
// Apenas usuários com cargo 'admin' podem acessar esta página.
if (!isset($_SESSION['user_cargo']) || $_SESSION['user_cargo'] !== 'admin') {
    $_SESSION['message'] = "Acesso negado. Você não tem permissão para acessar esta funcionalidade.";
    $_SESSION['message_type'] = "error";
    header("Location: " . BASE_URL . "/public/index.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
$conn = connectDB();

// Lógica de Paginação
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Contar o total de itens
$total_items = $conn->query("SELECT COUNT(*) AS total FROM operadores WHERE deleted_at IS NULL")->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Buscar os itens para a página atual
$sql = "SELECT * FROM operadores WHERE deleted_at IS NULL ORDER BY nome ASC LIMIT ? OFFSET ?";
$operadores = $conn->execute_query($sql, [$items_per_page, $offset])->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users-cog"></i> Gestão de Operadores</h2>
        <a href="adicionar.php" class="button add"><i class="fas fa-plus"></i> Adicionar Operador</a>
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
                <th>Nome</th>
                <th>Matrícula</th>
                <th>Usuário</th>
                <th>Cargo</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($operadores as $operador): ?>
                <tr>
                    <td><?php echo htmlspecialchars($operador['nome']); ?></td>
                    <td><?php echo htmlspecialchars($operador['matricula']); ?></td>
                    <td><?php echo htmlspecialchars($operador['username']); ?></td>
                    <td><?php echo htmlspecialchars($operador['cargo']); ?></td>
                    <td><?php echo $operador['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $operador['id']; ?>" class="button edit small">Editar</a>
                        <button class="button delete small" onclick="showDeleteModal('operadores', <?php echo $operador['id']; ?>)">Excluir</button>
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
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
?>
