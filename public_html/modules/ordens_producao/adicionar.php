<?php
// modules/ordens_producao/adicionar.php
// Esta página contém o formulário para adicionar uma nova Ordem de Produço.

// Inicia a sessão para usar variáveis de sessão (necessário para mensagens)
session_start();

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Brasília. ESSENCIAL PARA DATAS/HORAS.
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

// Busca máquinas ativas para o dropdown
$maquinas_ativas = [];
$sql_maquinas = "SELECT id, nome FROM maquinas WHERE deleted_at IS NULL AND status = 'operacional' ORDER BY nome ASC";
try {
    $result_maquinas = $conn->execute_query($sql_maquinas);
    if ($result_maquinas) {
        while ($row = $result_maquinas->fetch_assoc()) {
            $maquinas_ativas[] = $row;
        }
        $result_maquinas->free();
    } else {
        error_log("Erro ao buscar máquinas ativas: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao buscar máquinas ativas: " . $e->getMessage());
}

// Busca números de pedido de venda para o dropdown
$pedidos_venda = getDistinctValues($conn, 'pedidos_venda_lookup', 'numero_pedido');


// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitiza e valida as entradas
    $temp_numero_op = sanitizeInput(isset($_POST['numero_op']) ? $_POST['numero_op'] : ''); 
    $temp_numero_pedido = sanitizeInput(isset($_POST['numero_pedido']) ? $_POST['numero_pedido'] : '');
    $temp_produto_id = sanitizeInput(isset($_POST['produto_id_hidden']) ? $_POST['produto_id_hidden'] : ''); 
    $temp_maquina_id = sanitizeInput(isset($_POST['maquina_id']) ? $_POST['maquina_id'] : ''); 
    $temp_quantidade_produzir = (float) sanitizeInput(isset($_POST['quantidade_produzir']) ? $_POST['quantidade_produzir'] : 0.0);
    $temp_data_emissao = sanitizeInput(isset($_POST['data_emissao']) ? $_POST['data_emissao'] : '');
    $temp_data_prevista_conclusao = isset($_POST['data_prevista_conclusao']) && $_POST['data_prevista_conclusao'] !== '' ? sanitizeInput($_POST['data_prevista_conclusao']) : NULL;
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');

    // Gerar Número do Pedido se Vazio
    if (empty($temp_numero_pedido)) {
        $temp_numero_pedido = date('dmyHm'); 
    }

    // Validação básica de campos obrigatórios
    if (empty($temp_numero_op) || empty($temp_produto_id) || empty($temp_quantidade_produzir) || empty($temp_data_emissao)) {
        $message = "Número da OP, Produto, Quantidade a Produzir e Data de Emissão são campos obrigatórios.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // Insere/Atualiza o número do pedido de venda se for novo
            if (!empty($temp_numero_pedido)) {
                $sql_insert_pedido = "INSERT IGNORE INTO pedidos_venda_lookup (numero_pedido) VALUES (?)";
                try {
                    $conn->execute_query($sql_insert_pedido, [$temp_numero_pedido]);
                } catch (mysqli_sql_exception $e) {
                    error_log("Erro ao inserir lookup de pedido de venda: " . $e->getMessage());
                }
            }
            
            // 1. Inserir a Ordem de Produção
            $sql_insert_op = "INSERT INTO ordens_producao (numero_op, numero_pedido, produto_id, maquina_id, quantidade_produzir, data_emissao, data_prevista_conclusao, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params_insert_op = [
                $temp_numero_op,
                $temp_numero_pedido,
                $temp_produto_id,
                $temp_maquina_id, 
                $temp_quantidade_produzir,
                $temp_data_emissao,
                $temp_data_prevista_conclusao,
                $temp_observacoes
            ];

            $conn->execute_query($sql_insert_op, $params_insert_op);
            $new_op_id = $conn->insert_id; // Pega o ID da OP recém-criada

            // Empenho de Materiais
            // Buscar itens da BoM para o produto da OP
            $sql_bom_items = "SELECT produto_filho_id, quantidade_necessaria FROM lista_materiais WHERE produto_pai_id = ? AND deleted_at IS NULL";
            $result_bom_items = $conn->execute_query($sql_bom_items, [$temp_produto_id]);

            if ($result_bom_items) {
                while ($bom_item = $result_bom_items->fetch_assoc()) {
                    $material_id = $bom_item['produto_filho_id'];
                    $quantidade_necessaria_por_unidade = $bom_item['quantidade_necessaria'];
                    
                    // Calcula a quantidade total a ser empenhada para esta OP
                    $quantidade_a_empenhar = $quantidade_necessaria_por_unidade * $temp_quantidade_produzir;

                    // 3. Inserir registro de empenho
                    // --- INÍCIO DA ALTERAÇÃO: Inserir quantidade_inicial (09/06/2025 - IA) ---
                    $sql_insert_empenho = "INSERT INTO empenho_materiais (produto_id, ordem_producao_id, quantidade_empenhada, quantidade_inicial, observacoes) VALUES (?, ?, ?, ?, ?)";
                    $params_insert_empenho = [
                        $material_id,
                        $new_op_id,
                        $quantidade_a_empenhar,
                        $quantidade_a_empenhar, // quantidade_inicial é igual  quantidade_empenhada no primeiro empenho
                        "Empenho automático para OP " . $temp_numero_op
                    ];
                    // --- FIM DA ALTERAÇÃO: Inserir quantidade_inicial ---
                    $conn->execute_query($sql_insert_empenho, $params_insert_empenho);

                    // 4. Atualizar o estoque_empenhado na tabela produtos
                    $sql_update_produto_empenhado = "UPDATE produtos SET estoque_empenhado = estoque_empenhado + ? WHERE id = ?";
                    $params_update_produto_empenhado = [
                        $quantidade_a_empenhar,
                        $material_id
                    ];
                    $conn->execute_query($sql_update_produto_empenhado, $params_update_produto_empenhado);
                }
                $result_bom_items->free();
            }

            $conn->commit();
            $message = "Ordem de Produção criada e materiais empenhados com sucesso!";
            $message_type = "success";
            $_POST = array(); // Limpa o formulário

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            // Erro de duplicidade na restrição UNIQUE (numero_op)
            if ($conn->errno == 1062 && strpos($e->getMessage(), 'numero_op') !== false) { 
                $message = "Erro: O número da OP já existe. Por favor, tente novamente (o número é gerado automaticamente).";
            } else {
                $message = "Erro ao criar Ordem de Produção ou empenhar materiais (SQL): " . $e->getMessage();
            }
            $message_type = "error";
            error_log("Erro fatal ao inserir Ordem de Produção/Empenho: " . $e->getMessage());
        }
    }
}

