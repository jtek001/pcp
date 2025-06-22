<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $tipo_movimentacao = sanitizeInput($_POST['tipo_movimentacao']);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
    $observacoes = sanitizeInput($_POST['observacoes']);

    if ($produto_id && $tipo_movimentacao && $quantidade > 0) {
        $conn->begin_transaction();
        try {
            // 1. Atualizar o estoque na tabela de produtos
            if ($tipo_movimentacao === 'entrada' || $tipo_movimentacao === 'ajuste_entrada') {
                $sql_update_stock = "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?";
            } else { // saida ou ajuste_saida
                $sql_update_stock = "UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?";
            }
            $conn->execute_query($sql_update_stock, [$quantidade, $produto_id]);

            // 2. Registrar a movimentação
            $sql_movimentacao = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?)";
            $origem_destino = "Mov. Manual";
            $conn->execute_query($sql_movimentacao, [$produto_id, $tipo_movimentacao, $quantidade, $origem_destino, $observacoes]);
            
            $conn->commit();
            $_SESSION['message'] = "Movimentação de estoque registrada com sucesso!";
            $_SESSION['message_type'] = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao registrar movimentação: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Todos os campos são obrigatórios e a quantidade deve ser maior que zero.";
        $_SESSION['message_type'] = "warning";
    }

    header("Location: movimentar.php");
    exit();
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-arrows-alt-h"></i> Movimentação Manual de Estoque</h2>
    
    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="movimentar.php" method="POST">
        <div class="form-group full-width">
            <label for="produto_search">Buscar Produto (Nome ou Código):</label>
            <input type="text" id="produto_search" placeholder="Digite para buscar um produto...">
            <div id="produto_results" class="list-group mt-1"></div>
            <input type="hidden" name="produto_id" id="produto_id" required>
        </div>
        
        <div id="produto-details" class="full-width" style="display: none;">
            <div class="alert alert-info">
                <p><strong>Produto Selecionado:</strong> <span id="produto-nome"></span></p>
                <p><strong>Estoque Atual:</strong> <span id="produto-estoque"></span></p>
            </div>
        </div>

        <div class="form-group">
            <label for="tipo_movimentacao">Tipo de Movimentação</label>
            <select name="tipo_movimentacao" id="tipo_movimentacao" required>
                <option value="entrada">Entrada</option>
                <option value="saida">Saída</option>
                <option value="ajuste_entrada">Ajuste (Entrada)</option>
                <option value="ajuste_saida">Ajuste (Saída)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="quantidade">Quantidade</label>
            <input type="number" name="quantidade" id="quantidade" step="0.01" required>
        </div>
        
        <div class="form-group full-width">
            <label for="observacoes">Observações / Justificativa</label>
            <textarea name="observacoes" id="observacoes" rows="3" required></textarea>
        </div>

        <div class="full-width" style="text-align: center;">
            <button type="submit" class="button submit">Registrar Movimentação</button>
        </div>
    </form>
    
    <a href="index.php" class="back-link">Voltar para a Visão Geral</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>';
    const produtoSearchInput = document.getElementById('produto_search');
    const produtoResultsDiv = document.getElementById('produto_results');
    const produtoIdInput = document.getElementById('produto_id');

    const produtoDetailsDiv = document.getElementById('produto-details');
    const produtoNomeSpan = document.getElementById('produto-nome');
    const produtoEstoqueSpan = document.getElementById('produto-estoque');

    produtoSearchInput.addEventListener('keyup', function() {
        const query = this.value;
        if (query.length < 2) {
            produtoResultsDiv.innerHTML = '';
            produtoResultsDiv.style.display = 'none';
            return;
        }
        fetch(`${baseUrl}/modules/estoque/ajax_get_produtos_for_mov.php?q=${query}`)
            .then(response => response.json())
            .then(data => {
                produtoResultsDiv.innerHTML = '';
                if (data.length > 0) {
                     produtoResultsDiv.style.display = 'block';
                }
                data.forEach(produto => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = `${produto.nome} (Cód: ${produto.codigo})`;
                    item.onclick = (e) => {
                        e.preventDefault();
                        produtoSearchInput.value = item.textContent;
                        produtoIdInput.value = produto.id;
                        produtoNomeSpan.textContent = produto.nome;
                        produtoEstoqueSpan.textContent = produto.estoque_atual;
                        produtoDetailsDiv.style.display = 'block';
                        produtoResultsDiv.innerHTML = '';
                        produtoResultsDiv.style.display = 'none';
                    };
                    produtoResultsDiv.appendChild(item);
                });
            });
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
$conn->close();
ob_end_flush();
?>
