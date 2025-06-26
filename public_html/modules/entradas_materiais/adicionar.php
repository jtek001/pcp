<?php
// modules/entradas_materiais/adicionar.php
session_start();
ob_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... (Lógica de inserção permanece a mesma)
    $produto_id = filter_input(INPUT_POST, 'produto_id_hidden', FILTER_VALIDATE_INT);
    $fornecedor_id = filter_input(INPUT_POST, 'fornecedor_id', FILTER_VALIDATE_INT);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
    $valor_unitario = filter_input(INPUT_POST, 'valor_unitario', FILTER_VALIDATE_FLOAT);
    $numero_nota_fiscal = sanitizeInput($_POST['numero_nota_fiscal']);
    $data_emissao_nota = sanitizeInput($_POST['data_emissao_nota']);
    $data_entrada = sanitizeInput($_POST['data_entrada']);
    $local_armazenamento = sanitizeInput($_POST['local_armazenamento']);
    $responsavel_id = filter_input(INPUT_POST, 'responsavel_recebimento_id', FILTER_VALIDATE_INT);
    $observacoes = sanitizeInput($_POST['observacoes']);

    if ($produto_id && $quantidade > 0 && $numero_nota_fiscal && $data_emissao_nota) {
        $conn->begin_transaction();
        try {
            $sql_entrada = "INSERT INTO materiais_insumos_entrada (produto_id, fornecedor_id, quantidade, valor_unitario, numero_nota_fiscal, data_emissao_nota, data_entrada, local_armazenamento, responsavel_recebimento_id, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $conn->execute_query($sql_entrada, [$produto_id, $fornecedor_id, $quantidade, $valor_unitario, $numero_nota_fiscal, $data_emissao_nota, $data_entrada, $local_armazenamento, $responsavel_id, $observacoes]);

            $conn->execute_query("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?", [$quantidade, $produto_id]);
            
            $sql_mov = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, 'entrada', ?, ?, ?, ?)";
            $conn->execute_query($sql_mov, [$produto_id, $quantidade, $data_entrada, "NF: $numero_nota_fiscal", $observacoes]);

            $conn->commit();
            $_SESSION['message'] = "Entrada de material registrada com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao registrar entrada: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Campos obrigatórios não preenchidos.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: adicionar.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
$fornecedores = $conn->query("SELECT id, nome FROM fornecedores_clientes_lookup WHERE (tipo = 'fornecedor' OR tipo = 'ambos') AND deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$operadores = $conn->query("SELECT id, nome FROM operadores WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
// OBSERVAÇÃO: Busca os locais de armazenamento para o novo dropdown
$localizacoes = $conn->query("SELECT nome FROM localizacoes_lookup ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<h2><i class="fas fa-plus-circle"></i> Nova Entrada de Material/Insumo</h2>

<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
<?php endif; ?>

<form action="adicionar.php" method="POST">
    <div class="form-group full-width">
        <label for="produto_search">Produto/Material:</label>
        <input type="text" id="produto_search" list="product_options" placeholder="Digite para buscar produto (código ou nome)" required>
        <datalist id="product_options"></datalist>
        <input type="hidden" id="produto_id_hidden" name="produto_id_hidden" required>
        <div id="produto-details" class="alert alert-info mt-2" style="display: none;">
            <strong>Estoque Atual:</strong> <span id="produto-estoque"></span>
        </div>
    </div>
    
    <div class="form-group">
        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" step="0.01" required min="0.01">
    </div>
    <div class="form-group">
        <label for="valor_unitario">Valor Unitário:</label>
        <input type="number" id="valor_unitario" name="valor_unitario" step="0.0001">
    </div>
    <div class="form-group">
        <label for="numero_nota_fiscal">Número da Nota Fiscal:</label>
        <input type="text" id="numero_nota_fiscal" name="numero_nota_fiscal" required>
    </div>
    <div class="form-group">
        <label for="data_emissao_nota">Data Emissão NF:</label>
        <input type="date" id="data_emissao_nota" name="data_emissao_nota" value="<?php echo date('Y-m-d'); ?>" required>
    </div>
    <div class="form-group">
        <label for="data_entrada">Data de Entrada:</label>
        <input type="datetime-local" id="data_entrada" name="data_entrada" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
    </div>
    <div class="form-group">
        <label for="fornecedor_id">Fornecedor:</label>
        <select id="fornecedor_id" name="fornecedor_id">
            <option value="">Selecione</option>
            <?php foreach($fornecedores as $fornecedor): ?>
                <option value="<?php echo $fornecedor['id']; ?>"><?php echo htmlspecialchars($fornecedor['nome']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- ALTERAÇÃO: Campo de texto trocado por um dropdown -->
    <div class="form-group">
        <label for="local_armazenamento">Local Armazenamento:</label>
        <select id="local_armazenamento" name="local_armazenamento">
            <option value="">Selecione</option>
            <?php foreach($localizacoes as $local): ?>
                <option value="<?php echo htmlspecialchars($local['nome']); ?>"><?php echo htmlspecialchars($local['nome']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="responsavel_recebimento_id">Responsável Recebimento:</label>
        <select id="responsavel_recebimento_id" name="responsavel_recebimento_id">
            <option value="">Selecione</option>
            <?php foreach($operadores as $operador): ?>
                <option value="<?php echo $operador['id']; ?>"><?php echo htmlspecialchars($operador['nome']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes"></textarea>
    </div>
    
    <button type="submit" class="button submit">Registrar Entrada</button>
</form>

<a href="index.php" class="back-link">Voltar para a lista de Entradas</a>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>';
    const produtoSearchInput = document.getElementById('produto_search');
    const productOptionsDatalist = document.getElementById('product_options');
    const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
    const produtoDetailsDiv = document.getElementById('produto-details');
    const produtoEstoqueSpan = document.getElementById('produto-estoque');

    let debounceTimeout;

    function resetProductInfo() {
        produtoIdHiddenInput.value = '';
        produtoDetailsDiv.style.display = 'none';
    }

    produtoSearchInput.addEventListener('input', function() {
        resetProductInfo();
        const searchTerm = this.value;
        if (searchTerm.length < 2) { 
            productOptionsDatalist.innerHTML = ''; 
            return;
        }

        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            fetch(`${baseUrl}/modules/entradas_materiais/ajax_get_products_for_materials.php?term=${encodeURIComponent(searchTerm)}`) 
                .then(response => response.json())
                .then(data => {
                    productOptionsDatalist.innerHTML = ''; 
                    data.forEach(product => {
                        const option = document.createElement('option');
                        option.value = product.text; 
                        option.setAttribute('data-id', product.id);
                        option.setAttribute('data-stock', product.stock);
                        productOptionsDatalist.appendChild(option);
                    });
                })
                .catch(error => console.error('Erro ao buscar produtos:', error));
        }, 300); 
    });

    produtoSearchInput.addEventListener('change', function() {
        const selectedOption = Array.from(productOptionsDatalist.options).find(option => option.value === this.value);
        if (selectedOption) {
            produtoIdHiddenInput.value = selectedOption.getAttribute('data-id');
            produtoEstoqueSpan.textContent = parseFloat(selectedOption.getAttribute('data-stock')).toFixed(2);
            produtoDetailsDiv.style.display = 'block';
        } else {
            resetProductInfo();
        }
    });
});
</script>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
