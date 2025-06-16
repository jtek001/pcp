<?php
// modules/materiais_insumos/editar.php
// Esta página permite editar uma entrada de material/insumo existente.

// Inicia a sessão para usar variáveis de sessão
session_start();

// Habilita a exibição de todos os erros PHP para depuraão (REMOVER EM PRODUÇÃO)
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
$entrada_data = null; // Dados da entrada a ser editada

// Função para buscar valores únicos de uma coluna em uma tabela específica
function getDistinctValues($conn, $table_name, $column_name, $where_clause = '') {
    $values = [];
    $sql = "SELECT DISTINCT " . $column_name . " FROM " . $table_name . " WHERE " . $column_name . " IS NOT NULL AND " . $column_name . " != '' " . $where_clause . " ORDER BY " . $column_name . " ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $values[] = $row[$column_name];
        }
    } else {
        error_log("Erro ao buscar valores para lookup (Tabela: " . $table_name . ", Coluna: " . $column_name . "): " . $conn->error);
    }
    return $values;
}

// Busca fornecedores/clientes (apenas 'fornecedor' ou 'ambos') para o dropdown
$fornecedores_clientes = [];
$sql_fornecedores = "SELECT id, nome FROM fornecedores_clientes_lookup WHERE deleted_at IS NULL AND (tipo = 'fornecedor' OR tipo = 'ambos') ORDER BY nome ASC";
try {
    $result_fornecedores = $conn->execute_query($sql_fornecedores);
    if ($result_fornecedores) {
        while ($row = $result_fornecedores->fetch_assoc()) {
            $fornecedores_clientes[] = $row;
        }
        $result_fornecedores->free();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro ao buscar fornecedores/clientes: " . $e->getMessage());
}

// Busca localizações para o dropdown
$localizacoes = getDistinctValues($conn, 'localizacoes_lookup', 'nome');

// Busca operadores para o dropdown
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


// Pega o ID da entrada da URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Se o ID for inválido, redireciona
if ($id <= 0) {
    header("Location: " . BASE_URL . "/modules/materiais_insumos/index.php?message=" . urlencode("ID da entrada inválido para edição.") . "&type=error");
    exit();
}

// --- Lógica para buscar os dados da entrada para preencher o formulário ---
$sql_select = "SELECT mie.*, p.nome AS produto_nome, p.codigo AS produto_codigo, p.estoque_atual AS produto_estoque_atual FROM materiais_insumos_entrada mie JOIN produtos p ON mie.produto_id = p.id WHERE mie.id = ? AND mie.deleted_at IS NULL";
try {
    $result_entrada_data = $conn->execute_query($sql_select, [$id]);
    if ($result_entrada_data) {
        $entrada_data = $result_entrada_data->fetch_assoc();
        $result_entrada_data->free();
        if (!$entrada_data) {
            $message = "Entrada de material não encontrada para edição.";
            $message_type = "error";
        }
    } else {
        $message = "Erro ao carregar dados da entrada: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar dados da entrada: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro fatal ao carregar dados da entrada (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar dados da entrada: " . $e->getMessage());
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0 && $entrada_data) {
    // Sanitiza e valida as entradas
    $temp_data_entrada = sanitizeInput(isset($_POST['data_entrada']) ? $_POST['data_entrada'] : date('Y-m-d H:i:s'));
    $temp_produto_id = sanitizeInput(isset($_POST['produto_id_hidden']) ? $_POST['produto_id_hidden'] : '');
    $temp_quantidade = (float) sanitizeInput(isset($_POST['quantidade']) ? $_POST['quantidade'] : 0.0);
    $temp_valor_unitario = (float) sanitizeInput(isset($_POST['valor_unitario']) ? $_POST['valor_unitario'] : 0.0);
    $temp_numero_nota_fiscal = sanitizeInput(isset($_POST['numero_nota_fiscal']) ? $_POST['numero_nota_fiscal'] : '');
    $temp_serie_nota_fiscal = sanitizeInput(isset($_POST['serie_nota_fiscal']) ? $_POST['serie_nota_fiscal'] : '');
    $temp_data_emissao_nota = sanitizeInput(isset($_POST['data_emissao_nota']) ? $_POST['data_emissao_nota'] : '');
    $temp_fornecedor_id = sanitizeInput(isset($_POST['fornecedor_id']) ? $_POST['fornecedor_id'] : '');
    $temp_local_armazenamento = sanitizeInput(isset($_POST['local_armazenamento']) ? $_POST['local_armazenamento'] : '');
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');
    $temp_responsavel_recebimento_id = sanitizeInput(isset($_POST['responsavel_recebimento_id']) ? $_POST['responsavel_recebimento_id'] : '');

    // Validação básica de campos obrigatórios
    if (empty($temp_produto_id) || empty($temp_quantidade) || empty($temp_numero_nota_fiscal) || empty($temp_data_emissao_nota)) {
        $message = "Produto, Quantidade, Número da Nota Fiscal e Data de Emissão da Nota são campos obrigatórios.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // Insere/Atualiza o fornecedor se for novo (se o ID não for válido, insere novo)
            if (!empty($temp_fornecedor_id)) {
                $fornecedor_exists = false;
                foreach ($fornecedores_clientes as $f) {
                    if ($f['id'] == $temp_fornecedor_id) {
                        $fornecedor_exists = true;
                        break;
                    }
                }
                if (!$fornecedor_exists) { // Se o ID não existe na lista pre-buscada, assume que é um novo nome digitado
                    $sql_insert_fornecedor = "INSERT IGNORE INTO fornecedores_clientes_lookup (nome, tipo) VALUES (?, 'fornecedor')";
                    $conn->execute_query($sql_insert_fornecedor, [$temp_fornecedor_id]);
                    $temp_fornecedor_id = $conn->insert_id; // Pega o ID do novo fornecedor
                }
            } else {
                $temp_fornecedor_id = NULL; // Se não selecionou/digitou, é NULL
            }

            // Insere/Atualiza a localização se for nova
            if (!empty($temp_local_armazenamento)) {
                $localizacao_exists = false;
                foreach ($localizacoes as $l) {
                    if ($l == $temp_local_armazenamento) {
                        $localizacao_exists = true;
                        break;
                    }
                }
                if (!$localizacao_exists) {
                    $sql_insert_localizacao = "INSERT IGNORE INTO localizacoes_lookup (nome) VALUES (?)";
                    $conn->execute_query($sql_insert_localizacao, [$temp_local_armazenamento]);
                    // Não precisamos do ID aqui, pois armazenamos o nome direto na entrada
                }
            } else {
                $temp_local_armazenamento = NULL;
            }

            // Obter a quantidade ORIGINAL desta entrada antes da atualização
            $original_quantidade = $entrada_data['quantidade'];
            $diferenca_quantidade = $temp_quantidade - $original_quantidade; // Positivo se aumentou, negativo se diminuiu

            // 1. Atualizar a entrada de material/insumo
            $sql_update_entrada = "UPDATE materiais_insumos_entrada SET data_entrada = ?, produto_id = ?, quantidade = ?, valor_unitario = ?, numero_nota_fiscal = ?, serie_nota_fiscal = ?, data_emissao_nota = ?, fornecedor_id = ?, local_armazenamento = ?, observacoes = ?, responsavel_recebimento_id = ? WHERE id = ?";
            
            $params_update_entrada = [
                $temp_data_entrada,
                $temp_produto_id,
                $temp_quantidade,
                $temp_valor_unitario,
                $temp_numero_nota_fiscal,
                $temp_serie_nota_fiscal,
                $temp_data_emissao_nota,
                $temp_fornecedor_id,
                $temp_local_armazenamento,
                $temp_observacoes,
                $temp_responsavel_recebimento_id,
                $id // ID da entrada a ser atualizada
            ];
            $conn->execute_query($sql_update_entrada, $params_update_entrada);

            // 2. Ajustar o estoque do produto com base na diferença
            $sql_adjust_estoque = "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?";
            $params_adjust_estoque = [
                $diferenca_quantidade,
                $temp_produto_id
            ];
            $conn->execute_query($sql_adjust_estoque, $params_adjust_estoque);

            // 3. Registrar a movimentação de estoque (ajuste) ou atualizar a original se possível
            // Tenta atualizar uma movimentação existente ligada a esta entrada
            $sql_update_mov_estoque = "UPDATE movimentacoes_estoque SET quantidade = ?, data_hora_movimentacao = ?, origem_destino = ?, observacoes = ? WHERE produto_id = ? AND tipo_movimentacao = 'entrada' AND origem_destino LIKE ? LIMIT 1";
            $origem_destino_like = "NF: " . $entrada_data['numero_nota_fiscal'] . "%"; // Busca pela NF original
            
            $fornecedor_nome_log = '';
            foreach ($fornecedores_clientes as $f_log) {
                if ($f_log['id'] == $temp_fornecedor_id) {
                    $fornecedor_nome_log = $f_log['nome'];
                    break;
                }
            }

            $params_update_mov_estoque = [
                $temp_quantidade,
                $temp_data_entrada,
                "NF: " . $temp_numero_nota_fiscal . " (Fornecedor: " . $fornecedor_nome_log . ") [EDITADO]",
                $temp_observacoes,
                $temp_produto_id,
                $origem_destino_like
            ];
            $conn->execute_query($sql_update_mov_estoque, $params_update_mov_estoque);

            if ($conn->affected_rows === 0) { // Se a movimentação original não foi encontrada/atualizada
                // Registra uma nova movimentaão de ajuste para a diferença
                if (abs($diferenca_quantidade) > 0.001) {
                    $sql_new_adj_mov = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
                    $adj_type = ($diferenca_quantidade > 0) ? 'ajuste_entrada' : 'ajuste_saida';
                    $adj_quantity = abs($diferenca_quantidade);
                    $adj_obs = "Ajuste por edição de entrada de material ID " . $id . " - Original: " . $original_quantidade . ", Nova: " . $temp_quantidade . ". Obs: " . $temp_observacoes;
                    
                    $conn->execute_query($sql_new_adj_mov, [
                        $temp_produto_id, $adj_type, $adj_quantity, date('Y-m-d H:i:s'), "Ajuste Entrada ID " . $id, $adj_obs
                    ]);
                }
            }
            
            $conn->commit();
            $message = "Entrada de material/insumo atualizada com sucesso! Estoque e movimentação ajustados.";
            $message_type = "success";
            
            // Recarrega os dados da entrada após o sucesso para refletir na tela
            $sql_select_reloaded = "SELECT mie.*, p.nome AS produto_nome, p.codigo AS produto_codigo, p.estoque_atual AS produto_estoque_atual FROM materiais_insumos_entrada mie JOIN produtos p ON mie.produto_id = p.id WHERE mie.id = ? AND mie.deleted_at IS NULL";
            $result_reloaded = $conn->execute_query($sql_select_reloaded, [$id]);
            if ($result_reloaded && $row_reloaded = $result_reloaded->fetch_assoc()) {
                $entrada_data = $row_reloaded;
                $result_reloaded->free();
            }

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro na transação de atualização de entrada: " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal na transação de atualização de entrada: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Variável para armazenar o estoque atual do produto selecionado (para exibição no JS)
$current_product_stock = $entrada_data['produto_estoque_atual'] ?? '0.00'; 
// Se um produto foi selecionado (vindo de um POST com erro), usa o estoque daquele produto. Senão, o do DB.
if (isset($post_values['produto_id_hidden']) && !empty($post_values['produto_id_hidden'])) {
    $sql_get_stock = "SELECT estoque_atual FROM produtos WHERE id = ?";
    $result_stock = $conn->execute_query($sql_get_stock, [$post_values['produto_id_hidden']]);
    if ($result_stock && $row_stock = $result_stock->fetch_assoc()) {
        $current_product_stock = $row_stock['estoque_atual'];
        $result_stock->free();
    }
}

// Dados do produto original para preencher o campo de busca (JS)
$initial_product_search_value = '';
if (isset($entrada_data['produto_id']) && !empty($entrada_data['produto_id'])) {
    $initial_product_search_value = $entrada_data['produto_nome'] . ' (' . $entrada_data['produto_codigo'] . ')';
}

?>

<h2>Editar Entrada de Material/Insumo</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($entrada_data): ?>
    <form action="editar.php?id=<?php echo $entrada_data['id']; ?>" method="POST">
        <div class="form-group">
            <label for="data_entrada">Data de Entrada:</label>
            <input type="datetime-local" id="data_entrada" name="data_entrada" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($post_values['data_entrada'] ?? $entrada_data['data_entrada']))); ?>" required>
        </div>

        <div class="form-group">
            <label for="produto_search">Produto/Material:</label>
            <input type="text" id="produto_search" list="product_options" value="<?php echo htmlspecialchars($post_values['produto_search'] ?? $initial_product_search_value); ?>" required placeholder="Digite para buscar produto (código ou nome)">
            <datalist id="product_options">
                <?php 
                // Se houver um produto pré-selecionado (seja do DB ou do POST em caso de erro), garanta que ele esteja na datalist
                if (!empty($entrada_data['produto_id']) || !empty($post_values['produto_id_hidden'])) {
                    $display_product_id = $post_values['produto_id_hidden'] ?? $entrada_data['produto_id'];
                    $display_product_name = $post_values['produto_search'] ?? ($entrada_data['produto_nome'] . ' (' . $entrada_data['produto_codigo'] . ')');
                    $display_product_stock = $entrada_data['produto_estoque_atual'] ?? $current_product_stock;
                    echo '<option data-id="' . htmlspecialchars($display_product_id) . '" data-stock="' . htmlspecialchars($display_product_stock) . '" value="' . htmlspecialchars($display_product_name) . '"></option>';
                }
                ?>
            </datalist>
            <input type="hidden" id="produto_id_hidden" name="produto_id_hidden" value="<?php echo htmlspecialchars($post_values['produto_id_hidden'] ?? $entrada_data['produto_id']); ?>" required>
        </div>

        <div class="form-group">
            <label for="quantidade">Quantidade:</label>
            <input type="number" id="quantidade" name="quantidade" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade'] ?? $entrada_data['quantidade']); ?>" required min="0.01" placeholder="Ex: 50.00">
        </div>
		
      	    <div class="form-group">
        <label for="quantidade_em_estoque">Quantidade em Estoque:</label>
        <input type="number" id="quantidade_em_estoque" name="quantidade_em_estoque" step="0.01" value="<?php echo htmlspecialchars($current_product_stock); ?>" readonly placeholder="Estoque atual do produto">
    </div>
      
        <div class="form-group">
            <label for="valor_unitario">Valor Unitário:</label>
            <input type="number" id="valor_unitario" name="valor_unitario" step="0.0001" value="<?php echo htmlspecialchars($post_values['valor_unitario'] ?? $entrada_data['valor_unitario'] ?? ''); ?>" placeholder="Ex: 1.2500">
        </div>

        <div class="form-group">
            <label for="numero_nota_fiscal">Número da Nota Fiscal:</label>
            <input type="text" id="numero_nota_fiscal" name="numero_nota_fiscal" value="<?php echo htmlspecialchars($post_values['numero_nota_fiscal'] ?? $entrada_data['numero_nota_fiscal']); ?>" maxlength="50" required placeholder="Ex: 123456">
        </div>

        <div class="form-group">
            <label for="serie_nota_fiscal">Série NF:</label>
            <input type="text" id="serie_nota_fiscal" name="serie_nota_fiscal" value="<?php echo htmlspecialchars($post_values['serie_nota_fiscal'] ?? $entrada_data['serie_nota_fiscal'] ?? ''); ?>" maxlength="10" placeholder="Ex: A">
        </div>

        <div class="form-group">
            <label for="data_emissao_nota">Data Emissão NF:</label>
            <input type="date" id="data_emissao_nota" name="data_emissao_nota" value="<?php echo htmlspecialchars($post_values['data_emissao_nota'] ?? $entrada_data['data_emissao_nota']); ?>" required>
        </div>

        <div class="form-group">
            <label for="fornecedor_id">Fornecedor:</label>
            <select id="fornecedor_id_select" name="fornecedor_id_display" data-initial-value="<?php echo htmlspecialchars($post_values['fornecedor_id'] ?? $entrada_data['fornecedor_id'] ?? ''); ?>">
                <option value="">Selecione um Fornecedor</option>
                <?php
                $current_fornecedor_id = $post_values['fornecedor_id'] ?? $entrada_data['fornecedor_id'];
                foreach ($fornecedores_clientes as $fornecedor): ?>
                    <option value="<?php echo htmlspecialchars($fornecedor['id']); ?>" <?php echo ($current_fornecedor_id == $fornecedor['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($fornecedor['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="fornecedor_id_text" style="display:none;" maxlength="100" placeholder="Digite um novo Fornecedor">
            <a href="#" class="toggle-link" onclick="toggleInput(event, 'fornecedor_id'); return false;">Novo</a>
            <?php if (!empty($entrada_data['fornecedor_id'])): // Hidden para enviar o ID do fornecedor quando select está disabled ?>
                <input type="hidden" name="fornecedor_id" value="<?php echo htmlspecialchars($entrada_data['fornecedor_id']); ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="local_armazenamento">Local Armazenamento:</label>
            <select id="local_armazenamento_select" name="local_armazenamento_display" data-initial-value="<?php echo htmlspecialchars($post_values['local_armazenamento'] ?? $entrada_data['local_armazenamento'] ?? ''); ?>">
                <option value="">Selecione um Local</option>
                <?php
                $current_localizacao = $post_values['local_armazenamento'] ?? $entrada_data['local_armazenamento'];
                if (!empty($current_localizacao) && !in_array($current_localizacao, $localizacoes)) {
                    echo '<option value="' . htmlspecialchars($current_localizacao) . '" selected>' . htmlspecialchars($current_localizacao) . '</option>';
                }
                foreach ($localizacoes as $loc_opt) {
                    $selected = ($current_localizacao == $loc_opt) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($loc_opt) . '" ' . $selected . '>' . htmlspecialchars($loc_opt) . '</option>';
                }
                ?>
            </select>
            <input type="text" id="local_armazenamento_text" style="display:none;" maxlength="100" placeholder="Digite um novo Local">
            <a href="#" class="toggle-link" onclick="toggleInput(event, 'local_armazenamento'); return false;">Novo</a>
            <?php if (!empty($entrada_data['local_armazenamento'])): // Hidden para enviar o valor quando select está disabled ?>
                <input type="hidden" name="local_armazenamento" value="<?php echo htmlspecialchars($entrada_data['local_armazenamento']); ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="responsavel_recebimento_id">Responsável Recebimento:</label>
            <select id="responsavel_recebimento_id" name="responsavel_recebimento_id">
                <option value="">Selecione um Operador</option>
                <?php
                $current_responsavel = $post_values['responsavel_recebimento_id'] ?? $entrada_data['responsavel_recebimento_id'];
                foreach ($operadores as $operador): ?>
                    <option value="<?php echo htmlspecialchars($operador['id']); ?>" <?php echo ($current_responsavel == $operador['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($operador['nome'] . ' (' . $operador['matricula'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group full-width">
            <label for="observacoes">Observações:</label>
            <textarea id="observacoes" name="observacoes" placeholder="Observações adicionais sobre a entrada do material..."><?php echo htmlspecialchars($post_values['observacoes'] ?? $entrada_data['observacoes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button submit">Atualizar Entrada</button>
    </form>
<?php else: ?>
    <p class="error" style="text-align: center;">Entrada de material não encontrada para edição.</p>
<?php endif; ?>

<a href="index.php" class="back-link">Voltar para a lista de Entradas</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const produtoSearchInput = document.getElementById('produto_search');
        const productOptionsDatalist = document.getElementById('product_options');
        const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
        const quantidadeEmEstoqueInput = document.getElementById('quantidade_em_estoque'); // Campo de estoque atual (display only)
        const baseUrl = '<?php echo BASE_URL; ?>'; 

        let debounceTimeout;

        // Função para buscar e preencher a datalist de produtos
        function fetchProductsForSearch(searchTerm) {
            if (searchTerm.length < 2) { 
                productOptionsDatalist.innerHTML = ''; 
                return;
            }

            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                fetch(`${baseUrl}/modules/materiais_insumos/get_products_for_materials.php?term=${encodeURIComponent(searchTerm)}`)
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
        }

        // Event listener para o input de pesquisa de produto
        produtoSearchInput.addEventListener('input', function() {
            produtoIdHiddenInput.value = '';
            quantidadeEmEstoqueInput.value = '0.00'; // Limpa o estoque ao digitar
            fetchProductsForSearch(this.value);
        });

        // Event listener para quando uma opção da datalist é selecionada
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

        // Lógica para manter o estado do campo produto se o formulário foi submetido com erro
        const initialProductIdHidden = '<?php echo htmlspecialchars($entrada_data["produto_id"] ?? ""); ?>'; // ID do produto da entrada
        const initialProductSearchValue = '<?php echo htmlspecialchars($initial_product_search_value); ?>'; // Nome(Cdigo) do produto
        const initialProductStockValue = '<?php echo htmlspecialchars($current_product_stock); ?>'; // Estoque atual

        if (initialProductIdHidden) {
            produtoSearchInput.value = initialProductSearchValue;
            produtoIdHiddenInput.value = initialProductIdHidden;
            quantidadeEmEstoqueInput.value = initialProductStockValue;
        }


        // Lógica para alternar entre select e input de texto para lookups (fornecedor, local_armazenamento)
        window.toggleInput = function(event, fieldName) {
            event.preventDefault(); 
            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = event.target;

            if (selectElement.style.display === 'none') {
                selectElement.style.display = 'block';
                selectElement.setAttribute('name', fieldName + '_display'); // Display select now has name
                textInputElement.style.display = 'none';
                textInputElement.removeAttribute('name'); 
                textInputElement.value = ''; 
                linkElement.textContent = 'Novo'; 
            } else {
                selectElement.style.display = 'none';
                selectElement.removeAttribute('name'); 
                textInputElement.style.display = 'block';
                textInputElement.setAttribute('name', fieldName); 
                textInputElement.focus();
                linkElement.textContent = 'Voltar'; 
            }
        };

        // Lógica para garantir que o campo correto seja submetido (select ou text input)
        document.querySelector('form').addEventListener('submit', function(event) {
            const fieldsToToggle = ['fornecedor_id', 'local_armazenamento']; 
            fieldsToToggle.forEach(fieldName => {
                const selectElement = document.getElementById(fieldName + '_select');
                const textInputElement = document.getElementById(fieldName + '_text');
                const hiddenInput = document.querySelector(`input[type="hidden"][name="${fieldName}"]`); // Se houver hidden

                if (textInputElement.style.display === 'block') {
                    // Se o input de texto está visível, ele deve ter o 'name'
                    selectElement.removeAttribute('name');
                    textInputElement.setAttribute('name', fieldName);
                    // Garante que o hidden input não envie um valor duplicado se o text input estiver ativo
                    if (hiddenInput) {
                        hiddenInput.removeAttribute('name');
                    }
                } else {
                    // Se o select está visível, ele deve ter o 'name'
                    textInputElement.removeAttribute('name');
                    selectElement.setAttribute('name', fieldName);
                    // Garante que o hidden input não envie um valor duplicado se o select estiver ativo
                    if (hiddenInput) {
                        hiddenInput.removeAttribute('name');
                    }
                }
            });
        });

        // Lógica para manter o estado do campo (select/input) após um POST com erro ou edição
        const fieldsToReconcile = ['fornecedor_id', 'local_armazenamento'];
        fieldsToReconcile.forEach(fieldName => {
            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = textInputElement.nextElementSibling; 
            
            // Pega o valor inicial do data-attribute no select
            const initialValue = selectElement.getAttribute('data-initial-value') || ''; 
            
            if (initialValue) {
                let foundInSelect = false;
                for (let i = 0; i < selectElement.options.length; i++) {
                    if (selectElement.options[i].value == initialValue) { // Usar == para comparar ID (number) com string
                        selectElement.value = initialValue;
                        foundInSelect = true;
                        break;
                    }
                }
                if (!foundInSelect) {
                    // Se o valor inicial não está nas opções do select, ativa o input de texto
                    selectElement.style.display = 'none';
                    selectElement.removeAttribute('name');
                    textInputElement.style.display = 'block';
                    textInputElement.setAttribute('name', fieldName);
                    textInputElement.value = initialValue; // Preenche o input de texto com o valor
                    linkElement.textContent = 'Voltar';
                } else {
                    // Se o valor foi encontrado no select, garante que o select esteja ativo
                    selectElement.style.display = 'block';
                    selectElement.setAttribute('name', fieldName);
                    textInputElement.style.display = 'none';
                    textInputElement.removeAttribute('name');
                    linkElement.textContent = 'Novo';
                }
            } else {
                selectElement.style.display = 'block';
                selectElement.setAttribute('name', fieldName);
                textInputElement.style.display = 'none';
                textInputElement.removeAttribute('name');
                linkElement.textContent = 'Novo';
            }
        });
    });
</script>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
