<?php
// modules/estoque/movimentar.php
// Esta página permite registrar movimentações manuais de estoque (entrada, saída, ajuste).

// Inicia a sessão para usar variáveis de sessão
session_start();

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Brasília.
date_default_timezone_set('America/Sao_Paulo');

// Inclui os arquivos de configuração e o cabeçalho
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variáveis para mensagens de sucesso/erro
$message = '';
$message_type = '';

// Recupera mensagens da sessão se existirem
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Busca operadores para o dropdown (responsável pela movimentação)
$operadores = [];
$sql_operadores = "SELECT id, nome, matricula FROM operadores WHERE deleted_at IS NULL ORDER BY nome ASC";
try {
    $result_operadores = $conn->execute_query($sql_operadores);
    if ($result_operadores) {
        while ($row = $result_operadores->fetch_assoc()) {
            $operadores[] = $row;
        }
        $result_operadores->free();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro ao buscar operadores: " . $e->getMessage());
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitiza e valida as entradas
    $temp_produto_id = sanitizeInput(isset($_POST['produto_id_hidden']) ? $_POST['produto_id_hidden'] : '');
    $temp_tipo_movimentacao = sanitizeInput(isset($_POST['tipo_movimentacao']) ? $_POST['tipo_movimentacao'] : '');
    $temp_quantidade = (float) sanitizeInput(isset($_POST['quantidade']) ? $_POST['quantidade'] : 0.0);
    $temp_data_hora_movimentacao = sanitizeInput(isset($_POST['data_hora_movimentacao']) ? $_POST['data_hora_movimentacao'] : date('Y-m-d H:i:s'));
    $temp_origem_destino = sanitizeInput(isset($_POST['origem_destino']) ? $_POST['origem_destino'] : '');
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');
    $temp_responsavel_id = sanitizeInput(isset($_POST['responsavel_id']) ? $_POST['responsavel_id'] : '');

    // Validação básica
    if (empty($temp_produto_id) || empty($temp_tipo_movimentacao) || empty($temp_quantidade) || $temp_quantidade <= 0 || empty($temp_responsavel_id)) {
        $message = "Produto, Tipo de Movimentação, Quantidade (maior que zero) e Responsável são campos obrigatórios.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // 1. Registrar a movimentação de estoque
            $sql_mov = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
            
            $params_mov = [
                $temp_produto_id,
                $temp_tipo_movimentacao,
                $temp_quantidade,
                $temp_data_hora_movimentacao,
                $temp_origem_destino,
                $temp_observacoes
            ];
            $conn->execute_query($sql_mov, $params_mov);

            // 2. Atualizar o estoque do produto
            $sql_update_estoque = "UPDATE produtos SET estoque_atual = estoque_atual ";
            if ($temp_tipo_movimentacao === 'entrada' || ($temp_tipo_movimentacao === 'ajuste' && $temp_quantidade > 0)) {
                $sql_update_estoque .= "+ ?";
            } elseif ($temp_tipo_movimentacao === 'saida' || ($temp_tipo_movimentacao === 'ajuste' && $temp_quantidade < 0)) {
                $sql_update_estoque .= "- ?"; // Para ajuste negativo, ou saída
                $temp_quantidade = abs($temp_quantidade); // Garante que a quantidade seja positiva para subtração
            }
            $sql_update_estoque .= " WHERE id = ?";

            $params_update_estoque = [
                $temp_quantidade,
                $temp_produto_id
            ];
            $conn->execute_query($sql_update_estoque, $params_update_estoque);

            $conn->commit();
            $message = "Movimentação de estoque registrada com sucesso! Estoque atualizado.";
            $message_type = "success";
            $_POST = array(); // Limpa o formulário

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro ao registrar movimentação de estoque (SQL): " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal ao inserir movimentação de estoque: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Variáveis para manter o estado dos campos de pesquisa de produto em caso de erro no POST
$initial_produto_search_value = htmlspecialchars($post_values['produto_search'] ?? '');
$initial_produto_id_hidden = htmlspecialchars($post_values['produto_id_hidden'] ?? '');
$current_product_stock = ''; 
// Se um produto já foi selecionado (vindo de um POST com erro), busque o estoque dele
if (isset($post_values['produto_id_hidden']) && !empty($post_values['produto_id_hidden'])) {
    $sql_get_stock = "SELECT estoque_atual FROM produtos WHERE id = ?";
    $result_stock = $conn->execute_query($sql_get_stock, [$post_values['produto_id_hidden']]);
    if ($result_stock && $row_stock = $result_stock->fetch_assoc()) {
        $current_product_stock = $row_stock['estoque_atual'];
        $result_stock->free();
    }
}

// Valor padrão para Data/Hora da Movimentação
$default_data_hora_mov = date('Y-m-d\TH:i'); 

?>

<h2>Registrar Movimentação de Estoque</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="movimentar.php" method="POST">
    <div class="form-group">
        <label for="produto_search">Produto/Material:</label>
        <input type="text" id="produto_search" list="product_options" value="<?php echo $initial_produto_search_value; ?>" required placeholder="Digite para buscar produto (código ou nome)">
        <datalist id="product_options">
            <!-- Opções preenchidas via JavaScript -->
            <?php 
            if (!empty($initial_produto_id_hidden)) {
                $sql_selected_product = "SELECT id, nome, codigo, estoque_atual FROM produtos WHERE id = ? AND deleted_at IS NULL"; 
                $result_selected_product = $conn->execute_query($sql_selected_product, [$initial_produto_id_hidden]);
                if ($result_selected_product && $row_selected_product = $result_selected_product->fetch_assoc()) {
                    echo '<option data-id="' . htmlspecialchars($row_selected_product['id']) . '" data-stock="' . htmlspecialchars($row_selected_product['estoque_atual']) . '" value="' . htmlspecialchars($row_selected_product['nome'] . ' (' . $row_selected_product['codigo'] . ')') . '"></option>';
                }
            }
            ?>
        </datalist>
        <input type="hidden" id="produto_id_hidden" name="produto_id_hidden" value="<?php echo $initial_produto_id_hidden; ?>" required>
    </div>

    <div class="form-group">
        <label for="quantidade_em_estoque">Estoque Atual do Produto:</label>
        <input type="number" id="quantidade_em_estoque" name="quantidade_em_estoque" step="0.01" value="<?php echo htmlspecialchars($current_product_stock); ?>" readonly placeholder="Estoque atual">
    </div>

    <div class="form-group">
        <label for="tipo_movimentacao">Tipo de Movimentação:</label>
        <select id="tipo_movimentacao" name="tipo_movimentacao" required>
            <option value="">Selecione</option>
            <option value="entrada" <?php echo (isset($post_values['tipo_movimentacao']) && $post_values['tipo_movimentacao'] == 'entrada') ? 'selected' : ''; ?>>Entrada</option>
            <option value="saida" <?php echo (isset($post_values['tipo_movimentacao']) && $post_values['tipo_movimentacao'] == 'saida') ? 'selected' : ''; ?>>Saída</option>
            <option value="ajuste" <?php echo (isset($post_values['tipo_movimentacao']) && $post_values['tipo_movimentacao'] == 'ajuste') ? 'selected' : ''; ?>>Ajuste</option>
        </select>
    </div>

    <div class="form-group">
        <label for="quantidade">Quantidade da Movimentação:</label>
        <input type="number" id="quantidade" name="quantidade" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade'] ?? '0.00'); ?>" required min="0.01" placeholder="Ex: 10.00">
    </div>

    <div class="form-group">
        <label for="data_hora_movimentacao">Data/Hora da Movimentação:</label>
        <input type="datetime-local" id="data_hora_movimentacao" name="data_hora_movimentacao" value="<?php echo htmlspecialchars($post_values['data_hora_movimentacao'] ?? $default_data_hora_mov); ?>" required>
    </div>

    <div class="form-group">
        <label for="responsavel_id">Responsável:</label>
        <select id="responsavel_id" name="responsavel_id" required>
            <option value="">Selecione um Operador</option>
            <?php foreach ($operadores as $operador): ?>
                <option value="<?php echo htmlspecialchars($operador['id']); ?>" <?php echo (isset($post_values['responsavel_id']) && $post_values['responsavel_id'] == $operador['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($operador['nome'] . ' (' . $operador['matricula'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group full-width">
        <label for="origem_destino">Origem/Destino (Opcional):</label>
        <input type="text" id="origem_destino" name="origem_destino" value="<?php echo htmlspecialchars($post_values['origem_destino'] ?? ''); ?>" maxlength="100" placeholder="Ex: Fornecedor X, Cliente Y, Estoque Antigo">
    </div>

    <div class="form-group full-width">
        <label for="observacoes">Observações (Opcional):</label>
        <textarea id="observacoes" name="observacoes" placeholder="Detalhes adicionais sobre esta movimentação..."><?php echo htmlspecialchars($post_values['observacoes'] ?? ''); ?></textarea>
    </div>

    <button type="submit" class="button submit">Registrar Movimentação</button>
</form>

<a href="index.php" class="back-link">Voltar para o Estoque</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const produtoSearchInput = document.getElementById('produto_search');
        const productOptionsDatalist = document.getElementById('product_options');
        const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
        const quantidadeEmEstoqueInput = document.getElementById('quantidade_em_estoque');
        const baseUrl = '<?php echo BASE_URL; ?>'; 

        let debounceTimeout;

        function fetchProductsForSearch(searchTerm) {
            if (searchTerm.length < 2) { 
                productOptionsDatalist.innerHTML = ''; 
                return;
            }

            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                fetch(`${baseUrl}/modules/materiais/get_products_for_materials.php?term=${encodeURIComponent(searchTerm)}`) 
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
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
        }

        produtoSearchInput.addEventListener('input', function() {
            produtoIdHiddenInput.value = '';
            quantidadeEmEstoqueInput.value = '0.00'; 
            fetchProductsForSearch(this.value);
        });

        produtoSearchInput.addEventListener('change', function() {
            const selectedOption = Array.from(productOptionsDatalist.options).find(option => option.value === this.value);
            if (selectedOption) {
                produtoIdHiddenInput.value = selectedOption.getAttribute('data-id');
                quantidadeEmEstoqueInput.value = parseFloat(selectedOption.getAttribute('data-stock')).toFixed(2);
            } else {
                produtoIdHiddenInput.value = '';
                quantidadeEmEstoqueInput.value = '0.00';
            }
        });

        const initialProductIdHidden = '<?php echo htmlspecialchars($post_values["produto_id_hidden"] ?? ""); ?>';
        const initialProductSearchValue = '<?php echo htmlspecialchars($post_values["produto_search"] ?? ""); ?>';
        const initialProductStockValue = '<?php echo htmlspecialchars($current_product_stock); ?>';

        if (initialProductIdHidden) {
            produtoSearchInput.value = initialProductSearchValue;
            produtoIdHiddenInput.value = initialProductIdHidden;
            quantidadeEmEstoqueInput.value = initialProductStockValue;
        }
    });
</script>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