// Geração de número de OP aleatório único
$random_numero_op = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' || (isset($message_type) && $message_type == 'error')) {
    do {
        $ano_mes = date('ym'); // Ano (2 dígitos) + Mês (2 dígitos)
        $random_digits = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT); // 6 dígitos aleatórios
        $generated_op = $ano_mes . $random_digits;

        // Verifica se a OP já existe no banco de dados
        $sql_check_unique = "SELECT COUNT(*) FROM ordens_producao WHERE numero_op = ?";
        $result_check_unique = $conn->execute_query($sql_check_unique, [$generated_op]);
        
        $count = 0;
        if ($result_check_unique) {
            $row = $result_check_unique->fetch_row();
            $count = $row[0];
            $result_check_unique->free();
        } else {
            error_log("Erro ao verificar unicidade do número da OP: " . $conn->error);
            $count = 1; 
        }
    } while ($count > 0);
    $random_numero_op = $generated_op;
}

// Define a data prevista de conclusão padrão (hoje + 7 dias)
$default_data_prevista = date('Y-m-d', strtotime('+7 days'));

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

// Calcula a necessidade real
// NECESSIDADE REAL: Quantidade a Produzir da OP (desfeito o cálculo de subtração do estoque)
$necessidade_real = (float)($post_values['quantidade_produzir'] ?? 0.00); // Apenas a quantidade a produzir
$necessidade_real = max(0, $necessidade_real); // Garante que a necessidade não seja negativa, caso a entrada seja 0

?>

