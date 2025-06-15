<?php
// modules/empenho_manual/editar.php
// Esta página permite editar um empenho manual existente.

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
$empenho_data = null; // Dados do empenho a ser editado

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

// Pega o ID do empenho da URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Se o ID for inválido, redireciona
if ($id <= 0) {
    header("Location: " . BASE_URL . "/modules/empenho_manual/index.php?message=" . urlencode("ID do empenho inválido para edição.") . "&type=error");
    exit();
}

// --- Lógica para buscar os dados do empenho para preencher o formulário ---
$sql_select = "SELECT 
                    em.*, 
                    p.nome AS produto_nome, p.codigo AS produto_codigo, 
                    p.estoque_atual AS produto_estoque_atual, p.estoque_empenhado AS produto_estoque_empenhado,
                    op.numero_op AS ordem_producao_numero
                FROM empenho_materiais em 
                JOIN produtos p ON em.produto_id = p.id 
                JOIN ordens_producao op ON em.ordem_producao_id = op.id
                WHERE em.id = ? AND em.deleted_at IS NULL";
try {
    $result_empenho_data = $conn->execute_query($sql_select, [$id]);
    if ($result_empenho_data) {
        $empenho_data = $result_empenho_data->fetch_assoc();
        $result_empenho_data->free();
        if (!$empenho_data) {
            $message = "Empenho manual não encontrado para edição.";
            $message_type = "error";
        }
    } else {
        $message = "Erro ao carregar dados do empenho: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar dados do empenho: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro fatal ao carregar dados do empenho (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar dados do empenho: " . $e->getMessage());
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0 && $empenho_data) {
    // Sanitiza e valida as entradas
    $temp_produto_id = $empenho_data['produto_id']; // Produto e OP são read-only, usa o ID original
    $temp_op_id = $empenho_data['ordem_producao_id']; 
    $original_quantidade_empenhada = $empenho_data['quantidade_empenhada'];

    $temp_tipo_empenho = sanitizeInput(isset($_POST['tipo_empenho']) ? $_POST['tipo_empenho'] : ''); // 'empenhar' ou 'desempenhar'
    $temp_quantidade = (float) sanitizeInput(isset($_POST['quantidade']) ? $_POST['quantidade'] : 0.0);
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');
    $temp_responsavel_id = sanitizeInput(isset($_POST['responsavel_id']) ? $_POST['responsavel_id'] : '');
    $temp_data_hora_movimentacao = sanitizeInput(isset($_POST['data_hora_movimentacao']) ? $_POST['data_hora_movimentacao'] : date('Y-m-d H:i:s'));

    // Validação básica
    if (empty($temp_tipo_empenho) || empty($temp_quantidade) || $temp_quantidade <= 0 || empty($temp_responsavel_id)) {
        $message = "Tipo de Movimentação, Quantidade (maior que zero) e Responsável são campos obrigatórios.";
        $message_type = "error";
    } else {
        $conn->begin_transaction();
        try {
            // Busca o estoque atual e empenhado do produto para validação
            $sql_get_product_stock = "SELECT estoque_atual, estoque_empenhado FROM produtos WHERE id = ? AND deleted_at IS NULL";
            $result_stock = $conn->execute_query($sql_get_product_stock, [$temp_produto_id]);
            $current_stock_atual = 0;
            $current_stock_empenhado_total = 0;
            if ($result_stock && $row_stock = $result_stock->fetch_assoc()) {
                $current_stock_atual = (float)$row_stock['estoque_atual'];
                $current_stock_empenhado_total = (float)$row_stock['estoque_empenhado'];
            } else {
                throw new mysqli_sql_exception("Produto selecionado não encontrado ou inativo.");
            }
            $result_stock->free();

            $mov_tipo = '';
            $delta_empenho_produto_total = 0; // A diferença que será aplicada ao estoque_empenhado TOTAL do produto

            if ($temp_tipo_empenho === 'empenhar') {
                // Se a quantidade está sendo AUMENTADA (ou alterado de desempenho para empenho)
                $delta_empenho_individual = $temp_quantidade - $original_quantidade_empenhada;
                
                // Verifica se há estoque livre suficiente para o *aumento* do empenho
                if (($current_stock_atual - $current_stock_empenhado_total) < $delta_empenho_individual) {
                    throw new mysqli_sql_exception("Estoque livre insuficiente para o aumento do empenho em " . number_format($delta_empenho_individual, 2) . ". Disponível: " . number_format(($current_stock_atual - $current_stock_empenhado_total), 2));
                }
                $new_empenho_op_qty = $temp_quantidade;
                $delta_empenho_produto_total = $delta_empenho_individual; // Aumenta o empenho total no produto

                $mov_tipo = 'empenho'; // Movimentação será de empenho
            } elseif ($temp_tipo_empenho === 'desempenhar') {
                // Se a quantidade está sendo DIMINUÍDA (ou alterado de empenho para desempenho)
                $delta_desempenho_individual = $temp_quantidade - $original_quantidade_empenhada; // será negativo para aumento de desempenho
                
                // Se a quantidade foi reduzida, o delta é positivo para o estoque empenhado total
                // Se a quantidade foi aumentada (tipo desempenho), o delta é negativo para o estoque empenhado total
                $new_empenho_op_qty = $temp_quantidade; // Nova quantidade empenhada para esta OP
                $delta_empenho_produto_total = $delta_desempenho_individual; // Diminui o empenho total no produto

                // Valida que não está desempenhando mais do que estava empenhado para esta OP
                if ($temp_quantidade > $original_quantidade_empenhada && ($original_quantidade_empenhada - $temp_quantidade) < 0) { // Se a nova qtd desempenhada é maior que a original empenhada
                    // Isso é uma complicação: se você está "desempenhando" mais do que estava empenhado, isso é um ajuste reverso
                    // Ou seja, você está removendo MAIS do empenho do que existia.
                    // A quantidade desempenhada não pode ser maior que o empenho original da OP.
                    // Para simplificar, vou garantir que a nova quantidade desempenhada não seja maior que a originalmente empenhada se for do tipo 'desempenhar'.
                    if ($temp_quantidade > $original_quantidade_empenhada) {
                        throw new mysqli_sql_exception("Não é possível desempenhar mais do que a quantidade originalmente empenhada para este registro (" . number_format($original_quantidade_empenhada, 2) . ").");
                    }
                }
                $mov_tipo = 'desempenho'; // Movimentação será de desempenho

            } else {
                throw new mysqli_sql_exception("Tipo de movimentação de empenho inválido.");
            }

            // 1. Atualizar o registro de empenho na tabela empenho_materiais
            $sql_update_empenho_record = "UPDATE empenho_materiais SET quantidade_empenhada = ?, data_empenho = ?, observacoes = ?, deleted_at = CASE WHEN ? <= 0 THEN NOW() ELSE NULL END WHERE id = ?";
            $conn->execute_query($sql_update_empenho_record, [$new_empenho_op_qty, $temp_data_hora_movimentacao, $temp_observacoes, $new_empenho_op_qty, $id]);

            // 2. Ajustar o estoque_empenhado TOTAL na tabela produtos
            $sql_update_produto_empenhado_total = "UPDATE produtos SET estoque_empenhado = GREATEST(0, estoque_empenhado + ?) WHERE id = ?";
            $conn->execute_query($sql_update_produto_empenhado_total, [$delta_empenho_produto_total, $temp_produto_id]);

            // 3. Registrar a movimentação de estoque (empenho/desempenho)
            $sql_mov = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
            
            $responsavel_nome_log = '';
            foreach ($operadores as $op_info) {
                if ($op_info['id'] == $temp_responsavel_id) {
                    $responsavel_nome_log = $op_info['nome'] . ' (' . $op_info['matricula'] . ')';
                    break;
                }
            }
            $origem_destino_log = ucfirst($mov_tipo) . " Manual OP: " . $empenho_data['ordem_producao_numero'] . " (Resp: " . $responsavel_nome_log . ")";
            
            $conn->execute_query($sql_mov, [
                $temp_produto_id,
                $mov_tipo,
                abs($temp_quantidade), // Sempre registra quantidade positiva na movimentação
                $temp_data_hora_movimentacao,
                $origem_destino_log, 
                $temp_observacoes
            ]);

            $conn->commit();
            $message = "Empenho manual atualizado com sucesso! Estoque empenhado ajustado.";
            $message_type = "success";
            
            // Recarrega os dados do empenho após o sucesso para refletir na tela
            $result_empenho_data_reloaded = $conn->execute_query($sql_select, [$id]);
            if ($result_empenho_data_reloaded) {
                $empenho_data = $result_empenho_data_reloaded->fetch_assoc();
                $result_empenho_data_reloaded->free();
            }

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro na transação de atualização de empenho/desempenho: " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal em Empenho Manual (edição): " . $e->getMessage());
        } catch (Exception $e) { // Captura exceções de lógica
            $conn->rollback();
            $message = "Erro: " . $e->getMessage();
            $message_type = "error";
            error_log("Erro lógico em Empenho Manual (edição): " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Variáveis para exibir informações de estoque atualizadas
$current_product_stock_display = ''; 
$current_product_empenhado_display = ''; 
$current_product_livre_display = ''; 

// Busca estoque atual e empenhado do produto para exibição
if ($empenho_data && !empty($empenho_data['produto_id'])) {
    $sql_get_stock_info = "SELECT estoque_atual, estoque_empenhado FROM produtos WHERE id = ? AND deleted_at IS NULL";
    $result_stock_info = $conn->execute_query($sql_get_stock_info, [$empenho_data['produto_id']]);
    if ($result_stock_info && $row_stock_info = $result_stock_info->fetch_assoc()) {
        $current_product_stock_display = number_format($row_stock_info['estoque_atual'], 2, ',', '.');
        $current_product_empenhado_display = number_format($row_stock_info['estoque_empenhado'], 2, ',', '.');
        $current_product_livre_display = number_format($row_stock_info['estoque_atual'] - $row_stock_info['estoque_empenhado'], 2, ',', '.');
        $result_stock_info->free();
    }
}

// Valor padrão para Data/Hora da Movimentação (se o formulário não foi submetido ou falhou)
$default_data_hora_mov = date('Y-m-d\TH:i', strtotime($empenho_data['data_empenho'] ?? 'now')); 

?>

<h2>Editar Empenho Manual</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($empenho_data): ?>
    <form action="editar.php?id=<?php echo $empenho_data['id']; ?>" method="POST">
        <div class="form-group">
            <label for="produto_nome_display">Produto/Material:</label>
            <input type="text" id="produto_nome_display" value="<?php echo htmlspecialchars($empenho_data['produto_nome'] . ' (' . $empenho_data['produto_codigo'] . ')'); ?>" readonly>
            <input type="hidden" name="produto_id_hidden" value="<?php echo htmlspecialchars($empenho_data['produto_id']); ?>">
        </div>

        <div class="form-group">
            <label for="ordem_producao_numero_display">Ordem de Produção (OP):</label>
            <input type="text" id="ordem_producao_numero_display" value="<?php echo htmlspecialchars($empenho_data['ordem_producao_numero']); ?>" readonly>
            <input type="hidden" name="op_id_hidden" value="<?php echo htmlspecialchars($empenho_data['ordem_producao_id']); ?>">
        </div>

        <div class="form-group">
            <label for="quantidade_em_estoque_display">Estoque Atual:</label>
            <input type="text" id="quantidade_em_estoque_display" value="<?php echo $current_product_stock_display; ?>" readonly placeholder="Estoque atual">
        </div>
        
        <div class="form-group">
            <label for="quantidade_empenhado_display">Estoque Empenhado (Total):</label>
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
                <option value="empenhar" <?php echo (isset($post_values['tipo_empenho']) ? $post_values['tipo_empenho'] : 'empenhar') == 'empenhar' ? 'selected' : ''; ?>>Empenhar</option>
                <option value="desempenhar" <?php echo (isset($post_values['tipo_empenho']) ? $post_values['tipo_empenho'] : 'desempenhar') == 'desempenhar' ? 'selected' : ''; ?>>Desempenhar</option>
            </select>
        </div>

        <div class="form-group">
            <label for="quantidade">Quantidade:</label>
            <input type="number" id="quantidade" name="quantidade" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade'] ?? $empenho_data['quantidade_empenhada']); ?>" required min="0.01" placeholder="Ex: 10.00">
        </div>

        <div class="form-group">
            <label for="data_hora_movimentacao">Data/Hora da Movimentação:</label>
            <input type="datetime-local" id="data_hora_movimentacao" name="data_hora_movimentacao" value="<?php echo htmlspecialchars($post_values['data_hora_movimentacao'] ?? $default_data_hora_mov); ?>" required>
        </div>

        <div class="form-group">
            <label for="responsavel_id">Responsável:</label>
            <select id="responsavel_id" name="responsavel_id" required>
                <option value="">Selecione um Operador</option>
                <?php
                $current_responsavel_id = $post_values['responsavel_id'] ?? $empenho_data['responsavel_id'];
                foreach ($operadores as $operador): ?>
                    <option value="<?php echo htmlspecialchars($operador['id']); ?>" <?php echo ($current_responsavel_id == $operador['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($operador['nome'] . ' (' . $operador['matricula'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group full-width">
            <label for="observacoes">Observações (Opcional):</label>
            <textarea id="observacoes" name="observacoes" placeholder="Detalhes adicionais sobre esta movimentação..."><?php echo htmlspecialchars($post_values['observacoes'] ?? $empenho_data['observacoes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button submit">Atualizar Empenho Manual</button>
    </form>
<?php else: ?>
    <p class="error" style="text-align: center;">Empenho manual não encontrado para edição.</p>
<?php endif; ?>

<a href="index.php" class="back-link">Voltar para a Lista de Empenhos Manuais</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseUrl = '<?php echo BASE_URL; ?>'; 

        // Função para atualizar os displays de estoque ao carregar a página ou selecionar produto (apenas para exibição)
        function updateStockDisplays(productId) {
            if (!productId) {
                document.getElementById('quantidade_em_estoque_display').value = '0,00';
                document.getElementById('quantidade_empenhado_display').value = '0,00';
                document.getElementById('quantidade_livre_display').value = '0,00';
                return;
            }
            fetch(`${baseUrl}/modules/estoque/get_product_stock_info.php?id=${encodeURIComponent(productId)}`)
                .then(response => response.json())
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

        // Para a página de edição, o produto_id_hidden já vem preenchido pelo PHP
        const initialProdutoId = document.querySelector('input[name="produto_id_hidden"]').value;
        if (initialProdutoId) {
            updateStockDisplays(initialProdutoId);
        }
    });
</script>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
