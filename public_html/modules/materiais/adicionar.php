<?php
// modules/materiais/adicionar.php
// Esta página contém o formulário para adicionar uma nova entrada de materiais/insumos.

// Inicia a sessão para usar variáveis de sessão (necessário para mensagens)
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

// Variáveis para mensagens de sucesso/erro - GARANTIDAS DE SEREM INICIALIZADAS
$message = '';
$message_type = '';

// Recupera mensagens da sessão se existirem
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

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


// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitiza e valida as entradas
    $temp_data_entrada = sanitizeInput(isset($_POST['data_entrada']) ? $_POST['data_entrada'] : date('Y-m-d H:i:s'));
    $temp_produto_id = sanitizeInput(isset($_POST['produto_id_hidden']) ? $_POST['produto_id_hidden'] : '');
    $temp_quantidade = (float) sanitizeInput(isset($_POST['quantidade']) ? $_POST['quantidade'] : 0.0);
    $temp_valor_unitario = (float) sanitizeInput(isset($_POST['valor_unitario']) ? $_POST['valor_unitario'] : 0.0);
    $temp_numero_nota_fiscal = sanitizeInput(isset($_POST['numero_nota_fiscal']) ? $_POST['numero_nota_fiscal'] : '');
    $temp_serie_nota_fiscal = sanitizeInput(isset($_POST['serie_nota_fiscal']) ? $_POST['serie_nota_fiscal'] : '');
    $temp_data_emissao_nota = sanitizeInput(isset($_POST['data_emissao_nota']) ? $_POST['data_emissao_nota'] : '');
    // Pega o valor bruto do POST para Fornecedor e Local de Armazenamento
    $temp_fornecedor_raw = isset($_POST['fornecedor_id']) ? $_POST['fornecedor_id'] : '';
    $temp_local_armazenamento_raw = isset($_POST['local_armazenamento']) ? $_POST['local_armazenamento'] : '';
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
            // --- INÍCIO DA ALTERAÇÃO: Lógica Robusta para Fornecedor (07/06/2025 - IA) ---
            $final_fornecedor_id = NULL; // ID final do fornecedor para inserção
            $fornecedor_name_to_lookup_or_insert = sanitizeInput($temp_fornecedor_raw); // Valor vindo do POST (ID ou Nome)

            if (!empty($fornecedor_name_to_lookup_or_insert)) {
                // Tenta encontrar o fornecedor por ID (se for um ID numérico)
                if (is_numeric($fornecedor_name_to_lookup_or_insert)) {
                    $potential_id = (int)$fornecedor_name_to_lookup_or_insert;
                    $sql_check_id = "SELECT id FROM fornecedores_clientes_lookup WHERE id = ? AND (tipo = 'fornecedor' OR tipo = 'ambos') AND deleted_at IS NULL";
                    $result_check_id = $conn->execute_query($sql_check_id, [$potential_id]);
                    if ($result_check_id && $row_check_id = $result_check_id->fetch_assoc()) {
                        $final_fornecedor_id = $row_check_id['id']; // ID válido encontrado
                    }
                    // Se não encontrou por ID, mas era numérico, pode ser um nome numérico digitado
                }
                
                // Se o ID ainda não foi definido, tenta encontrar/inserir pelo nome (ou se era um nome numérico que não era ID)
                if ($final_fornecedor_id === NULL) {
                    $sql_check_name = "SELECT id FROM fornecedores_clientes_lookup WHERE nome = ? AND (tipo = 'fornecedor' OR tipo = 'ambos') AND deleted_at IS NULL";
                    $result_check_name = $conn->execute_query($sql_check_name, [$fornecedor_name_to_lookup_or_insert]);
                    if ($result_check_name && $row_check_name = $result_check_name->fetch_assoc()) {
                        $final_fornecedor_id = $row_check_name['id']; // Nome encontrado, usa o ID
                    } else {
                        // Nome não existe, insere novo fornecedor
                        $sql_insert_new_fornecedor = "INSERT INTO fornecedores_clientes_lookup (nome, tipo) VALUES (?, 'fornecedor')";
                        $conn->execute_query($sql_insert_new_fornecedor, [$fornecedor_name_to_lookup_or_insert]);
                        $final_fornecedor_id = $conn->insert_id; // Pega o ID do novo fornecedor
                    }
                }
            }
            $temp_fornecedor_id = $final_fornecedor_id; // Atribui o ID final para a inserção

            // --- FIM DA ALTERAÇÃO: Lógica Robusta para Fornecedor ---


            // --- INÍCIO DA ALTERAÇÃO: Lógica Robusta para Local de Armazenamento (07/06/2025 - IA) ---
            $final_local_armazenamento_name = sanitizeInput($temp_local_armazenamento_raw); // Valor que será inserido/validado

            if (!empty($final_local_armazenamento_name)) {
                $localizacao_exists = false;
                $sql_check_localizacao = "SELECT COUNT(*) FROM localizacoes_lookup WHERE nome = ?";
                $result_check_loc = $conn->execute_query($sql_check_localizacao, [$final_local_armazenamento_name]);
                if ($result_check_loc && $row_check_loc = $result_check_loc->fetch_row()) {
                    if ($row_check_loc[0] > 0) {
                        $localizacao_exists = true;
                    }
                }
                
                if (!$localizacao_exists) { 
                    $sql_insert_localizacao = "INSERT IGNORE INTO localizacoes_lookup (nome) VALUES (?)";
                    $conn->execute_query($sql_insert_localizacao, [$final_local_armazenamento_name]);
                }
            } else {
                $final_local_armazenamento_name = NULL;
            }
            $temp_local_armazenamento = $final_local_armazenamento_name; // Atribui o nome final para a inserção
            // --- FIM DA ALTERAÇÃO: Lógica Robusta para Local de Armazenamento ---


            // 1. Inserir a entrada de material/insumo
            $sql_insert_entrada = "INSERT INTO materiais_insumos_entrada (data_entrada, produto_id, quantidade, valor_unitario, numero_nota_fiscal, serie_nota_fiscal, data_emissao_nota, fornecedor_id, local_armazenamento, observacoes, responsavel_recebimento_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params_insert_entrada = [
                $temp_data_entrada,
                $temp_produto_id,
                $temp_quantidade,
                $temp_valor_unitario,
                $temp_numero_nota_fiscal,
                $temp_serie_nota_fiscal,
                $temp_data_emissao_nota,
                $temp_fornecedor_id, // Usando o ID final do fornecedor
                $temp_local_armazenamento, // Usando o nome final da localização
                $temp_observacoes,
                $temp_responsavel_recebimento_id
            ];

            error_log("SQL INSERT Materiais: " . $sql_insert_entrada);
            error_log("Parâmetros Materiais: " . print_r($params_insert_entrada, true));

            $conn->execute_query($sql_insert_entrada, $params_insert_entrada);

            // 2. Atualizar o estoque do produto (Adicionar a quantidade)
            $sql_update_estoque = "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?";
            $params_update_estoque = [
                $temp_quantidade,
                $temp_produto_id
            ];
            $conn->execute_query($sql_update_estoque, $params_update_estoque);

            // 3. Registrar a movimentação de estoque
            $sql_mov_estoque = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
            
            $fornecedor_nome_log = '';
            // Buscar nome do fornecedor para o log (se o ID for válido)
            if ($temp_fornecedor_id !== NULL) {
                $sql_get_fornecedor_name = "SELECT nome FROM fornecedores_clientes_lookup WHERE id = ?";
                $result_f_name = $conn->execute_query($sql_get_fornecedor_name, [$temp_fornecedor_id]);
                if ($result_f_name && $row_f_name = $result_f_name->fetch_assoc()) {
                    $fornecedor_nome_log = $row_f_name['nome'];
                    $result_f_name->free();
                }
            }
            
            $params_mov_estoque = [
                $temp_produto_id,
                'entrada',
                $temp_quantidade,
                $temp_data_entrada,
                "NF: " . $temp_numero_nota_fiscal . " (Fornecedor: " . $fornecedor_nome_log . ")", 
                $temp_observacoes
            ];
            $conn->execute_query($sql_mov_estoque, $params_mov_estoque);

            $conn->commit();
            $message = "Entrada de material/insumo registrada com sucesso! Estoque atualizado.";
            $message_type = "success";
            $_POST = array(); // Limpa o formulário

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro ao registrar entrada de material (SQL): " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal ao inserir material/insumo: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Variável para armazenar o estoque atual do produto selecionado (para exibição no JS)
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