<h2>Criar Nova Ordem de Produção</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="adicionar.php" method="POST">
    <div class="form-group">
        <label for="numero_op">Número da OP:</label>
        <input type="text" id="numero_op" name="numero_op" value="<?php echo htmlspecialchars($post_values['numero_op'] ?? $random_numero_op); ?>" maxlength="50" required placeholder="Gerado automaticamente" readonly>
    </div>

    <div class="form-group">
        <label for="numero_pedido">Número do Pedido:</label>
        <select id="numero_pedido_select" name="numero_pedido" data-initial-value="<?php echo htmlspecialchars($post_values['numero_pedido'] ?? ''); ?>">
            <option value="">Nenhum/Selecione um Pedido</option>
            <?php
            $current_numero_pedido = $post_values['numero_pedido'] ?? '';
            foreach ($pedidos_venda as $pedido_opt) {
                $selected = ($current_numero_pedido == $pedido_opt) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($pedido_opt) . '" ' . $selected . '>' . htmlspecialchars($pedido_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="numero_pedido_text" style="display:none;" maxlength="50" placeholder="Digite um novo Nº Pedido">
        <a href="#" class="toggle-link" onclick="toggleInput(event, 'numero_pedido'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="produto_search">Produto:</label>
        <input type="text" id="produto_search" list="product_options" value="<?php echo $initial_produto_search_value; ?>" required placeholder="Digite para buscar produto (código ou nome)">
        <datalist id="product_options">
            <!-- Opções preenchidas via JavaScript -->
            <?php 
            // Se houver um produto pré-selecionado (após erro de POST), garanta que ele esteja na datalist
            if (!empty($initial_produto_id_hidden)) {
                $sql_selected_product = "SELECT id, nome, codigo, estoque_atual FROM produtos WHERE id = ? AND acabamento = 'Acabado'";
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
        <label for="maquina_id">Máquina Ideal:</label>
        <select id="maquina_id" name="maquina_id">
            <option value="">Selecione uma Máquina</option>
            <?php foreach ($maquinas_ativas as $maquina): ?>
                <option value="<?php echo htmlspecialchars($maquina['id']); ?>" <?php echo (isset($post_values['maquina_id']) && $post_values['maquina_id'] == $maquina['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($maquina['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="quantidade_produzir">Quantidade a Produzir:</label>
        <input type="number" id="quantidade_produzir" name="quantidade_produzir" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade_produzir'] ?? '0.00'); ?>" required placeholder="Ex: 100.50">
    </div>

    <div class="form-group">
        <label for="quantidade_em_estoque">Quantidade em Estoque:</label>
        <input type="number" id="quantidade_em_estoque" name="quantidade_em_estoque" step="0.01" value="<?php echo htmlspecialchars($current_product_stock); ?>" readonly placeholder="Estoque atual do produto">
    </div>

    <div class="form-group">
        <label for="data_emissao">Data de Emissão:</label>
        <input type="date" id="data_emissao" name="data_emissao" value="<?php echo htmlspecialchars($post_values['data_emissao'] ?? date('Y-m-d')); ?>" required>
    </div>

    <div class="form-group">
        <label for="data_prevista_conclusao">Data Prevista de Conclusão:</label>
        <input type="date" id="data_prevista_conclusao" name="data_prevista_conclusao" value="<?php echo htmlspecialchars($post_values['data_prevista_conclusao'] ?? $default_data_prevista); ?>">
    </div>

    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes" placeholder="Informações adicionais sobre a ordem de produção..."><?php echo htmlspecialchars($post_values['observacoes'] ?? ''); ?></textarea>
    </div>

    <button type="submit" class="button submit">Criar Ordem de Produção</button>
</form>

<a href="index.php" class="back-link">Voltar para a lista de Ordens de Produção</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const produtoSearchInput = document.getElementById('produto_search');
        const productOptionsDatalist = document.getElementById('product_options');
        const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
        const quantidadeEmEstoqueInput = document.getElementById('quantidade_em_estoque');
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
                fetch(`${baseUrl}/modules/ordens_producao/get_products_for_op.php?term=${encodeURIComponent(searchTerm)}`) 
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

        // Event listener para o input de pesquisa
        produtoSearchInput.addEventListener('input', function() {
            produtoIdHiddenInput.value = '';
            quantidadeEmEstoqueInput.value = '0.00'; 
            fetchProductsForSearch(this.value);
        });

        // Event listener para quando uma opção da datalist  selecionada
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
        const initialProductIdHidden = '<?php echo htmlspecialchars($post_values["produto_id_hidden"] ?? ""); ?>';
        const initialProductSearchValue = '<?php echo htmlspecialchars($post_values["produto_search"] ?? ""); ?>';
        const initialProductStockValue = '<?php echo htmlspecialchars($current_product_stock); ?>';

        if (initialProductIdHidden) {
            produtoSearchInput.value = initialProductSearchValue;
            produtoIdHiddenInput.value = initialProductIdHidden;
            quantidadeEmEstoqueInput.value = initialProductStockValue;
        }


        // Lógica para alternar entre select e input de texto para número_pedido (reutilizada de produtos)
        window.toggleInput = function(event, fieldName) {
            event.preventDefault(); 
            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = event.target;

            if (selectElement.style.display === 'none') {
                selectElement.style.display = 'block';
                selectElement.setAttribute('name', fieldName); 
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
            const fieldsToToggle = ['numero_pedido']; 
            fieldsToToggle.forEach(fieldName => {
                const selectElement = document.getElementById(fieldName + '_select');
                const textInputElement = document.getElementById(fieldName + '_text');

                if (textInputElement.style.display === 'block') {
                    selectElement.removeAttribute('name');
                    textInputElement.setAttribute('name', fieldName);
                } else {
                    textInputElement.removeAttribute('name');
                    selectElement.setAttribute('name', fieldName);
                }
            });
        });

        // Lógica para manter o estado do campo (select/input) após um POST com erro
        const fieldsToReconcile = ['numero_pedido'];
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
                    textInputElement.style.display = 'block';
                    textInputElement.value = initialValue; 
                    linkElement.textContent = 'Novo';
                } else {
                    selectElement.style.display = 'block';
                    textInputElement.style.display = 'none';
                    linkElement.textContent = 'Novo';
                }
                // Garante que o atributo 'name' esteja correto no carregamento da página,
                // caso o initialValue force um estado de 'input text' ou 'select'.
                if (textInputElement.style.display === 'block') {
                    textInputElement.setAttribute('name', fieldName);
                    selectElement.removeAttribute('name');
                } else {
                    selectElement.setAttribute('name', fieldName);
                    textInputElement.removeAttribute('name');
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
