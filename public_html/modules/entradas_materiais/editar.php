<?php
// modules/entradas_materiais/editar.php
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();
$entrada_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$entrada_id) {
    $_SESSION['message'] = "ID da entrada inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Lógica de POST para atualizar a entrada
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // Busca a quantidade antiga antes de atualizar
        $sql_get_old_qty = "SELECT produto_id, quantidade FROM materiais_insumos_entrada WHERE id = ?";
        $old_data = $conn->execute_query($sql_get_old_qty, [$entrada_id])->fetch_assoc();
        $old_qty = (float)$old_data['quantidade'];
        $produto_id = (int)$old_data['produto_id'];

        // Captura e sanitiza os novos dados
        $fornecedor_id = filter_input(INPUT_POST, 'fornecedor_id', FILTER_VALIDATE_INT);
        $quantidade_nova = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
        $valor_unitario = filter_input(INPUT_POST, 'valor_unitario', FILTER_VALIDATE_FLOAT);
        $numero_nota_fiscal = sanitizeInput($_POST['numero_nota_fiscal']);
        $data_emissao_nota = sanitizeInput($_POST['data_emissao_nota']);
        $data_entrada = sanitizeInput($_POST['data_entrada']);
        $local_armazenamento = sanitizeInput($_POST['local_armazenamento']);
        $responsavel_id = filter_input(INPUT_POST, 'responsavel_recebimento_id', FILTER_VALIDATE_INT);
        $observacoes = sanitizeInput($_POST['observacoes']);
        
        // Atualiza a entrada
        $sql_update_entrada = "UPDATE materiais_insumos_entrada SET fornecedor_id = ?, quantidade = ?, valor_unitario = ?, numero_nota_fiscal = ?, data_emissao_nota = ?, data_entrada = ?, local_armazenamento = ?, responsavel_recebimento_id = ?, observacoes = ? WHERE id = ?";
        $conn->execute_query($sql_update_entrada, [$fornecedor_id, $quantidade_nova, $valor_unitario, $numero_nota_fiscal, $data_emissao_nota, $data_entrada, $local_armazenamento, $responsavel_id, $observacoes, $entrada_id]);

        // Calcula a diferença e ajusta o estoque
        $diferenca_estoque = $quantidade_nova - $old_qty;
        $conn->execute_query("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?", [$diferenca_estoque, $produto_id]);
        
        $conn->commit();
        $_SESSION['message'] = "Entrada de material atualizada com sucesso!";
        $_SESSION['message_type'] = "success";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Erro ao atualizar entrada: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: editar.php?id=" . $entrada_id);
        exit();
    }
}

require_once __DIR__ . '/../../includes/header.php';
$fornecedores = $conn->query("SELECT id, nome FROM fornecedores_clientes_lookup WHERE (tipo = 'fornecedor' OR tipo = 'ambos') AND deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$operadores = $conn->query("SELECT id, nome FROM operadores WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$localizacoes = $conn->query("SELECT nome FROM localizacoes_lookup ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

// Busca os dados da entrada atual
$sql_get = "SELECT mie.*, p.nome as produto_nome, p.codigo as produto_codigo FROM materiais_insumos_entrada mie JOIN produtos p ON mie.produto_id = p.id WHERE mie.id = ?";
$entrada = $conn->execute_query($sql_get, [$entrada_id])->fetch_assoc();

if (!$entrada) {
    die("Registro de entrada não encontrado.");
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Editar Entrada de Material</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="editar.php?id=<?php echo $entrada_id; ?>" method="POST">
        <div class="form-group full-width">
            <label for="produto_search">Produto/Material:</label>
            <input type="text" id="produto_search" class="form-control" value="<?php echo htmlspecialchars($entrada['produto_nome'] . ' (' . $entrada['produto_codigo'] . ')'); ?>" readonly>
        </div>
        
        <div class="form-group">
            <label for="quantidade">Quantidade:</label>
            <input type="number" id="quantidade" name="quantidade" value="<?php echo htmlspecialchars($entrada['quantidade']); ?>" step="0.01" required>
        </div>
        <div class="form-group">
            <label for="valor_unitario">Valor Unitário:</label>
            <input type="number" id="valor_unitario" name="valor_unitario" value="<?php echo htmlspecialchars($entrada['valor_unitario']); ?>" step="0.0001">
        </div>
        <div class="form-group">
            <label for="numero_nota_fiscal">Número da Nota Fiscal:</label>
            <input type="text" id="numero_nota_fiscal" name="numero_nota_fiscal" value="<?php echo htmlspecialchars($entrada['numero_nota_fiscal']); ?>" required>
        </div>
        <div class="form-group">
            <label for="data_emissao_nota">Data Emissão NF:</label>
            <input type="date" id="data_emissao_nota" name="data_emissao_nota" value="<?php echo htmlspecialchars($entrada['data_emissao_nota']); ?>" required>
        </div>
        <div class="form-group">
            <label for="data_entrada">Data de Entrada:</label>
            <input type="datetime-local" id="data_entrada" name="data_entrada" value="<?php echo date('Y-m-d\TH:i', strtotime($entrada['data_entrada'])); ?>" required>
        </div>
        <div class="form-group">
            <label for="fornecedor_id">Fornecedor:</label>
            <select id="fornecedor_id" name="fornecedor_id">
                <option value="">Selecione</option>
                <?php foreach($fornecedores as $fornecedor): ?>
                    <option value="<?php echo $fornecedor['id']; ?>" <?php echo ($entrada['fornecedor_id'] == $fornecedor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fornecedor['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="local_armazenamento">Local Armazenamento:</label>
            <select id="local_armazenamento" name="local_armazenamento">
                <option value="">Selecione</option>
                <?php foreach($localizacoes as $local): ?>
                    <option value="<?php echo htmlspecialchars($local['nome']); ?>" <?php echo ($entrada['local_armazenamento'] == $local['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($local['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="responsavel_recebimento_id">Responsável Recebimento:</label>
            <select id="responsavel_recebimento_id" name="responsavel_recebimento_id">
                <option value="">Selecione</option>
                <?php foreach($operadores as $operador): ?>
                    <option value="<?php echo $operador['id']; ?>" <?php echo ($entrada['responsavel_recebimento_id'] == $operador['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($operador['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group full-width">
            <label for="observacoes">Observações:</label>
            <textarea id="observacoes" name="observacoes"><?php echo htmlspecialchars($entrada['observacoes']); ?></textarea>
        </div>
        
        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Salvar Alterações</button>
        </div>
    </form>

    <a href="index.php" class="back-link">Voltar para a lista de Entradas</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
ob_end_flush();
?>
