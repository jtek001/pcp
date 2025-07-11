<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apontamento_id = filter_input(INPUT_POST, 'apontamento_id', FILTER_VALIDATE_INT);
    $quantidade_movida = filter_input(INPUT_POST, 'quantidade_movida', FILTER_VALIDATE_FLOAT);
    $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);

    if ($apontamento_id && $quantidade_movida > 0 && $operador_id) {
        $conn->begin_transaction();
        try {
            $sql_lote = "SELECT ap.*, op.produto_id FROM apontamentos_producao ap JOIN ordens_producao op ON ap.ordem_producao_id = op.id WHERE ap.id = ?";
            $lote_data = $conn->execute_query($sql_lote, [$apontamento_id])->fetch_assoc();

            if (!$lote_data || $quantidade_movida > $lote_data['quantidade_produzida']) {
                throw new Exception("A quantidade a mover não pode ser maior que o saldo do lote.");
            }
            
            // 1. Subtrai do estoque de "produto acabado" (que é o saldo do lote)
            $conn->execute_query("UPDATE apontamentos_producao SET quantidade_produzida = quantidade_produzida - ? WHERE id = ?", [$quantidade_movida, $apontamento_id]);
            
            // 2. Adiciona ao estoque da expedição
            $conn->execute_query("UPDATE produtos SET estoque_expedicao = estoque_expedicao + ? WHERE id = ?", [$quantidade_movida, $lote_data['produto_id']]);

            // 3. Regista no novo log de expedição
            $sql_log = "INSERT INTO expedicao_log (produto_id, lote_numero, tipo_movimentacao, quantidade, operador_id) VALUES (?, ?, 'entrada', ?, ?)";
            $conn->execute_query($sql_log, [$lote_data['produto_id'], $lote_data['lote_numero'], $quantidade_movida, $operador_id]);

            $conn->commit();
            $_SESSION['message'] = "Entrada na expedição registrada com sucesso!";
            $_SESSION['message_type'] = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao registrar entrada: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: entrada.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
$operadores = $conn->query("SELECT id, nome FROM operadores WHERE ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2><i class="fas fa-sign-in-alt"></i> Registrar Entrada na Expedição</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="entrada.php" method="POST">
        <div class="form-group full-width">
            <label for="lote_search">Buscar Lote de Produção:</label>
            <input type="text" id="lote_search" placeholder="Digite o número do lote e saia do campo" class="form-control" required>
            <input type="hidden" name="apontamento_id" id="apontamento_id" required>
        </div>

        <div id="lote-details" class="full-width" style="display: none;">
            <div class="alert alert-info">
                <p><strong>Produto:</strong> <span id="produto-nome"></span></p>
                <p><strong>Saldo do Lote:</strong> <span id="produto-qtd"></span></p>
            </div>
        </div>

        <div class="form-group">
            <label for="quantidade_movida">Quantidade a Mover para Expedição</label>
            <input type="number" name="quantidade_movida" id="quantidade_movida" class="form-control" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="operador_id">Operador Responsável</label>
            <select name="operador_id" id="operador_id" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($operadores as $operador): ?>
                <option value="<?php echo $operador['id']; ?>"><?php echo htmlspecialchars($operador['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="full-width" style="text-align: center; margin-top: 20px;">
            <button type="submit" class="button submit">Registrar Entrada</button>
        </div>
    </form>
    
    <a href="index.php" class="back-link">Voltar para o Módulo de Expedição</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>';
    const loteSearchInput = document.getElementById('lote_search');
    const apontamentoIdInput = document.getElementById('apontamento_id');
    const loteDetailsDiv = document.getElementById('lote-details');
    const produtoNomeSpan = document.getElementById('produto-nome');
    const produtoQtdSpan = document.getElementById('produto-qtd');
    const quantidadeMovidaInput = document.getElementById('quantidade_movida');

    loteSearchInput.addEventListener('blur', function() {
        const loteNumero = this.value.trim();
        if (loteNumero === '') return;

        fetch(`${baseUrl}/modules/expedicao/ajax_get_lote_para_entrada.php?lote=${loteNumero}`)
            .then(response => {
                if (!response.ok) return response.json().then(err => Promise.reject(err));
                return response.json();
            })
            .then(lote => {
                apontamentoIdInput.value = lote.id;
                produtoNomeSpan.textContent = `${lote.produto_nome} (${lote.produto_codigo})`;
                produtoQtdSpan.textContent = lote.quantidade_produzida;
                quantidadeMovidaInput.value = lote.quantidade_produzida;
                quantidadeMovidaInput.max = lote.quantidade_produzida;
                loteDetailsDiv.style.display = 'block';
            })
            .catch(error => {
                alert(error.error || 'Erro ao buscar lote.');
                this.value = '';
                loteDetailsDiv.style.display = 'none';
                apontamentoIdInput.value = '';
            });
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
ob_end_flush();
?>