// Valor padrão para Data de Entrada
$default_data_entrada = date('Y-m-d\TH:i'); // Data e hora atual para datetime-local

?>

<h2>Registrar Entrada de Material/Insumo</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="adicionar.php" method="POST">
    <div class="form-group">
        <label for="data_entrada">Data de Entrada:</label>
        <input type="datetime-local" id="data_entrada" name="data_entrada" value="<?php echo htmlspecialchars($post_values['data_entrada'] ?? $default_data_entrada); ?>" required>
    </div>

    <div class="form-group">
        <label for="produto_search">Produto/Material:</label>
        <input type="text" id="produto_search" list="product_options" value="<?php echo htmlspecialchars($post_values['produto_search'] ?? ''); ?>" required placeholder="Digite para buscar produto (código ou nome)">
        <datalist id="product_options">
            <!-- Opções serão preenchidas via JavaScript -->
            <?php 
            // Se houver um produto pré-selecionado (após erro de POST), garanta que ele esteja na datalist
            if (!empty($post_values['produto_id_hidden'])) {
                $selected_product_id = $post_values['produto_id_hidden'];
                // Adaptação: Buscar produto sem filtro de acabamento 'Acabado' para materiais
                $sql_selected_product = "SELECT id, nome, codigo, estoque_atual FROM produtos WHERE id = ?"; 
                $result_selected_product = $conn->execute_query($sql_selected_product, [$selected_product_id]);
                if ($result_selected_product && $row_selected_product = $result_selected_product->fetch_assoc()) {
                    echo '<option data-id="' . htmlspecialchars($row_selected_product['id']) . '" data-stock="' . htmlspecialchars($row_selected_product['estoque_atual']) . '" value="' . htmlspecialchars($row_selected_product['nome'] . ' (' . $row_selected_product['codigo'] . ')') . '"></option>';
                }
            }
            ?>
        </datalist>
        <input type="hidden" id="produto_id_hidden" name="produto_id_hidden" value="<?php echo htmlspecialchars($post_values['produto_id_hidden'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label for="quantidade">Quantidade:</label>
        <input type="number" id="quantidade" name="quantidade" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade'] ?? '0.00'); ?>" required min="0.01" placeholder="Ex: 50.00">
    </div>

    <div class="form-group">
        <label for="quantidade_em_estoque">Quantidade em Estoque:</label>
        <input type="number" id="quantidade_em_estoque" name="quantidade_em_estoque" step="0.01" value="<?php echo htmlspecialchars($current_product_stock); ?>" readonly placeholder="Estoque atual do produto">
    </div>

    <div class="form-group">
        <label for="valor_unitario">Valor Unitário:</label>
        <input type="number" id="valor_unitario" name="valor_unitario" step="0.0001" value="<?php echo htmlspecialchars($post_values['valor_unitario'] ?? ''); ?>" placeholder="Ex: 1.2500">
    </div>

    <div class="form-group">
        <label for="numero_nota_fiscal">Número da Nota Fiscal:</label>
        <input type="text" id="numero_nota_fiscal" name="numero_nota_fiscal" value="<?php echo htmlspecialchars($post_values['numero_nota_fiscal'] ?? ''); ?>" maxlength="50" required placeholder="Ex: 123456">
    </div>

    <div class="form-group">
        <label for="serie_nota_fiscal">Série NF:</label>
        <input type="text" id="serie_nota_fiscal" name="serie_nota_fiscal" value="<?php echo htmlspecialchars($post_values['serie_nota_fiscal'] ?? ''); ?>" maxlength="10" placeholder="Ex: A">
    </div>

    <div class="form-group">
        <label for="data_emissao_nota">Data Emissão NF:</label>
        <input type="date" id="data_emissao_nota" name="data_emissao_nota" value="<?php echo htmlspecialchars($post_values['data_emissao_nota'] ?? date('Y-m-d')); ?>" required>
    </div>

    <div class="form-group">
        <label for="fornecedor_id">Fornecedor:</label>
        <select id="fornecedor_id_select" name="fornecedor_id" data-initial-value="<?php echo htmlspecialchars($post_values['fornecedor_id'] ?? ''); ?>">
            <option value="">Selecione um Fornecedor</option>
            <?php
            $current_fornecedor_id = $post_values['fornecedor_id'] ?? '';
            foreach ($fornecedores_clientes as $fornecedor): ?>
                <option value="<?php echo htmlspecialchars($fornecedor['id']); ?>" <?php echo ($current_fornecedor_id == $fornecedor['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($fornecedor['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="fornecedor_id_text" style="display:none;" maxlength="100" placeholder="Digite um novo Fornecedor">
        <a href="#" class="toggle-link" onclick="toggleInput(event, 'fornecedor_id'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="local_armazenamento">Local Armazenamento:</label>
        <select id="local_armazenamento_select" name="local_armazenamento" data-initial-value="<?php echo htmlspecialchars($post_values['local_armazenamento'] ?? ''); ?>">
            <option value="">Selecione um Local</option>
            <?php
            $current_localizacao = $post_values['local_armazenamento'] ?? '';
            if (!empty($current_localizacao) && !in_array($current_localizacao, $localizacoes)) {
                echo '<option data-id="' . htmlspecialchars($current_localizacao) . '" value="' . htmlspecialchars($current_localizacao) . '" selected>' . htmlspecialchars($current_localizacao) . '</option>'; // Mantido data-id para consistência, embora seja o nome
            }
            foreach ($localizacoes as $loc_opt) {
                $selected = ($current_localizacao == $loc_opt) ? 'selected' : '';
                echo '<option data-id="' . htmlspecialchars($loc_opt) . '" value="' . htmlspecialchars($loc_opt) . '" ' . $selected . '>' . htmlspecialchars($loc_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="local_armazenamento_text" style="display:none;" maxlength="100" placeholder="Digite um novo Local">
        <a href="#" class="toggle-link" onclick="toggleInput(event, 'local_armazenamento'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="responsavel_recebimento_id">Responsável Recebimento:</label>
        <select id="responsavel_recebimento_id" name="responsavel_recebimento_id">
            <option value="">Selecione um Operador</option>
            <?php foreach ($operadores as $operador): ?>
                <option value="<?php echo htmlspecialchars($operador['id']); ?>" <?php echo (isset($post_values['responsavel_recebimento_id']) && $post_values['responsavel_recebimento_id'] == $operador['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($operador['nome'] . ' (' . $operador['matricula'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes" placeholder="Observações adicionais sobre a entrada do material..."><?php echo htmlspecialchars($post_values['observacoes'] ?? ''); ?></textarea>
    </div>

    <button type="submit" class="button submit">Registrar Entrada</button>
</form>

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
                // Adicionado console.log para depuração da URL
                console.log("DEBUG: FETCH URL:", `${baseUrl}/modules/materiais/get_products_for_materials.php?term=${encodeURIComponent(searchTerm)}`); 
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

        // Event listener para o input de pesquisa de produto
        produtoSearchInput.addEventListener('input', function() {
            produtoIdHiddenInput.value = '';
            // Quantidade em estoque deve ser limpa APENAS se o produto atual não for validado
            // ou se o usuário realmente limpar o campo de busca.
            // Para simplicidade, limpa ao digitar e preenche no 'change'.
            quantidadeEmEstoqueInput.value = '0.00'; 
            fetchProductsForSearch(this.value);
        });

        // Event listener para quando uma opção da datalist é selecionada
        produtoSearchInput.addEventListener('change', function() {
            const selectedOption = Array.from(productOptionsDatalist.options).find(option => option.value === this.value);
            if (selectedOption) {
                produtoIdHiddenInput.value = selectedOption.getAttribute('data-id');
                quantidadeEmEstoqueInput.value = parseFloat(selectedOption.getAttribute('data-stock')).toFixed(2);
            } else {
                // Se o usuário digitou algo que não está na lista ou limpou o campo
                produtoIdHiddenInput.value = '';
                quantidadeEmEstoqueInput.value = '0.00';
            }
        });

        // Lógica para manter o estado do campo produto se o formulário foi submetido com erro
        const initialProductIdHidden = '<?php echo htmlspecialchars($post_values["produto_id_hidden"] ?? ""); ?>';
        const initialProductSearchValue = '<?php echo htmlspecialchars($post_values["produto_search"] ?? ""); ?>';
        const initialProductStockValue = '<?php echo htmlspecialchars($current_product_stock); ?>';

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

            // Esta é a lógica CORRETA que gerencia os 'name' dinamicamente.
            // O atributo 'name' é removido do elemento que está escondido e adicionado ao que está visível.
            if (selectElement.style.display === 'none') {
                // Ativar select (se estava no modo input de texto)
                selectElement.style.display = 'block';
                selectElement.setAttribute('name', fieldName); // Reativa o name para o select
                textInputElement.style.display = 'none';
                textInputElement.removeAttribute('name'); // Desativa o name para o input de texto
                textInputElement.value = ''; // Limpa o input de texto ao voltar para select
                linkElement.textContent = 'Novo'; 
            } else {
                // Ativar input de texto (se estava no modo select)
                selectElement.style.display = 'none';
                selectElement.removeAttribute('name'); // Desativa o name para o select
                textInputElement.style.display = 'block';
                textInputElement.setAttribute('name', fieldName); // Ativa o name para o input de texto
                textInputElement.focus();
                linkElement.textContent = 'Voltar'; 
            }
        };

        // Lógica para garantir que o campo correto seja submetido (select ou text input)
        // ESTE BLOCO DE CÓDIGO FOI REMOVIDO/COMENTADO ANTERIORMENTE.
        // ELE ESTAVA CAUSANDO PROBLEMAS DE RENOMEAÇÃO DE ATRIBUTO 'NAME' NO SUBMIT.
        // NÃO RE-ADICIONE ESTE BLOCO. A FUNÇÃO toggleInput JÁ CUIDA DO 'NAME' CORRETAMENTE.

        // Lógica para manter o estado do campo (select/input) após um POST com erro ou edição
        const fieldsToReconcile = ['fornecedor_id', 'local_armazenamento'];
        fieldsToReconcile.forEach(fieldName => {
            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = textInputElement.nextElementSibling; 
            
            const initialValue = selectElement.getAttribute('data-initial-value') || ''; 
            
            if (initialValue) {
                let foundInSelect = false;
                for (let i = 0; i < selectElement.options.length; i++) {
                    if (selectElement.options[i].value == initialValue) { 
                        selectElement.value = initialValue;
                        foundInSelect = true;
                        break;
                    }
                }
                if (!foundInSelect) {
                    selectElement.style.display = 'none';
                    // textInputElement já terá o 'name' por conta do toggleInput na inicialização
                    textInputElement.style.display = 'block';
                    textInputElement.value = initialValue; 
                    linkElement.textContent = 'Novo';
                } else {
                    selectElement.style.display = 'block';
                    // selectElement já terá o 'name' por conta do toggleInput na inicialização
                    textInputElement.style.display = 'none';
                    linkElement.textContent = 'Novo';
                }
                // Garante que o atributo 'name' esteja correto no carregamento da página,
                // caso o initialValue force um estado de 'input text' ou 'select'
                if (textInputElement.style.display === 'block') {
                    textInputElement.setAttribute('name', fieldName);
                    selectElement.removeAttribute('name');
                } else {
                    selectElement.setAttribute('name', fieldName);
                    textInputElement.removeAttribute('name');
                }
            } else {
                // Estado padrão: select visível, input escondido
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
