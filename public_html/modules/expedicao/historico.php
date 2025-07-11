<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

$sql = "SELECT 
            el.id,
            el.data_movimentacao,
            el.tipo_movimentacao,
            el.lote_numero,
            p.nome as produto_nome,
            el.quantidade,
            el.nota_fiscal_saida,
            o.nome as operador_nome
        FROM expedicao_log el
        JOIN produtos p ON el.produto_id = p.id
        LEFT JOIN operadores o ON el.operador_id = o.id
        ORDER BY el.id DESC";

$movimentacoes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-history"></i> Histórico de Movimentações da Expedição</h2>
        <a href="index.php" class="button">Voltar</a>
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
                <th>Data</th>
                <th>Tipo</th>
                <th>Lote</th>
                <th>Produto</th>
                <th class="text-end">Quantidade</th>
                <th>NF Saída</th>
                <th>Operador</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($movimentacoes as $mov): ?>
                <tr class="<?php echo $mov['tipo_movimentacao'] === 'entrada' ? 'status-concluida-row' : 'status-cancelada-row'; ?>">
                    <td><?php echo date('d/m/Y H:i', strtotime($mov['data_movimentacao'])); ?></td>
                    <td><?php echo ucfirst($mov['tipo_movimentacao']); ?></td>
                    <td><?php echo htmlspecialchars($mov['lote_numero']); ?></td>
                    <td><?php echo htmlspecialchars($mov['produto_nome']); ?></td>
                    <td class="text-end"><?php echo number_format($mov['quantidade'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($mov['nota_fiscal_saida'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($mov['operador_nome'] ?? 'N/A'); ?></td>
                    <td>
                        <a href="estornar.php?id=<?php echo $mov['id']; ?>" class="button delete small" onclick="return confirm('Tem certeza que deseja estornar esta movimentação? A ação é irreversível.');">Estornar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
