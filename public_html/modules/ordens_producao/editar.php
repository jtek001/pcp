<?php
// modules/ordens_producao/editar.php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: index.php?message=" . urlencode("ID da Ordem de Produção inválido.") . "&type=error");
    exit();
}

// --- Lógica para processar a submissão do formulário de edição ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitiza e valida as entradas
    $numero_pedido = sanitizeInput($_POST['numero_pedido'] ?? '');
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $maquina_id = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT) ?: null;
    $quantidade_produzir = (float) sanitizeInput($_POST['quantidade_produzir'] ?? 0.0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $data_prevista_conclusao = !empty($_POST['data_prevista_conclusao']) ? sanitizeInput($_POST['data_prevista_conclusao']) : null;
    $observacoes = sanitizeInput($_POST['observacoes'] ?? '');

    // Validação básica
    if (empty($numero_pedido) || empty($produto_id) || $quantidade_produzir <= 0 || empty($status)) {
        $_SESSION['message'] = "Todos os campos marcados com * são obrigatórios.";
        $_SESSION['message_type'] = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Apenas atualiza, não mexe com empenhos na edição por enquanto.
            $sql = "UPDATE ordens_producao SET 
                        numero_pedido = ?, 
                        produto_id = ?, 
                        maquina_id = ?, 
                        quantidade_produzir = ?, 
                        data_prevista_conclusao = ?, 
                        status = ?, 
                        observacoes = ? 
                    WHERE id = ?";
            $params = [$numero_pedido, $produto_id, $maquina_id, $quantidade_produzir, $data_prevista_conclusao, $status, $observacoes, $id];
            $conn->execute_query($sql, $params);

            $conn->commit();
            $_SESSION['message'] = "Ordem de Produção atualizada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao atualizar a Ordem de Produção: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    // Em caso de erro, recarrega a página de edição
    header("Location: editar.php?id=" . $id);
    exit();
}


// --- Lógica para buscar os dados para preencher o formulário ---
require_once __DIR__ . '/../../includes/header.php';

$sql_select = "SELECT op.*, p.nome AS produto_nome_original, p.codigo AS produto_codigo_original FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id WHERE op.id = ?";
$op_data = $conn->execute_query($sql_select, [$id])->fetch_assoc();
if (!$op_data) {
    die("Ordem de Produção não encontrada.");
}

$maquinas_ativas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL AND status = 'operacional' ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$sql_pedidos = "SELECT pv.numero_pedido, fc.nome as cliente_nome 
                FROM pedidos_venda pv 
                JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id 
                WHERE pv.status = 'Aprovado' AND pv.deleted_at IS NULL 
                ORDER BY pv.numero_pedido DESC";
$pedidos_venda = $conn->query($sql_pedidos)->fetch_all(MYSQLI_ASSOC);

$quantidade_apontada = $conn->execute_query("SELECT SUM(COALESCE(quantidade_produzida, 0)) as total FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL", [$id])->fetch_assoc()['total'] ?? 0;

$is_op_concluida_ou_cancelada = in_array($op_data['status'], ['concluida', 'cancelada']);
?>

<h2>Editar Ordem de Produção</h2>

<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>

<form action="editar.php?id=<?php echo $op_data['id']; ?>" method="POST">
    <?php 
    $disabled_html_attr = $is_op_concluida_ou_cancelada ? 'disabled' : '';
    $readonly_html_attr = $is_op_concluida_ou_cancelada ? 'readonly' : '';
    ?>
    <div class="form-group">
        <label for="numero_op">Número da OP:</label>
        <input type="text" id="numero_op" name="numero_op" value="<?php echo htmlspecialchars($op_data['numero_op']); ?>" readonly>
    </div>

    <div class="form-group">
        <label for="numero_pedido">Número do Pedido (Aprovados)*:</label>
        <select id="numero_pedido" name="numero_pedido" required <?php echo $disabled_html_attr; ?>>
            <!-- OBSERVAÇÃO: A opção vazia foi removida -->
            <?php
            $current_numero_pedido = $_POST['numero_pedido'] ?? $op_data['numero_pedido'];
            foreach ($pedidos_venda as $pedido) {
                $selected = ($current_numero_pedido == $pedido['numero_pedido']) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($pedido['numero_pedido']) . '" ' . $selected . '>';
                echo htmlspecialchars('Pedido: ' . $pedido['numero_pedido'] . ' - Cliente: ' . $pedido['cliente_nome']);
                echo '</option>';
            }
            ?>
        </select>
    </div>

    <div class="form-group">
        <label for="produto_search">Produto:</label>
        <input type="text" id="produto_search" value="<?php echo htmlspecialchars($op_data['produto_nome_original'] . ' (' . $op_data['produto_codigo_original'] . ')'); ?>" readonly>
        <input type="hidden" id="produto_id_hidden" name="produto_id" value="<?php echo htmlspecialchars($op_data['produto_id']); ?>" required>
    </div>

    <div class="form-group">
        <label for="maquina_id">Máquina Ideal:</label>
        <select id="maquina_id" name="maquina_id" <?php echo $disabled_html_attr; ?>>
            <option value="">Selecione uma Máquina</option>
            <?php
            $current_maquina_id = $_POST['maquina_id'] ?? $op_data['maquina_id'];
            foreach ($maquinas_ativas as $maquina): ?>
                <option value="<?php echo htmlspecialchars($maquina['id']); ?>" <?php echo ($current_maquina_id == $maquina['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($maquina['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="quantidade_produzir">Quantidade a Produzir:</label>
        <input type="number" id="quantidade_produzir" name="quantidade_produzir" step="0.01" value="<?php echo htmlspecialchars($op_data['quantidade_produzir']); ?>" required <?php echo $readonly_html_attr; ?>>
    </div>
    
    <div class="form-group">
        <label for="quantidade_apontada">Quantidade Já Produzida:</label>
        <input type="number" id="quantidade_apontada" value="<?php echo htmlspecialchars($quantidade_apontada); ?>" readonly>
    </div>
    
    <div class="form-group">
        <label for="status">Status:</label>
        <select id="status" name="status" required>
            <option value="pendente" <?php echo ($op_data['status'] == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
            <option value="em_producao" <?php echo ($op_data['status'] == 'em_producao') ? 'selected' : ''; ?>>Em Produção</option>
            <option value="concluida" <?php echo ($op_data['status'] == 'concluida') ? 'selected' : ''; ?>>Concluída</option>
            <option value="cancelada" <?php echo ($op_data['status'] == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
        </select>
    </div>

    <div class="form-group">
        <label for="data_prevista_conclusao">Data Prevista de Conclusão:</label>
        <input type="date" id="data_prevista_conclusao" name="data_prevista_conclusao" value="<?php echo htmlspecialchars($op_data['data_prevista_conclusao']); ?>" <?php echo $readonly_html_attr; ?>>
    </div>

    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes" <?php echo $readonly_html_attr; ?>><?php echo htmlspecialchars($op_data['observacoes']); ?></textarea>
    </div>

    <button type="submit" class="button submit">Atualizar Ordem de Produção</button>
</form>

<a href="index.php" class="back-link">Voltar para a lista de Ordens de Produção</a>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
