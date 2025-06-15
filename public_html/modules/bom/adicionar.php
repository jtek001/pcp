<?php
// modules/bom/adicionar.php
// Esta página contém o formulário para adicionar um novo item (componente) a uma Lista de Materiais (BoM).

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

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitiza e valida as entradas
    $temp_produto_pai_id = sanitizeInput(isset($_POST['produto_pai_id_hidden']) ? $_POST['produto_pai_id_hidden'] : '');
    $temp_produto_filho_id = sanitizeInput(isset($_POST['produto_filho_id_hidden']) ? $_POST['produto_filho_id_hidden'] : '');
    $temp_quantidade_necessaria = (float) sanitizeInput(isset($_POST['quantidade_necessaria']) ? $_POST['quantidade_necessaria'] : 0.0);
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');

    // Validação básica de campos obrigatórios
    if (empty($temp_produto_pai_id) || empty($temp_produto_filho_id) || empty($temp_quantidade_necessaria) || $temp_quantidade_necessaria <= 0) {
        $message = "Produto Pai, Produto Filho e Quantidade Necessária (maior que zero) são campos obrigatórios.";
        $message_type = "error";
    } elseif ($temp_produto_pai_id == $temp_produto_filho_id) {
        $message = "O Produto Pai e o Produto Filho não podem ser o mesmo item na BoM.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // 1. Inserir o item na lista de materiais
            $sql_insert_bom = "INSERT INTO lista_materiais (produto_pai_id, produto_filho_id, quantidade_necessaria, observacoes) VALUES (?, ?, ?, ?)";
            
            $params_insert_bom = [
                $temp_produto_pai_id,
                $temp_produto_filho_id,
                $temp_quantidade_necessaria,
                $temp_observacoes
            ];

            $conn->execute_query($sql_insert_bom, $params_insert_bom);

            $conn->commit();
            $message = "Item da Lista de Materiais cadastrado com sucesso!";
            $message_type = "success";
            $_POST = array(); // Limpa o formulário

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            // Erro de duplicidade na restrição UNIQUE (produto_pai_id, produto_filho_id)
            if ($conn->errno == 1062) { 
                $message = "Erro: Este Produto Filho já está listado para este Produto Pai na BoM.";
            } else {
                $message = "Erro ao cadastrar item da Lista de Materiais (SQL): " . $e->getMessage();
            }
            $message_type = "error";
            error_log("Erro fatal ao inserir item da BoM: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Variáveis para manter o estado dos campos de pesquisa de produto em caso de erro no POST
$initial_produto_pai_search_value = htmlspecialchars($post_values['produto_pai_search'] ?? '');
$initial_produto_pai_id_hidden = htmlspecialchars($post_values['produto_pai_id_hidden'] ?? '');

$initial_produto_filho_search_value = htmlspecialchars($post_values['produto_filho_search'] ?? '');
$initial_produto_filho_id_hidden = htmlspecialchars($post_values['produto_filho_id_hidden'] ?? '');

?>

<h2>Adicionar Item à Lista de Materiais (BoM)</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="adicionar.php" method="POST">
    <div class="form-group">
        <label for="produto_pai_search">Produto Pai:</label>
        <input type="text" id="produto_pai_search" list="product_pai_options" value="<?php echo $initial_produto_pai_search_value; ?>" required placeholder="Digite para buscar produto pai (código ou nome)">
        <datalist id="product_pai_options">
            <!-- Opções preenchidas via JavaScript -->
            <?php 
            // Se houver um produto pai pré-selecionado (após erro de POST), garanta que ele esteja na datalist
            if (!empty($initial_produto_pai_id_hidden)) {
                $sql_selected_product_pai = "SELECT id, nome, codigo FROM produtos WHERE id = ? AND deleted_at IS NULL"; // REMOVIDO: acabamento = 'Acabado'
                $result_selected_product_pai = $conn->execute_query($sql_selected_product_pai, [$initial_produto_pai_id_hidden]);
                if ($result_selected_product_pai && $row_selected_product_pai = $result_selected_product_pai->fetch_assoc()) {
                    echo '<option data-id="' . htmlspecialchars($row_selected_product_pai['id']) . '" value="' . htmlspecialchars($row_selected_product_pai['nome'] . ' (' . $row_selected_product_pai['codigo'] . ')') . '"></option>';
                }
            }
            ?>
        </datalist>
        <input type="hidden" id="produto_pai_id_hidden" name="produto_pai_id_hidden" value="<?php echo $initial_produto_pai_id_hidden; ?>" required>
    </div>

    <div class="form-group">
        <label for="produto_filho_search">Produto Filho (Componente/Material):</label>
        <input type="text" id="produto_filho_search" list="product_filho_options" value="<?php echo $initial_produto_filho_search_value; ?>" required placeholder="Digite para buscar produto filho (código ou nome)">
        <datalist id="product_filho_options">
            <!-- Opções preenchidas via JavaScript -->
            <?php 
            if (!empty($initial_produto_filho_id_hidden)) {
                $sql_selected_product_filho = "SELECT id, nome, codigo FROM produtos WHERE id = ? AND deleted_at IS NULL"; // Sem filtro de acabamento
                $result_selected_product_filho = $conn->execute_query($sql_selected_product_filho, [$initial_produto_filho_id_hidden]);
                if ($result_selected_product_filho && $row_selected_product_filho = $result_selected_product_filho->fetch_assoc()) {
                    echo '<option data-id="' . htmlspecialchars($row_selected_product_filho['id']) . '" value="' . htmlspecialchars($row_selected_product_filho['nome'] . ' (' . $row_selected_product_filho['codigo'] . ')') . '"></option>';
                }
            }
            ?>
        </datalist>
        <input type="hidden" id="produto_filho_id_hidden" name="produto_filho_id_hidden" value="<?php echo $initial_produto_filho_id_hidden; ?>" required>
    </div>

    <div class="form-group">
        <label for="quantidade_necessaria">Quantidade Necessária (por unidade do Pai):</label>
        <input type="number" id="quantidade_necessaria" name="quantidade_necessaria" step="0.0001" value="<?php echo htmlspecialchars($post_values['quantidade_necessaria'] ?? '0.0000'); ?>" required min="0.0001" placeholder="Ex: 2.5000">
    </div>

    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes" placeholder="Observações sobre este item na BoM..."><?php echo htmlspecialchars($post_values['observacoes'] ?? ''); ?></textarea>
    </div>

    <button type="submit" class="button submit">Adicionar Item à BoM</button>
</form>

<a href="index.php" class="back-link">Voltar para a Lista de Materiais (BoM)</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseUrl = '<?php echo BASE_URL; ?>'; 

        // --- Lógica para Produto Pai ---
        const produtoPaiSearchInput = document.getElementById('produto_pai_search');
        const productPaiOptionsDatalist = document.getElementById('product_pai_options');
        const produtoPaiIdHiddenInput = document.getElementById('produto_pai_id_hidden');
        let debounceTimeoutPai;

        // DEBUG: Verificar se os elementos do Produto Pai existem
        console.log("DEBUG BOM: produtoPaiSearchInput:", produtoPaiSearchInput);
        console.log("DEBUG BOM: productPaiOptionsDatalist:", productPaiOptionsDatalist);
        console.log("DEBUG BOM: produtoPaiIdHiddenInput:", produtoPaiIdHiddenInput);
        if (!produtoPaiSearchInput || !productPaiOptionsDatalist || !produtoPaiIdHiddenInput) {
            console.error("ERRO BOM: Elementos HTML para Produto Pai não encontrados. Verifique IDs.");
            return; 
        }

        function fetchProductsForPaiSearch(searchTerm) {
            if (searchTerm.length < 2) { 
                productPaiOptionsDatalist.innerHTML = ''; 
                return;
            }
            clearTimeout(debounceTimeoutPai);
            debounceTimeoutPai = setTimeout(() => {
                // REMOVIDO: filtro por acabamento 'Acabado' para Produto Pai
                fetch(`${baseUrl}/modules/bom/get_products_for_bom.php?term=${encodeURIComponent(searchTerm)}&type=all`) // 'type=all' para listar todos
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        productPaiOptionsDatalist.innerHTML = ''; 
                        data.forEach(product => {
                            const option = document.createElement('option');
                            option.value = product.text; 
                            option.setAttribute('data-id', product.id);
                            productPaiOptionsDatalist.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Erro ao buscar produtos pai:', error));
            }, 300); 
        }

        produtoPaiSearchInput.addEventListener('input', function() {
            produtoPaiIdHiddenInput.value = '';
            fetchProductsForPaiSearch(this.value);
        });

        produtoPaiSearchInput.addEventListener('change', function() {
            const selectedOption = Array.from(productPaiOptionsDatalist.options).find(option => option.value === this.value);
            if (selectedOption) {
                produtoPaiIdHiddenInput.value = selectedOption.getAttribute('data-id');
            } else {
                produtoPaiIdHiddenInput.value = '';
                alert('Por favor, selecione um Produto Pai válido da lista de sugestões.'); // Alerta de validação
            }
        });

        // --- Lógica para Produto Filho ---
        const produtoFilhoSearchInput = document.getElementById('produto_filho_search');
        const productFilhoOptionsDatalist = document.getElementById('product_filho_options');
        const produtoFilhoIdHiddenInput = document.getElementById('produto_filho_id_hidden');
        let debounceTimeoutFilho;

        // DEBUG: Verificar se os elementos do Produto Filho existem
        console.log("DEBUG BOM: produtoFilhoSearchInput:", produtoFilhoSearchInput);
        console.log("DEBUG BOM: productFilhoOptionsDatalist:", productFilhoOptionsDatalist);
        console.log("DEBUG BOM: produtoFilhoIdHiddenInput:", produtoFilhoIdHiddenInput);
        if (!produtoFilhoSearchInput || !productFilhoOptionsDatalist || !produtoFilhoIdHiddenInput) {
            console.error("ERRO BOM: Elementos HTML para Produto Filho não encontrados. Verifique IDs.");
            return;
        }

        function fetchProductsForFilhoSearch(searchTerm) {
            if (searchTerm.length < 2) { 
                productFilhoOptionsDatalist.innerHTML = ''; 
                return;
            }
            clearTimeout(debounceTimeoutFilho);
            debounceTimeoutFilho = setTimeout(() => {
                // Não filtra por acabamento para Produto Filho (pode ser qualquer tipo)
                fetch(`${baseUrl}/modules/bom/get_products_for_bom.php?term=${encodeURIComponent(searchTerm)}&type=filho`) 
                    .then(response => {
                        if (!response.ok) { // Adiciona verificação de erro HTTP
                            throw new Error(`HTTP error! status: ${response.status}, Response: ${response.text()}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        productFilhoOptionsDatalist.innerHTML = ''; 
                        data.forEach(product => {
                            const option = document.createElement('option');
                            option.value = product.text; 
                            option.setAttribute('data-id', product.id);
                            productFilhoOptionsDatalist.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Erro ao buscar produtos filho:', error));
            }, 300); 
        }

        produtoFilhoSearchInput.addEventListener('input', function() {
            produtoFilhoIdHiddenInput.value = '';
            fetchProductsForFilhoSearch(this.value);
        });

        produtoFilhoSearchInput.addEventListener('change', function() {
            const selectedOption = Array.from(productFilhoOptionsDatalist.options).find(option => option.value === this.value);
            if (selectedOption) {
                produtoFilhoIdHiddenInput.value = selectedOption.getAttribute('data-id');
            } else {
                produtoFilhoIdHiddenInput.value = '';
                alert('Por favor, selecione um Produto Filho válido da lista de sugestões.'); // Alerta de validação
            }
        });

        // --- Lógica de Validação no Submit ---
        document.querySelector('form').addEventListener('submit', function(event) {
            // Revalida os campos hidden antes de enviar
            if (!produtoPaiIdHiddenInput.value) {
                alert('Por favor, selecione um Produto Pai válido da lista.');
                event.preventDefault();
                return;
            }
            if (!produtoFilhoIdHiddenInput.value) {
                alert('Por favor, selecione um Produto Filho válido da lista.');
                event.preventDefault();
                return;
            }
            // Outras validações required do HTML já atuam
        });
        
        // --- Reconciliação do estado inicial (se houver POST com erro) ---
        const initialProdutoPaiIdHiddenSaved = produtoPaiIdHiddenInput.value;
        const initialProdutoFilhoIdHiddenSaved = produtoFilhoIdHiddenInput.value;

        if (initialProdutoPaiIdHiddenSaved) {
            fetchProductsForPaiSearch(produtoPaiSearchInput.value); // Re-popula para garantir
        }
        if (initialProdutoFilhoIdHiddenSaved) {
            fetchProductsForFilhoSearch(produtoFilhoSearchInput.value); // Re-popula para garantir
        }
    });
</script>
<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
