<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lote_numero = sanitizeInput($_POST['lote_numero']);
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
    $nota_fiscal = sanitizeInput($_POST['nota_fiscal']);
    $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
    $data_saida = sanitizeInput($_POST['data_saida']);

    if ($produto_id && $pedido_id && $quantidade > 0 && !empty($nota_fiscal) && $operador_id) {
        $conn->begin_transaction();
        try {
            $cliente_id = $conn->execute_query("SELECT cliente_id FROM pedidos_venda WHERE id = ?", [$pedido_id])->fetch_assoc()['cliente_id'];
            if (!$cliente_id) {
                throw new Exception("Cliente não encontrado para o pedido selecionado.");
            }

            $sql_saldo = "SELECT SUM(CASE WHEN tipo_movimentacao = 'entrada' THEN quantidade ELSE -quantidade END) as saldo FROM expedicao_log WHERE lote_numero = ?";
            $saldo_lote = $conn->execute_query($sql_saldo, [$lote_numero])->fetch_assoc()['saldo'] ?? 0;

            if ($quantidade > $saldo_lote) {
                throw new Exception("A quantidade de saída não pode ser maior que o saldo do lote na expedição.");
            }

            $conn->execute_query("UPDATE produtos SET estoque_expedicao = GREATEST(0, estoque_expedicao - ?) WHERE id = ?", [$quantidade, $produto_id]);

            // OBSERVAÇÃO: A query agora salva o pedido_venda_id
            $sql_log = "INSERT INTO expedicao_log (produto_id, lote_numero, tipo_movimentacao, quantidade, cliente_id, pedido_venda_id, nota_fiscal_saida, operador_id, data_movimentacao) VALUES (?, ?, 'saida', ?, ?, ?, ?, ?, ?)";
            $conn->execute_query($sql_log, [$produto_id, $lote_numero, $quantidade, $cliente_id, $pedido_id, $nota_fiscal, $operador_id, $data_saida]);

            $conn->commit();
            $_SESSION['message'] = "Saída da expedição registrada com sucesso!";
            $_SESSION['message_type'] = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao registrar saída: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: saida.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
$pedidos = $conn->query("SELECT pv.id, pv.numero_pedido, fc.nome as cliente_nome FROM pedidos_venda pv JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id WHERE pv.status = 'Aprovado' AND pv.deleted_at IS NULL ORDER BY pv.id DESC")->fetch_all(MYSQLI_ASSOC);
$operadores = $conn->query("SELECT id, nome FROM operadores WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-sign-out-alt"></i> Registrar Sada da Expedição</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="saida.php" method="POST">
        <div class="form-group full-width">
            <label for="lote_search">Buscar Lote na Expedição:</label>
            <input type="text" id="lote_search" name="lote_numero" placeholder="Digite o número do lote e saia do campo" class="form-control" required>
            <input type="hidden" name="produto_id" id="produto_id" required>
        </div>

        <div id="lote-details" class="full-width" style="display: none;">
            <div class="alert alert-info">
                <p><strong>Produto:</strong> <span id="produto-nome"></span></p>
                <p><strong>Saldo na Expedição:</strong> <span id="produto-qtd"></span></p>
            </div>
        </div>

        <div class="form-group full-width">
            <label for="pedido_id">Pedido (Aprovados)*</label>
            <select name="pedido_id" id="pedido_id" class="form-select" required>
                <option value="">Selecione o pedido</option>
                <?php foreach ($pedidos as $pedido): ?>
                <option value="<?php echo $pedido['id']; ?>"><?php echo htmlspecialchars('Nº ' . $pedido['numero_pedido'] . ' - ' . $pedido['cliente_nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-4 form-group">
                <label for="nota_fiscal">Nota Fiscal*</label>
                <input type="text" name="nota_fiscal" id="nota_fiscal" class="form-control" required>
            </div>
            <div class="col-md-4 form-group">
                <label for="quantidade">Quantidade*</label>
                <input type="number" name="quantidade" id="quantidade" class="form-control" step="0.01" required>
            </div>
            <div class="col-md-4 form-group">
                <label for="operador_id">Responsável*</label>
                <select name="operador_id" id="operador_id" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($operadores as $operador): ?>
                    <option value="<?php echo $operador['id']; ?>"><?php echo htmlspecialchars($operador['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12 form-group">
                <label for="data_saida">Data da Saída*</label>
                <input type="datetime-local" name="data_saida" id="data_saida" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
        </div>
        
        <div class="full-width" style="text-align: center; margin-top: 20px;">
            <button type="submit" class="button submit">Registrar Saída</button>
        </div>
    </form>
    
    <a href="index.php" class="back-link">Voltar para o Módulo de Expedição</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>';
    const loteSearchInput = document.getElementById('lote_search');
    const produtoIdInput = document.getElementById('produto_id');
    const loteDetailsDiv = document.getElementById('lote-details');
    const produtoNomeSpan = document.getElementById('produto-nome');
    const produtoQtdSpan = document.getElementById('produto-qtd');
    const quantidadeInput = document.getElementById('quantidade');

    loteSearchInput.addEventListener('blur', function() {
        const loteNumero = this.value.trim();
        if (loteNumero === '') return;

        fetch(`${baseUrl}/modules/expedicao/ajax_get_lote_expedicao.php?lote=${loteNumero}`)
            .then(response => {
                if (!response.ok) return response.json().then(err => Promise.reject(err));
                return response.json();
            })
            .then(lote => {
                produtoIdInput.value = lote.produto_id;
                produtoNomeSpan.textContent = `${lote.produto_nome} (${lote.produto_codigo})`;
                produtoQtdSpan.textContent = lote.saldo_disponivel;
                quantidadeInput.value = lote.saldo_disponivel;
                quantidadeInput.max = lote.saldo_disponivel;
                loteDetailsDiv.style.display = 'block';
            })
            .catch(error => {
                alert(error.error || 'Erro ao buscar lote.');
                this.value = '';
                loteDetailsDiv.style.display = 'none';
                produtoIdInput.value = '';
            });
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
ob_end_flush();
?>
