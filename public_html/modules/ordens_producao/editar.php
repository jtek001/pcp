<?php
// modules/ordens_producao/editar.php
// Esta página contém o formulário para editar uma Ordem de Produção existente.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Brasília.
date_default_timezone_set('America/Sao_Paulo');

// Inclui os arquivos de configuraço e o cabeçalho
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variáveis para mensagens de sucesso/erro
$message = '';
$message_type = '';
$op_data = null; // Para armazenar os dados da OP a ser editada

// Função para buscar valores únicos de uma coluna em uma tabela específica
function getDistinctValues($conn, $table_name, $column_name, $where_clause = '') {
    $values = [];
    $sql = "SELECT DISTINCT " . $column_name . " FROM . " . $table_name . " WHERE " . $column_name . " IS NOT NULL AND " . $column_name . " != '' " . $where_clause . " ORDER BY " . $column_name . " ASC";
    
    // DEBUG: Imprime a query SQL sendo executada
    echo "\n";

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


// Pega o ID da Ordem de Produção da URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Se o ID for invlido, redireciona de volta para a lista
if ($id <= 0) {
    header("Location: " . BASE_URL . "/modules/ordens_producao/index.php?message=" . urlencode("ID da Ordem de Produção inválido para edição.") . "&type=error");
    exit();
}

// --- Lógica para buscar os dados da Ordem de Produção para preencher o formulário ---
// Inclui o nome e cdigo original do produto para preencher o campo de busca
$sql_select = "SELECT op.*, p.estoque_atual AS produto_estoque_atual, p.nome AS produto_nome_original, p.codigo AS produto_codigo_original FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id WHERE op.id = ?";
try {
    $result_op_data = $conn->execute_query($sql_select, [$id]);
    if ($result_op_data) {
        $op_data = $result_op_data->fetch_assoc();
        $result_op_data->free(); // Libera o resultado
        if (!$op_data) { // Se não encontrou a OP
            $message = "Ordem de Produção não encontrada para edição.";
            $message_type = "error";
        }
    } else {
        $message = "Erro ao carregar dados da OP: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar dados da OP: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro fatal ao carregar dados da OP (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar dados da OP: " . $e->getMessage());
}

// Determina se a OP está concluída ou cancelada (para bloquear campos)
$is_op_concluida_ou_cancelada = ($op_data['status'] ?? '') === 'concluida' || ($op_data['status'] ?? '') === 'cancelada';


// --- Lógica para processar a submissão do formulário de edição ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0 && $op_data) {
    // Captura a quantidade a produzir ORIGINAL da OP antes de sobrescrever com o POST
    $original_quantidade_produzir_op = $op_data['quantidade_produzir'];

    // Sanitiza e valida as entradas
    // Numero OP é readonly no HTML, usa o valor original do DB
    $temp_numero_op = $op_data['numero_op']; 
    
    // Para campos desabilitados/readonly que devem ser enviados:
    // Pega o valor do POST, ou se o campo for disabled (não enviado), pega do op_data original.
    $temp_numero_pedido = sanitizeInput(isset($_POST['numero_pedido']) ? $_POST['numero_pedido'] : ($op_data['numero_pedido'] ?? ''));
    $temp_produto_id = sanitizeInput(isset($_POST['produto_id']) ? $_POST['produto_id'] : ($op_data['produto_id'] ?? ''));
    $temp_maquina_id = sanitizeInput(isset($_POST['maquina_id']) ? $_POST['maquina_id'] : ($op_data['maquina_id'] ?? ''));

    $temp_quantidade_produzir = (float) sanitizeInput(isset($_POST['quantidade_produzir']) ? $_POST['quantidade_produzir'] : 0.0);
    $temp_data_emissao = sanitizeInput(isset($_POST['data_emissao']) ? $_POST['data_emissao'] : '');
    $temp_data_prevista_conclusao = isset($_POST['data_prevista_conclusao']) && $_POST['data_prevista_conclusao'] !== '' ? sanitizeInput($_POST['data_prevista_conclusao']) : NULL;
    $temp_status = sanitizeInput(isset($_POST['status']) ? $_POST['status'] : 'pendente');
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');

    // Se o status for "concluida" e não houver data_conclusao, define NOW()
    $temp_data_conclusao = $op_data['data_conclusao']; // Mantém o valor original
    if ($temp_status === 'concluida' && empty($op_data['data_conclusao'])) {
        $temp_data_conclusao = date('Y-m-d H:i:s'); // Define a data/hora atual
    } elseif ($temp_status !== 'concluida') {
        $temp_data_conclusao = NULL; // Limpa se o status não for mais concluído
    }

    // --- INÍCIO DA ALTERAÇÃO: Validação de Quantidade e Atualização de Status (09/06/2025 - IA) ---
    // Recalcula total produzido para o display (garante que é o valor mais atual para validação)
    $total_produzido_op_validation = 0.00; // Inicializa como float
    $sql_total_produzido_validation = "SELECT SUM(COALESCE(quantidade_produzida, 0)) AS total FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL";
    $result_total_validation = $conn->execute_query($sql_total_produzido_validation, [$id]);
    if ($result_total_validation && $row_total_validation = $result_total_validation->fetch_assoc()) {
        $total_produzido_op_validation = (float)($row_total_validation['total'] ?? 0.00); // Garante que é float
        $result_total_validation->free();
    }

    // Validação: Quantidade a Produzir não pode ser menor que a Quantidade Produzida
    if ($temp_quantidade_produzir < $total_produzido_op_validation) {
        $message = "A 'Quantidade a Produzir' (" . number_format($temp_quantidade_produzir, 2, ',', '.') . ") não pode ser menor que a 'Quantidade Produzida' (" . number_format($total_produzido_op_validation, 2, ',', '.') . ").";
        $message_type = "error";
    } 
    // Se a quantidade a produzir for IGUAL  quantidade produzida, altera o status para 'concluida'
    elseif ($temp_quantidade_produzir == $total_produzido_op_validation && $total_produzido_op_validation > 0) { // Garante que não conclui se ambos forem zero
        $temp_status = 'concluida';
        if (empty($temp_data_conclusao)) { // Define data de conclusão se não estiver definida
            $temp_data_conclusao = date('Y-m-d H:i:s');
        }
    }
    // --- FIM DA ALTERAÃO: Validação de Quantidade e Atualização de Status ---


    // Validação básica de campos obrigatórios (mantida)
    if (empty($temp_numero_op) || empty($temp_produto_id) || empty($temp_quantidade_produzir) || empty($temp_data_emissao)) {
        $message = "Número da OP, Produto, Quantidade a Produzir e Data de Emissão são campos obrigatórios.";
        $message_type = "error";
    } 
    // Só prossegue com a transação se não houver mensagens de erro de validação
    if (empty($message)) { 
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

            // Atualizar Empenho de Materiais ao Mudar Qtd da OP
            $new_op_qty = $temp_quantidade_produzir;
            $produto_pai_id_op = $op_data['produto_id'];

            if ($new_op_qty != $original_quantidade_produzir_op) {
                // 1. Buscar itens da BoM para o produto desta OP
                $sql_bom_items = "SELECT produto_filho_id, quantidade_necessaria FROM lista_materiais WHERE produto_pai_id = ? AND deleted_at IS NULL";
                $result_bom_items = $conn->execute_query($sql_bom_items, [$produto_pai_id_op]);

                if ($result_bom_items) {
                    while ($bom_item = $result_bom_items->fetch_assoc()) {
                        $material_id = $bom_item['produto_filho_id'];
                        $quantidade_necessaria_por_unidade = $bom_item['quantidade_necessaria'];
                        
                        // Calcula o empenho antigo e o novo para este material
                        $old_empenho_for_item = $quantidade_necessaria_por_unidade * $original_quantidade_produzir_op;
                        $new_empenho_for_item = $quantidade_necessaria_por_unidade * $new_op_qty;
                        
                        // Diferença líquida no empenho para este material
                        $delta_empenho = $new_empenho_for_item - $old_empenho_for_item;

                        // Atualiza o estoque_empenhado na tabela produtos
                        $sql_update_produto_empenhado = "UPDATE produtos SET estoque_empenhado = estoque_empenhado + ? WHERE id = ?";
                        $conn->execute_query($sql_update_produto_empenhado, [$delta_empenho, $material_id]);

                        // Atualiza ou "desemprenha" o registro em empenho_materiais
                        $sql_check_empenho_record = "SELECT id FROM empenho_materiais WHERE produto_id = ? AND ordem_producao_id = ? AND deleted_at IS NULL";
                        $result_check_empenho = $conn->execute_query($sql_check_empenho_record, [$material_id, $id]);
                        $empenho_record_exists = ($result_check_empenho && $result_check_empenho->num_rows > 0);
                        $result_check_empenho->free();

                        if ($empenho_record_exists) {
                            if ($new_empenho_for_item <= 0) {
                                // Se a nova quantidade é zero ou negativa, "desempenha" (soft delete) o registro
                                $sql_soft_delete_empenho = "UPDATE empenho_materiais SET deleted_at = NOW() WHERE produto_id = ? AND ordem_producao_id = ?";
                                $conn->execute_query($sql_soft_delete_empenho, [$material_id, $id]);
                            } else {
                                // Se ainda há empenho, atualiza a quantidade
                                $sql_update_empenho = "UPDATE empenho_materiais SET quantidade_empenhada = ?, quantidade_inicial = ?, data_empenho = NOW() WHERE produto_id = ? AND ordem_producao_id = ?";
                                $conn->execute_query($sql_update_empenho, [$new_empenho_for_item, $new_empenho_for_item, $material_id, $id]);
                            }
                        } elseif ($new_empenho_for_item > 0) {
                            // Se não existia empenho e agora deveria existir (ex: OP foi de 0 para >0, ou BoM foi adicionada)
                            // Inserir um novo registro de empenho
                            $sql_insert_empenho = "INSERT INTO empenho_materiais (produto_id, ordem_producao_id, quantidade_empenhada, quantidade_inicial, observacoes, data_empenho) VALUES (?, ?, ?, ?, ?, NOW())";
                            $conn->execute_query($sql_insert_empenho, [$material_id, $id, $new_empenho_for_item, $new_empenho_for_item, "Empenho criado na edição da OP " . $temp_numero_op]);
                        }
                    }
                    $result_bom_items->free();
                }
            }


            // Prepara a consulta SQL para atualização da Ordem de Produção
            $sql_update = "UPDATE ordens_producao SET numero_op = ?, numero_pedido = ?, produto_id = ?, maquina_id = ?, quantidade_produzir = ?, data_emissao = ?, data_prevista_conclusao = ?, status = ?, observacoes = ?, data_conclusao = ? WHERE id = ?";
            
            // Array de parâmetros para execute_query
            $params = [
                $temp_numero_op,
                $temp_numero_pedido,
                $temp_produto_id,
                $temp_maquina_id,
                $temp_quantidade_produzir,
                $temp_data_emissao,
                $temp_data_prevista_conclusao,
                $temp_status,
                $temp_observacoes,
                $temp_data_conclusao,
                $id
            ];

            $result_update = $conn->execute_query($sql_update, $params);

            if ($result_update === TRUE) {
                $message = "Ordem de Produção atualizada com sucesso!";
                $message_type = "success";
                // Atualiza os dados na variável $op_data para exibir os novos valores
                $op_data['numero_op'] = $temp_numero_op;
                $op_data['numero_pedido'] = $temp_numero_pedido;
                $op_data['produto_id'] = $temp_produto_id;
                $op_data['maquina_id'] = $temp_maquina_id;
                $op_data['quantidade_produzir'] = $temp_quantidade_produzir;
                $op_data['data_emissao'] = $temp_data_emissao;
                $op_data['data_prevista_conclusao'] = $temp_data_prevista_conclusao;
                $op_data['status'] = $temp_status;
                $op_data['observacoes'] = $temp_observacoes;
                $op_data['data_conclusao'] = $temp_data_conclusao;
            } else {
                throw new mysqli_sql_exception("Erro ao atualizar Ordem de Produção: " . $conn->error);
            }

            $conn->commit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro na transação de atualização de Ordem de Produção/Empenho: " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal na transação de atualização de OP/Empenho: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Calcula a quantidade total já produzida para esta OP (soma dos apontamentos)
$total_produzido_op = 0.00; // Inicializa como float
if ($op_data && !empty($op_data['id'])) {
    $sql_total_produzido = "SELECT SUM(COALESCE(quantidade_produzida, 0)) AS total FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL";
    $result_total = $conn->execute_query($sql_total_produzido, [$op_data['id']]);
    if ($result_total && $row_total = $result_total->fetch_assoc()) {
        $total_produzido_op = (float)($row_total['total'] ?? 0.00); // Garante que é float
        $result_total->free();
    }
}
// Formata o número com ponto para input type="number" (value), e com vrgula para exibição de texto
$quantidade_produzida_display_for_value = number_format($total_produzido_op, 2, '.', ''); // Ponto para o atributo value
$quantidade_produzida_display_for_text = number_format($total_produzido_op, 2, ',', '.'); // Vírgula para texto visual (fora do input)


// Calcula a necessidade real: Quantidade a Produzir da OP - Estoque Atual do Produto
$necessidade_real = ($op_data['quantidade_produzir'] ?? 0) - ($op_data['produto_estoque_atual'] ?? 0); 
$necessidade_real = max(0, $necessidade_real); // Garante que a necessidade não seja negativa


// Dados do produto original para preencher o campo de busca (JS)
$initial_product_search_value = '';
if (isset($op_data['produto_id']) && !empty($op_data['produto_id'])) {
    $initial_product_search_value = $op_data['produto_nome_original'] . ' (' . $op_data['produto_codigo_original'] . ')';
}

?>

<h2>Editar Ordem de Produção</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($op_data): ?>
    <form action="editar.php?id=<?php echo $op_data['id']; ?>" method="POST">
        <?php 
        // Define o estado desabilitado/somente leitura.
        // O botão de submit será sempre ativo, pois o status ainda pode ser mudado.
        $is_op_concluida_ou_cancelada = ($op_data['status'] ?? '') === 'concluida' || ($op_data['status'] ?? '') === 'cancelada';
        
        // Bloqueia campos se a OP estiver concluída ou cancelada
        $disabled_html_attr = $is_op_concluida_ou_cancelada ? 'disabled' : ''; // Para selects
        $readonly_html_attr = $is_op_concluida_ou_cancelada ? 'readonly' : ''; // Para inputs, textarea
        $toggle_link_class = $is_op_concluida_ou_cancelada ? 'disabled-link' : ''; 
        ?>
        <div class="form-group">
            <label for="numero_op">Número da OP:</label>
            <input type="text" id="numero_op" name="numero_op" value="<?php echo htmlspecialchars($post_values['numero_op'] ?? $op_data['numero_op']); ?>" maxlength="50" required placeholder="Ex: OP-2025-001" readonly>
        </div>

        <div class="form-group">
            <label for="numero_pedido">Número do Pedido:</label>
            <select id="numero_pedido_select" name="numero_pedido_display" data-initial-value="<?php echo htmlspecialchars($post_values['numero_pedido'] ?? $op_data['numero_pedido'] ?? ''); ?>" <?php echo $disabled_html_attr; ?>>
                <option value="">Nenhum/Selecione um Pedido</option>
                <?php
                $current_numero_pedido = $post_values['numero_pedido'] ?? $op_data['numero_pedido'];
                if (!empty($current_numero_pedido) && !in_array($current_numero_pedido, $pedidos_venda)) {
                    echo '<option value="' . htmlspecialchars($current_numero_pedido) . '" selected>' . htmlspecialchars($current_numero_pedido) . '</option>';
                }
                foreach ($pedidos_venda as $pedido_opt) {
                    $selected = ($current_numero_pedido == $pedido_opt) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($pedido_opt) . '" ' . $selected . '>' . htmlspecialchars($pedido_opt) . '</option>';
                }
                ?>
            </select>
            <input type="text" id="numero_pedido_text" style="display:none;" maxlength="50" placeholder="Digite um novo Nº Pedido" <?php echo $readonly_html_attr; ?>>
            <a href="#" class="toggle-link <?php echo $toggle_link_class; ?>" onclick="toggleInput(event, 'numero_pedido'); return false;">Novo</a>
            <?php if ($is_op_concluida_ou_cancelada): // Input hidden para enviar valor quando select está disabled ?>
                <input type="hidden" name="numero_pedido" value="<?php echo htmlspecialchars($op_data['numero_pedido'] ?? ''); ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="produto_search">Produto:</label>
            <input type="text" id="produto_search" list="product_options" value="<?php echo htmlspecialchars($post_values['produto_search'] ?? $initial_product_search_value); ?>" required placeholder="Digite para buscar produto (código ou nome)" <?php echo $readonly_html_attr; ?>>
            <datalist id="product_options">
                <?php 
                // Se houver um produto pré-selecionado (seja do DB ou do POST em caso de erro), garanta que ele esteja na datalist
                if (!empty($op_data['produto_id']) || !empty($post_values['produto_id_hidden'])) {
                    $display_product_id = $post_values['produto_id_hidden'] ?? $op_data['produto_id'];
                    $display_product_name = $post_values['produto_search'] ?? ($op_data['produto_nome_original'] . ' (' . $op_data['produto_codigo_original'] . ')');
                    $display_product_stock = $op_data['produto_estoque_atual']; // Usamos o estoque atual real do produto
                    echo '<option data-id="' . htmlspecialchars($display_product_id) . '" data-stock="' . htmlspecialchars($display_product_stock) . '" value="' . htmlspecialchars($display_product_name) . '"></option>';
                }
                ?>
            </datalist>
            <input type="hidden" id="produto_id_hidden" name="produto_id" value="<?php echo htmlspecialchars($post_values['produto_id_hidden'] ?? ($op_data['produto_id'] ?? '')); ?>" required>
        </div>

        <div class="form-group">
            <label for="maquina_id">Máquina Ideal:</label>
            <select id="maquina_id" name="maquina_id_display" <?php echo $disabled_html_attr; ?>>
                <option value="">Selecione uma Máquina</option>
                <?php
                $current_maquina_id = $post_values['maquina_id'] ?? $op_data['maquina_id'];
                foreach ($maquinas_ativas as $maquina): ?>
                    <option value="<?php echo htmlspecialchars($maquina['id']); ?>" <?php echo ($current_maquina_id == $maquina['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($maquina['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($is_op_concluida_ou_cancelada): // Input hidden para enviar valor quando select está disabled ?>
                <input type="hidden" name="maquina_id" value="<?php echo htmlspecialchars($op_data['maquina_id'] ?? ''); ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="quantidade_produzir">Quantidade a Produzir:</label>
            <input type="number" id="quantidade_produzir" name="quantidade_produzir" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade_produzir'] ?? $op_data['quantidade_produzir']); ?>" required placeholder="Ex: 100.50" <?php echo $readonly_html_attr; ?>>
        </div>

        <div class="form-group">
            <label for="quantidade_produzida">Quantidade Produzida:</label>
            <input type="number" id="quantidade_produzida" name="quantidade_produzida_display" step="0.01" value="<?php echo $quantidade_produzida_display_for_value; ?>" readonly>
        </div>

        <div class="form-group">
            <label for="data_emissao">Data de Emissão:</label>
            <input type="date" id="data_emissao" name="data_emissao" value="<?php echo htmlspecialchars($post_values['data_emissao'] ?? $op_data['data_emissao']); ?>" required <?php echo $readonly_html_attr; ?>>
        </div>

        <div class="form-group">
            <label for="data_prevista_conclusao">Data Prevista de Conclusão:</label>
            <input type="date" id="data_prevista_conclusao" name="data_prevista_conclusao" value="<?php echo htmlspecialchars($post_values['data_prevista_conclusao'] ?? $op_data['data_prevista_conclusao'] ?? ''); ?>" <?php echo $readonly_html_attr; ?>>
        </div>
        
        <div class="form-group">
            <label for="status">Status:</label>
            <select id="status" name="status" required>
                <option value="pendente" <?php echo (($post_values['status'] ?? $op_data['status']) == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                <option value="em_producao" <?php echo (($post_values['status'] ?? $op_data['status']) == 'em_producao') ? 'selected' : ''; ?>>Em Produção</option>
                <option value="concluida" <?php echo (($post_values['status'] ?? $op_data['status']) == 'concluida') ? 'selected' : ''; ?>>Concluída</option>
                <option value="cancelada" <?php echo (($post_values['status'] ?? $op_data['status']) == 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
            </select>
        </div>

        <div class="form-group full-width">
            <label for="observacoes">Observações:</label>
            <textarea id="observacoes" name="observacoes" placeholder="Informações adicionais sobre a ordem de produção..." <?php echo $readonly_html_attr; ?>><?php echo htmlspecialchars($post_values['observacoes'] ?? $op_data['observacoes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button submit">Atualizar Ordem de Produção</button>
    </form>
<?php else: ?>
    <p class="error" style="text-align: center;">Ordem de Produção não encontrada para edição.</p>
<?php endif; ?>

<a href="index.php" class="back-link">Voltar para a lista de Ordens de Produção</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const produtoSearchInput = document.getElementById('produto_search');
        const productOptionsDatalist = document.getElementById('product_options');
        const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
        // Removido quantidadeEmEstoqueInput, pois agora é quantidadeProduzida
        const quantidadeProduzidaDisplay = document.getElementById('quantidade_produzida'); // Nova variável para o campo

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
            // Removido: quantidadeEmEstoqueInput.value = '0.00'; 
            // Não limpamos quantidade produzida aqui
            fetchProductsForSearch(this.value);
        });

        // Event listener para quando uma opção da datalist é selecionada
        produtoSearchInput.addEventListener('change', function() {
            const selectedOption = Array.from(productOptionsDatalist.options).find(option => option.value === this.value);
            if (selectedOption) {
                produtoIdHiddenInput.value = selectedOption.getAttribute('data-id');
                // Não atualizamos quantidadeProduzidaDisplay aqui, pois ela vem do banco de dados da OP.
                // quantidadeEmEstoqueInput.value = parseFloat(selectedOption.getAttribute('data-stock')).toFixed(2); // Removido
            } else {
                produtoIdHiddenInput.value = '';
                // quantidadeEmEstoqueInput.value = '0.00'; // Removido
            }
        });

        // Lógica para manter o estado do campo produto se o formulário foi submetido com erro
        const initialProductIdHidden = '<?php echo htmlspecialchars($post_values["produto_id"] ?? ($op_data["produto_id"] ?? "")); ?>'; 
        const initialProductSearchValue = '<?php echo htmlspecialchars($post_values["produto_search"] ?? $initial_product_search_value); ?>';
        // Removido initialProductStockValue, pois o campo agora é Quantidade Produzida

        if (initialProductIdHidden) {
            produtoSearchInput.value = initialProductSearchValue;
            produtoIdHiddenInput.value = initialProductIdHidden;
            // quantidadeEmEstoqueInput.value = initialProductStockValue; // Removido
        }


        // Lógica para alternar entre select e input de texto
        window.toggleInput = function(event, fieldName) {
            event.preventDefault(); 
            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = event.target;

            // Verifica se o formulário está bloqueado antes de permitir o toggle
            const isOpConcluidaOuCancelada = <?php echo json_encode($is_op_concluida_ou_cancelada); ?>;
            if (isOpConcluidaOuCancelada) { 
                return; // Não permite o toggle se a OP está concluída ou cancelada
            }

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
            const isOpConcluidaOuCancelada = <?php echo json_encode($is_op_concluida_ou_cancelada); ?>;
            
            // Gerencia os nomes dos selects/inputs alternáveis
            const fieldsToToggle = ['numero_pedido', 'maquina_id']; 
            fieldsToToggle.forEach(fieldName => {
                const selectElement = document.getElementById(fieldName + '_select');
                const textInputElement = document.getElementById(fieldName + '_text'); 

                // Se o campo visível é um input de texto (modo "Novo")
                if (textInputElement && textInputElement.style.display === 'block') {
                    selectElement.removeAttribute('name');
                    textInputElement.setAttribute('name', fieldName);
                } else if (selectElement) { // Se o campo visível é um select (modo "Selecionar existente")
                    textInputElement.removeAttribute('name'); 
                    selectElement.setAttribute('name', fieldName);
                }
            });

            // Se a OP está concluída ou cancelada, garante que apenas os hidden inputs e o status sejam enviados.
            if (isOpConcluidaOuCancelada) {
                document.getElementById('numero_pedido_select').removeAttribute('name');
                if(document.getElementById('numero_pedido_text')) {
                    document.getElementById('numero_pedido_text').removeAttribute('name');
                }
                document.getElementById('produto_search').removeAttribute('name');
                document.getElementById('maquina_id').removeAttribute('name'); 
            }
        });

        // Lógica para manter o estado do campo (select/input) após um POST com erro ou edição
        const fieldsToReconcile = ['numero_pedido'];
        fieldsToReconcile.forEach(fieldName => {
            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = textInputElement.nextElementSibling; 
            
            // Verifica se os elementos existem antes de tentar manipular
            if (!selectElement || !textInputElement || !linkElement) {
                console.warn(`DEBUG: Elementos para reconciliação do campo ${fieldName} não encontrados.`);
                return; 
            }

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
                    selectElement.removeAttribute('name');
                    textInputElement.style.display = 'block';
                    textInputElement.setAttribute('name', fieldName);
                    textInputElement.value = initialValue; 
                    linkElement.textContent = 'Novo';
                } else {
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
