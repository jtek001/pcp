<?php
// modules/ordens_producao/editar.php

ob_start();
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$op_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$op_id) {
    header("Location: index.php?message=" . urlencode("ID da OP inválido.") . "&type=error");
    exit();
}

// --- LÓGICA PARA PROCESSAR A ATUALIZAÇÃO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grupo_id = filter_input(INPUT_POST, 'grupo_id', FILTER_VALIDATE_INT) ?: null;
    $quantidade_produzir = (float) sanitizeInput($_POST['quantidade_produzir'] ?? 0.0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $data_prevista_conclusao = !empty($_POST['data_prevista_conclusao']) ? sanitizeInput($_POST['data_prevista_conclusao']) : null;
    $observacoes = sanitizeInput($_POST['observacoes'] ?? '');

    if ($quantidade_produzir > 0 && !empty($status)) {
        try {
            // OBSERVAÇÃO: A query agora atualiza a coluna correta 'grupo_id'.
            $sql = "UPDATE ordens_producao SET grupo_id = ?, quantidade_produzir = ?, data_prevista_conclusao = ?, status = ?, observacoes = ? WHERE id = ?";
            $params = [$grupo_id, $quantidade_produzir, $data_prevista_conclusao, $status, $observacoes, $op_id];
            $conn->execute_query($sql, $params);
            $_SESSION['message'] = "Ordem de Produção atualizada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = "Erro ao atualizar OP: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos marcados com * são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: editar.php?id=" . $op_id);
    exit();
}


// --- LÓGICA PARA BUSCAR DADOS E PREPARAR A PÁGINA (GET) ---
try {
    $sql_select_op = "SELECT op.*, p.nome AS produto_nome_original, p.codigo AS produto_codigo_original, fc.nome as cliente_nome
                      FROM ordens_producao op 
                      JOIN produtos p ON op.produto_id = p.id
                      LEFT JOIN pedidos_venda pv ON op.numero_pedido = pv.numero_pedido
                      LEFT JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id
                      WHERE op.id = ?";
    $op_data = $conn->execute_query($sql_select_op, [$op_id])->fetch_assoc();

    if (!$op_data) {
        $_SESSION['message'] = "Ordem de Produção não encontrada.";
        $_SESSION['message_type'] = "error";
        header("Location: index.php");
        exit();
    }

    $sql_grupos = "SELECT DISTINCT gm.id, gm.nome_grupo
                   FROM grupos_maquinas gm
                   JOIN roteiro_etapas re ON gm.id = re.grupo_id
                   JOIN roteiros r ON re.roteiro_id = r.id
                   WHERE r.produto_id = ? AND r.ativo = 1 AND r.deleted_at IS NULL
                   ORDER BY re.sequencia";
    $grupos_do_roteiro = $conn->execute_query($sql_grupos, [$op_data['produto_id']])->fetch_all(MYSQLI_ASSOC);
    
    $quantidade_apontada = $conn->execute_query("SELECT SUM(COALESCE(quantidade_produzida, 0)) as total FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL", [$op_id])->fetch_assoc()['total'] ?? 0;
    $is_op_concluida_ou_cancelada = in_array($op_data['status'], ['concluida', 'cancelada']);

} catch(Exception $e) {
    $_SESSION['message'] = "Erro fatal ao carregar dados da página: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Editar Ordem de Produção</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="editar.php?id=<?php echo $op_data['id']; ?>" method="POST">
        <?php 
        $disabled_html_attr = $is_op_concluida_ou_cancelada ? 'disabled' : '';
        $readonly_html_attr = $is_op_concluida_ou_cancelada ? 'readonly' : '';
        ?>
        <div class="form-group full-width">
            <label for="numero_op">Número da OP:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($op_data['numero_op']); ?>" readonly>
        </div>
        <div class="form-group full-width">
            <label for="numero_pedido">Número do Pedido:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars('Pedido: ' . $op_data['numero_pedido'] . ' - Cliente: ' . ($op_data['cliente_nome'] ?? 'N/A')); ?>" readonly>
        </div>
        <div class="form-group full-width">
            <label for="produto_nome">Produto:</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($op_data['produto_nome_original'] . ' (' . $op_data['produto_codigo_original'] . ')'); ?>" readonly>
        </div>
        <div class="form-group full-width">
            <label for="grupo_id">Grupo de Máquinas:</label>
            <select id="grupo_id" name="grupo_id" class="form-select" <?php echo $disabled_html_attr; ?>>
                <option value="">Selecione um Grupo</option>
                <?php foreach ($grupos_do_roteiro as $grupo): ?>
                    <option value="<?php echo $grupo['id']; ?>" <?php echo ($op_data['grupo_id'] == $grupo['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($grupo['nome_grupo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="quantidade_produzir">Quantidade a Produzir:</label>
            <input type="number" id="quantidade_produzir" name="quantidade_produzir" class="form-control" step="0.01" value="<?php echo htmlspecialchars($op_data['quantidade_produzir']); ?>" required <?php echo $readonly_html_attr; ?>>
        </div>
        <div class="form-group">
            <label for="quantidade_apontada">Quantidade Já Produzida:</label>
            <input type="number" id="quantidade_apontada" class="form-control" value="<?php echo htmlspecialchars($quantidade_apontada); ?>" readonly>
        </div>
        <div class="form-group">
            <label for="status">Status:</label>
            <select id="status" name="status" class="form-select" required>
                <option value="pendente" <?php echo ($op_data['status'] == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                <option value="em_producao" <?php echo ($op_data['status'] == 'em_producao') ? 'selected' : ''; ?>>Em Produção</option>
                <option value="concluida" <?php echo ($op_data['status'] == 'concluida') ? 'selected' : ''; ?>>Concluída</option>
                <option value="cancelada" <?php echo ($op_data['status'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
            </select>
        </div>
        <div class="form-group">
            <label for="data_prevista_conclusao">Data Prevista de Conclusão:</label>
            <input type="date" id="data_prevista_conclusao" name="data_prevista_conclusao" class="form-control" value="<?php echo htmlspecialchars($op_data['data_prevista_conclusao']); ?>" <?php echo $readonly_html_attr; ?>>
        </div>
        <div class="form-group full-width">
            <label for="observacoes">Observações:</label>
            <textarea id="observacoes" name="observacoes" class="form-control" <?php echo $readonly_html_attr; ?>><?php echo htmlspecialchars($op_data['observacoes']); ?></textarea>
        </div>

        <div class="mt-4 text-center">
            <button type="submit" class="button submit">Salvar Alterações</button>
        </div>
    </form>

    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
