<?php
// modules/empenho_manual/adicionar.php
// Esta página permite registrar o empenho manual e o desempenho de materiais.

// Inicia a sessão para usar variáveis de sessão
session_start();

// Habilita a exibião de todos os erros PHP para depuraço (REMOVER EM PRODUÇO)
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
$sql_operadores = "SELECT id, nome, matricula FROM operadores WHERE ativo = 1 and deleted_at IS NULL ORDER BY nome ASC";
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
    $temp_tipo_empenho = sanitizeInput(isset($_POST['tipo_empenho']) ? $_POST['tipo_empenho'] : ''); // 'empenhar' ou 'desempenhar'
    $temp_quantidade = (float) sanitizeInput(isset($_POST['quantidade']) ? $_POST['quantidade'] : 0.0);
    $temp_numero_op_manual_text = sanitizeInput(isset($_POST['op_search']) ? $_POST['op_search'] : ''); // Texto digitado/selecionado da OP
    $temp_op_id_hidden = sanitizeInput(isset($_POST['op_id_hidden']) ? $_POST['op_id_hidden'] : ''); // ID da OP do campo hidden
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');
    $temp_responsavel_id = sanitizeInput(isset($_POST['responsavel_id']) ? $_POST['responsavel_id'] : '');
    $temp_data_hora_movimentacao = sanitizeInput(isset($_POST['data_hora_movimentacao']) ? $_POST['data_hora_movimentacao'] : date('Y-m-d H:i:s'));


    // Validação básica
    if (empty($temp_produto_id) || empty($temp_tipo_empenho) || empty($temp_quantidade) || $temp_quantidade <= 0 || empty($temp_op_id_hidden) || empty($temp_responsavel_id)) {
        $message = "Produto, Tipo de Movimentação, Quantidade (maior que zero), Ordem de Produção e Responsável são campos obrigatórios.";
        $message_type = "error";
    } else {
        // Usa o ID da OP do campo hidden (que é o ID real do banco)
        $ordem_producao_real_id = (int)$temp_op_id_hidden;
        
        // Inicia transação
        $conn->begin_transaction();
        try {
            $current_stock_empenhado = 0;
            $current_stock_atual = 0;
            
            // Busca o estoque atual e empenhado do produto
            $sql_get_product_stock = "SELECT estoque_atual, estoque_empenhado FROM produtos WHERE id = ? AND deleted_at IS NULL";
            $result_stock = $conn->execute_query($sql_get_product_stock, [$temp_produto_id]);
            if ($result_stock && $row_stock = $result_stock->fetch_assoc()) {
                $current_stock_atual = (float)$row_stock['estoque_atual'];
                $current_stock_empenhado_total = (float)$row_stock['estoque_empenhado'];
                $result_stock->free();
            } else {
                throw new mysqli_sql_exception("Produto selecionado não encontrado ou inativo para empenho.");
            }

            $new_stock_empenhado_total = $current_stock_empenhado; // Inicia com o valor atual
            $mov_tipo = ''; // Para o registro na movimentacoes_estoque

            // Lógica de Empenho / Desempenho
            if ($temp_tipo_empenho === 'empenhar') {
                // Verifica se há estoque livre suficiente para empenhar
                if (($current_stock_atual - $current_stock_empenhado_total) < $temp_quantidade) {
                    throw new mysqli_sql_exception("Estoque livre insuficiente para empenhar " . number_format($temp_quantidade, 2) . ". Disponível: " . number_format(($current_stock_atual - $current_stock_empenhado_total), 2));
                }
                $new_stock_empenhado_total += $temp_quantidade;

                // Inserir ou atualizar empenho na tabela empenho_materiais
                // ON DUPLICATE KEY UPDATE: Se já existe um empenho para este produto e OP, atualiza.
                // --- INÍCIO DA ALTERAÇÃO: Adicionar quantidade_inicial no INSERT (09/06/2025 - IA) ---
                $sql_empenho = "INSERT INTO empenho_materiais (produto_id, ordem_producao_id, quantidade_empenhada, quantidade_inicial, observacoes, data_empenho) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantidade_empenhada = quantidade_empenhada + VALUES(quantidade_empenhada), quantidade_inicial = quantidade_inicial + VALUES(quantidade_inicial), data_empenho = VALUES(data_empenho), deleted_at = NULL";
                $conn->execute_query($sql_empenho, [$temp_produto_id, $ordem_producao_real_id, $temp_quantidade, $temp_quantidade, $temp_observacoes, $temp_data_hora_movimentacao]);
                // --- FIM DA ALTERAÇÃO: Adicionar quantidade_inicial ---
                $mov_tipo = 'empenho';

            } elseif ($temp_tipo_empenho === 'desempenhar') {
                // Verifica se há quantidade empenhada suficiente para desempenhar para esta OP
                $sql_check_empenho_op = "SELECT quantidade_empenhada FROM empenho_materiais WHERE produto_id = ? AND ordem_producao_id = ? AND deleted_at IS NULL";
                $result_check_empenho = $conn->execute_query($sql_check_empenho_op, [$temp_produto_id, $ordem_producao_real_id]);
                $empenho_op_qty = 0;
                if ($result_check_empenho && $row_empenho_op = $result_check_empenho->fetch_assoc()) {
                    $empenho_op_qty = (float)$row_empenho_op['quantidade_empenhada'];
                    $result_check_empenho->free();
                } else {
                    throw new mysqli_sql_exception("Não há empenho ativo deste material para a OP informada para desempenhar.");
                }

                if ($empenho_op_qty < $temp_quantidade) {
                     throw new mysqli_sql_exception("Quantidade empenhada para esta OP insuficiente para desempenhar " . number_format($temp_quantidade, 2) . ". Empenhado para esta OP: " . number_format($empenho_op_qty, 2));
                }

                $new_stock_empenhado_total -= $temp_quantidade;
                // Atualiza empenho na tabela empenho_materiais (reduz a quantidade)
                // Se a quantidade restante for <= 0, marca como deleted_at
                $sql_empenho = "UPDATE empenho_materiais SET quantidade_empenhada = quantidade_empenhada - ?, data_empenho = ?, deleted_at = CASE WHEN (quantidade_empenhada - ?) <= 0 THEN NOW() ELSE NULL END WHERE produto_id = ? AND ordem_producao_id = ? AND deleted_at IS NULL";
                $conn->execute_query($sql_empenho, [$temp_quantidade, $temp_data_hora_movimentacao, $temp_quantidade, $temp_produto_id, $ordem_producao_real_id]);
                $mov_tipo = 'desempenho';

            } else {
                throw new mysqli_sql_exception("Tipo de movimentação de empenho inválido.");
            }

            // Atualiza o estoque_empenhado TOTAL na tabela produtos
            // Garante que o estoque_empenhado nunca seja negativo
            $sql_update_produto_empenhado = "UPDATE produtos SET estoque_empenhado = GREATEST(0, ?) WHERE id = ?";
            $conn->execute_query($sql_update_produto_empenhado, [$new_stock_empenhado_total, $temp_produto_id]);

            // Registrar a movimentação de estoque (empenho/desempenho)
            $sql_mov = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
            
            $responsavel_nome_log = '';
            foreach ($operadores as $op_info) {
                if ($op_info['id'] == $temp_responsavel_id) {
                    $responsavel_nome_log = $op_info['nome'] . ' (' . $op_info['matricula'] . ')';
                    break;
                }
            }

            $origem_destino_log = ucfirst($mov_tipo) . " Manual OP: " . $temp_numero_op_manual_text . " (Resp: " . $responsavel_nome_log . ")";
            
            $conn->execute_query($sql_mov, [
                $temp_produto_id,
                $mov_tipo,
                abs($temp_quantidade), // Sempre registra quantidade positiva na movimentação
                $temp_data_hora_movimentacao,
                $origem_destino_log, 
                $temp_observacoes
            ]);

            $conn->commit();
            $message = ucfirst($temp_tipo_empenho) . " de material registrado com sucesso! Estoque empenhado atualizado.";
            $message_type = "success";
            $_POST = array(); // Limpa o formulário

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro na transação de empenho/desempenho: " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal em Empenho Manual: " . $e->getMessage());
        } catch (Exception $e) { // Captura exceções de lógica
            $conn->rollback();
            $message = "Erro: " . $e->getMessage();
            $message_type = "error";
            error_log("Erro lógico em Empenho Manual: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Variáveis para manter o estado dos campos de pesquisa de produto e OP
$initial_produto_search_value = htmlspecialchars($post_values['produto_search'] ?? '');
$initial_produto_id_hidden = htmlspecialchars($post_values['produto_id_hidden'] ?? '');
$current_product_stock_display = ''; 
$current_product_empenhado_display = ''; 
$current_product_livre_display = ''; 

// Busca estoque atual e empenhado do produto pré-selecionado (se houver)
if (!empty($initial_produto_id_hidden)) {
    $sql_get_stock_info = "SELECT estoque_atual, estoque_empenhado FROM produtos WHERE id = ? AND deleted_at IS NULL";
    $result_stock_info = $conn->execute_query($sql_get_stock_info, [$initial_produto_id_hidden]);
    if ($result_stock_info && $row_stock_info = $result_stock_info->fetch_assoc()) {
        $current_product_stock_display = number_format($row_stock_info['estoque_atual'], 2, ',', '.');
        $current_product_empenhado_display = number_format($row_stock_info['estoque_empenhado'], 2, ',', '.');
        $current_product_livre_display = number_format($row_stock_info['estoque_atual'] - $row_stock_info['estoque_empenhado'], 2, ',', '.');
        $result_stock_info->free();
    }
}

$initial_op_search_value = htmlspecialchars($post_values['op_search'] ?? '');
$initial_op_id_hidden = htmlspecialchars($post_values['op_id_hidden'] ?? '');

// Valor padrão para Data/Hora da Movimentação
$default_data_hora_mov = date('Y-m-d\TH:i'); 

?>

<h2>Controle Manual de Empenho de Materiais</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="adicionar.php" method="POST" id="empenhoForm"> <!-- Adicionado id="empenhoForm" -->
    <div class="form-group">
        <label for="produto_search">Produto/Material:</label>
        <input type="text" id="produto_search" list="product_options" value="<?php echo $initial_produto_search_value; ?>" required placeholder="Digite para buscar produto (código ou nome)">
        <datalist id="product_options">
            <!-- Opções preenchidas via JavaScript -->
            <?php 
            if (!empty($initial_produto_id_hidden)) {
                $sql_selected_product = "SELECT id, nome, codigo FROM produtos WHERE id = ? AND deleted_at IS NULL"; 
                $result_selected_product = $conn->execute_query($sql_selected_product, [$initial_produto_id_hidden]);
                if ($result_selected_product && $row_selected_product = $result_selected_product->fetch_assoc()) {
                    echo '<option data-id="' . htmlspecialchars($row_selected_product['id']) . '" value="' . htmlspecialchars($row_selected_product['nome'] . ' (' . $row_selected_product['codigo'] . ')') . '"></option>';
                }
            }
            ?>
        </datalist>
        <input type="hidden" id="produto_id_hidden" name="produto_id_hidden" value="<?php echo $initial_produto_id_hidden; ?>" required>
    </div>
<br>
    <div class="form-group">
        <label for="quantidade_em_estoque_display">Estoque Atual:</label>
        <input type="text" id="quantidade_em_estoque_display" value="<?php echo $current_product_stock_display; ?>" readonly placeholder="Estoque atual">
    </div>
    
    <div class="form-group">
        <label for="quantidade_empenhado_display">Estoque Empenhado:</label>
        <input type="text" id="quantidade_empenhado_display" value="<?php echo $current_product_empenhado_display; ?>" readonly placeholder="Estoque empenhado">
    </div>

    <div class="form-group">
        <label for="quantidade_livre_display">Estoque Livre:</label>
        <input type="text" id="quantidade_livre_display" value="<?php echo $current_product_livre_display; ?>" readonly placeholder="Estoque livre">
    </div>

    <div class="form-group">
        <label for="tipo_empenho">Tipo de Movimentação:</label>
        <select id="tipo_empenho" name="tipo_empenho" required>
            <option value="">Selecione</option>
            <option value="empenhar" <?php echo (isset($post_values['tipo_empenho']) && $post_values['tipo_empenho'] == 'empenhar') ? 'selected' : ''; ?>>Empenhar</option>
            <option value="desempenhar" <?php echo (isset($post_values['tipo_empenho']) && $post_values['tipo_empenho'] == 'desempenhar') ? 'selected' : ''; ?>>Desempenhar</option>
        </select>
    </div>

    <div class="form-group">
        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade'] ?? '0.00'); ?>" required min="0.01" placeholder="Ex: 10.00">
    </div>

    <div class="form-group">
        <label for="op_search">Ordem de Produão (OP):</label>
        <input type="text" id="op_search" list="op_options" value="<?php echo $initial_op_search_value; ?>" required placeholder="Digite para buscar OP">
        <datalist id="op_options">
            <!-- Opções preenchidas via JavaScript -->
            <?php 
            if (!empty($initial_op_id_hidden)) {
                $sql_selected_op = "SELECT id, numero_op FROM ordens_producao WHERE id = ? AND deleted_at IS NULL"; 
                $result_selected_op = $conn->execute_query($sql_selected_op, [$initial_op_id_hidden]);
                if ($result_selected_op && $row_selected_op = $result_selected_op->fetch_assoc()) {
                    echo '<option data-id="' . htmlspecialchars($row_selected_op['id']) . '" value="' . htmlspecialchars($row_selected_op['numero_op']) . '"></option>';
                }
            }
            ?>
        </datalist>
        <input type="hidden" id="op_id_hidden" name="op_id_hidden" value="<?php echo $initial_op_id_hidden; ?>" required>
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
        <label for="observacoes">Observações (Opcional):</label>
        <textarea id="observacoes" name="observacoes" placeholder="Detalhes adicionais sobre esta movimentação..."><?php echo htmlspecialchars($post_values['observacoes'] ?? ''); ?></textarea>
    </div>

    <button type="submit" class="button submit">Registrar Empenho Manual</button>
</form>

<a href="index.php" class="back-link">Voltar para o Estoque</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseUrl = '<?php echo BASE_URL; ?>'; 

        // --- Lógica para Busca de Produto ---
        const produtoSearchInput = document.getElementById('produto_search');
        const productOptionsDatalist = document.getElementById('product_options');
        const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
        const quantidadeEmEstoqueDisplay = document.getElementById('quantidade_em_estoque_display');
        const quantidadeEmpenhadoDisplay = document.getElementById('quantidade_empenhado_display');
        const quantidadeLivreDisplay = document.getElementById('quantidade_livre_display');
        let debounceTimeoutProduto;

        // Verifica se os elementos do produto existem antes de adicionar listeners
        if (produtoSearchInput && productOptionsDatalist && produtoIdHiddenInput && quantidadeEmEstoqueDisplay && quantidadeEmpenhadoDisplay && quantidadeLivreDisplay) {
            function fetchProductsForSearch(searchTerm) {
                if (searchTerm.length < 2) { 
                    productOptionsDatalist.innerHTML = ''; 
                    return;
                }
                clearTimeout(debounceTimeoutProduto);
                debounceTimeoutProduto = setTimeout(() => {
                    fetch(`${baseUrl}/modules/empenho_manual/get_products_for_empenho.php?term=${encodeURIComponent(searchTerm)}`) 
                        .then(response => {
                            if (!response.ok) {
                                // Se a resposta NÃO for OK, ela pode ser um erro HTTP (404, 500)
                                // ou um HTML de erro PHP. Tenta ler como texto para depuração.
                                return response.text().then(text => { throw new Error(`HTTP error! status: ${response.status}, Response: ${text}`); });
                            }
                            return response.json();
                        })
                        .then(data => {
                            productOptionsDatalist.innerHTML = ''; 
                            // Verifica se a resposta JSON tem uma chave 'error' (para erros PHP tratados)
                            if (data && data.error) {
                                console.error('Erro no endpoint de produtos:', data.error);
                                // Pode mostrar um alerta ou mensagem de erro ao usuário aqui
                                alert('Erro na busca de produtos: ' + data.error);
                                return;
                            }
                            data.forEach(product => {
                                const option = document.createElement('option');
                                option.value = product.text; 
                                option.setAttribute('data-id', product.id);
                                option.setAttribute('data-stock', product.stock); // Estoque atual
                                option.setAttribute('data-empenhado', product.empenhado); // Estoque empenhado
                                productOptionsDatalist.appendChild(option);
                            });
                        })
                        .catch(error => console.error('Erro ao buscar produtos:', error));
                }, 300); 
            }

            produtoSearchInput.addEventListener('input', function() {
                produtoIdHiddenInput.value = '';
                quantidadeEmEstoqueDisplay.value = '0,00'; 
                quantidadeEmpenhadoDisplay.value = '0,00'; 
                quantidadeLivreDisplay.value = '0,00'; 
                fetchProductsForSearch(this.value);
            });

            produtoSearchInput.addEventListener('change', function() {
                const selectedOption = Array.from(productOptionsDatalist.options).find(option => option.value === this.value);
                if (selectedOption) {
                    produtoIdHiddenInput.value = selectedOption.getAttribute('data-id');
                    const stockValue = parseFloat(selectedOption.getAttribute('data-stock')) || 0;
                    const empenhadoValue = parseFloat(selectedOption.getAttribute('data-empenhado')) || 0;
                    
                    // Atualiza os campos de estoque, empenhado e livre ao selecionar
                    quantidadeEmEstoqueDisplay.value = stockValue.toFixed(2).replace('.', ',');
                    quantidadeEmpenhadoDisplay.value = empenhadoValue.toFixed(2).replace('.', ',');
                    quantidadeLivreDisplay.value = (stockValue - empenhadoValue).toFixed(2).replace('.', ',');
                } else {
                    produtoIdHiddenInput.value = '';
                    quantidadeEmEstoqueDisplay.value = '0,00';
                    quantidadeEmpenhadoDisplay.value = '0,00';
                    quantidadeLivreDisplay.value = '0,00';
                }
            });

            // Re-preenche os campos de estoque/empenho/livre ao carregar se um produto já estiver selecionado (ex: após erro de POST)
            const initialProductIdHiddenValue = produtoIdHiddenInput.value;
            if (initialProductIdHiddenValue) {
                 updateStockDisplays(initialProductIdHiddenValue); // Função auxiliar para preencher os displays
            }
        } else {
            console.error("DEBUG: Elementos HTML para busca de produto não encontrados na página de Empenho Manual.");
        }


        // --- Lógica para Busca de Ordem de Produção (OP) ---
        const opSearchInput = document.getElementById('op_search');
        const opOptionsDatalist = document.getElementById('op_options');
        const opIdHiddenInput = document.getElementById('op_id_hidden');
        let debounceTimeoutOp;

        // Verifica se os elementos da OP existem antes de adicionar listeners
        if (opSearchInput && opOptionsDatalist && opIdHiddenInput) {
            function fetchOpsForSearch(searchTerm) {
                if (searchTerm.length < 2) { 
                    opOptionsDatalist.innerHTML = ''; 
                    return;
                }
                clearTimeout(debounceTimeoutOp);
                debounceTimeoutOp = setTimeout(() => {
                    fetch(`${baseUrl}/modules/empenho_manual/get_ops_for_empenho.php?term=${encodeURIComponent(searchTerm)}`) 
                        .then(response => {
                            if (!response.ok) {
                                // Se a resposta NO for OK, tenta ler como texto para depuração
                                return response.text().then(text => { throw new Error(`HTTP error! status: ${response.status}, Response: ${text}`); });
                            }
                            return response.json();
                        })
                        .then(data => {
                            opOptionsDatalist.innerHTML = ''; 
                            // Verifica se a resposta JSON tem uma chave 'error'
                            if (data && data.error) {
                                console.error('Erro no endpoint de OPs:', data.error);
                                alert('Erro na busca de OPs: ' + data.error); // Alerta ao usuário
                                return;
                            }
                            data.forEach(op => {
                                const option = document.createElement('option');
                                option.value = op.text; 
                                option.setAttribute('data-id', op.id);
                                opOptionsDatalist.appendChild(option);
                            });
                        })
                        .catch(error => console.error('Erro ao buscar OPs:', error));
                }, 300); 
            }

            opSearchInput.addEventListener('input', function() {
                opIdHiddenInput.value = '';
                fetchOpsForSearch(this.value);
            });

            opSearchInput.addEventListener('change', function() {
                const selectedOption = Array.from(opOptionsDatalist.options).find(option => option.value === this.value);
                if (selectedOption) {
                    opIdHiddenInput.value = selectedOption.getAttribute('data-id');
                } else {
                    opIdHiddenInput.value = '';
                }
            });
            // Re-preenche a OP se j estiver selecionada (após erro de POST)
            const initialOpIdHiddenValue = opIdHiddenInput.value;
            if (initialOpIdHiddenValue) {
                fetchOpsForSearch(opSearchInput.value); 
            }

        } else {
            console.error("DEBUG: Elementos HTML para busca de OP não encontrados na página de Empenho Manual.");
        }

        // --- Lógica de validação do formulário antes de enviar ---
        const empenhoForm = document.getElementById('empenhoForm');
        empenhoForm.addEventListener('submit', function(event) {
            // Verifica se os campos hidden de ID esto preenchidos
            if (!produtoIdHiddenInput.value) {
                alert('Por favor, selecione um Produto/Material válido da lista de sugestes.');
                event.preventDefault(); // Impede o envio do formulário
                return;
            }
            if (!opIdHiddenInput.value) {
                alert('Por favor, selecione uma Ordem de Produo (OP) válida da lista de sugestões.');
                event.preventDefault(); // Impede o envio do formulário
                return;
            }
            // Adicione mais validaçes de outros campos 'required' aqui, se necessário
        });
    });

    // Função auxiliar para re-preencher displays de estoque (chamada no DOMContentLoaded)
    function updateStockDisplays(productId) {
        const baseUrl = '<?php echo BASE_URL; ?>';
        fetch(`${baseUrl}/modules/estoque/get_product_stock_info.php?id=${encodeURIComponent(productId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data && !data.error) {
                    document.getElementById('quantidade_em_estoque_display').value = parseFloat(data.estoque_atual).toFixed(2).replace('.', ',');
                    document.getElementById('quantidade_empenhado_display').value = parseFloat(data.estoque_empenhado).toFixed(2).replace('.', ',');
                    document.getElementById('quantidade_livre_display').value = parseFloat(data.estoque_livre).toFixed(2).replace('.', ',');
                } else {
                    console.error('Erro ao buscar informações detalhadas de estoque:', data.error);
                    document.getElementById('quantidade_em_estoque_display').value = '0,00';
                    document.getElementById('quantidade_empenhado_display').value = '0,00';
                    document.getElementById('quantidade_livre_display').value = '0,00';
                }
            })
            .catch(error => console.error('Erro na requisição de estoque detalhado:', error));
    }
</script>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
