<?php
// modules/ordens_producao/editar_apontamento.php
// Esta página permite editar um apontamento de produção já registrado.

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
$apontamento_data = null; // Dados do apontamento a ser editado
$op_data_context = null; // Dados da OP para contexto
$maquinas_ativas = []; // Lista de máquinas ativas
$operadores = []; // Lista de operadores

// Busca máquinas ativas para o dropdown
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

// Busca operadores para o dropdown (CORRIGIDO: agora da tabela 'operadores')
$sql_operadores = "SELECT id, nome, matricula FROM operadores WHERE deleted_at IS NULL ORDER BY nome ASC";
try {
    $result_operadores = $conn->execute_query($sql_operadores);
    if ($result_operadores) {
        while ($row = $result_operadores->fetch_assoc()) {
            $operadores[] = $row;
        }
        $result_operadores->free();
    } else {
        error_log("Erro ao buscar operadores: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao buscar operadores: " . $e->getMessage());
}


// Pega o ID do apontamento da URL
$apontamento_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Se o ID for inválido, redireciona
if ($apontamento_id <= 0) {
    header("Location: " . BASE_URL . "/modules/ordens_producao/index.php?message=" . urlencode("ID do apontamento inválido para edição.") . "&type=error");
    exit();
}

// --- Lógica para buscar os dados do apontamento e contexto da OP ---
$sql_select_apontamento = "SELECT ap.*, op.numero_op, op.quantidade_produzir AS op_quantidade_produzir, op.status AS op_status, op.data_conclusao AS op_data_conclusao, p.id AS produto_id, p.nome AS produto_nome, p.codigo AS produto_codigo, p.estoque_atual AS produto_estoque_atual FROM apontamentos_producao ap JOIN ordens_producao op ON ap.ordem_producao_id = op.id JOIN produtos p ON op.produto_id = p.id WHERE ap.id = ?";
try {
    $result_apontamento_data = $conn->execute_query($sql_select_apontamento, [$apontamento_id]);
    if ($result_apontamento_data) {
        $apontamento_data = $result_apontamento_data->fetch_assoc();
        $result_apontamento_data->free();
        if (!$apontamento_data) {
            $message = "Apontamento não encontrado para edição.";
            $message_type = "error";
        } else {
            // Contexto da OP para exibição e cálculos
            $op_data_context = [
                'id' => $apontamento_data['ordem_producao_id'],
                'numero_op' => $apontamento_data['numero_op'],
                'quantidade_produzir' => $apontamento_data['op_quantidade_produzir'],
                'status' => $apontamento_data['op_status'],
                'data_conclusao' => $apontamento_data['op_data_conclusao'],
                'produto_id' => $apontamento_data['produto_id'],
                'produto_nome' => $apontamento_data['produto_nome'],
                'produto_codigo' => $apontamento_data['produto_codigo'],
                'produto_estoque_atual' => $apontamento_data['produto_estoque_atual']
            ];
        }
    } else {
        $message = "Erro ao carregar dados do apontamento: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar dados do apontamento: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro fatal ao carregar dados do apontamento (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar dados do apontamento: " . $e->getMessage());
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $apontamento_id > 0 && $apontamento_data) {
    // Sanitiza e valida as entradas
    $temp_maquina_id = sanitizeInput(isset($_POST['maquina_id']) ? $_POST['maquina_id'] : ''); // Valor vindo do hidden input
    $temp_quantidade_produzida = (float) sanitizeInput(isset($_POST['quantidade_produzida']) ? $_POST['quantidade_produzida'] : 0.0);
    $temp_data_apontamento = isset($_POST['data_apontamento']) && $_POST['data_apontamento'] !== '' ? sanitizeInput($_POST['data_apontamento']) : date('Y-m-d H:i:s');
    $temp_operador_id = sanitizeInput(isset($_POST['operador_id']) ? $_POST['operador_id'] : '');
    $temp_observacoes_apontamento = sanitizeInput(isset($_POST['observacoes_apontamento']) ? $_POST['observacoes_apontamento'] : '');

    // Validação básica
    if (empty($temp_maquina_id) || empty($temp_quantidade_produzida) || $temp_quantidade_produzida <= 0 || empty($temp_operador_id)) {
        $message = "Máquina, Quantidade Produzida, Data do Apontamento e Operador são campos obrigatórios e a quantidade deve ser maior que zero.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // Obter a quantidade produzida ORIGINAL deste apontamento
            $original_quantidade_produzida = $apontamento_data['quantidade_produzida'];
            $diferenca_quantidade = $temp_quantidade_produzida - $original_quantidade_produzida;

            // 1. Atualizar o apontamento de produção
            $sql_update_apontamento = "UPDATE apontamentos_producao SET maquina_id = ?, operador_id = ?, quantidade_produzida = ?, data_apontamento = ?, observacoes = ? WHERE id = ?";
            $params_update_apontamento = [
                $temp_maquina_id,
                $temp_operador_id,
                $temp_quantidade_produzida,
                $temp_data_apontamento,
                $temp_observacoes_apontamento,
                $apontamento_id
            ];
            $conn->execute_query($sql_update_apontamento, $params_update_apontamento);

            // 2. Ajustar o estoque do produto com base na diferença
            $sql_adjust_estoque = "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?";
            $params_adjust_estoque = [
                $diferenca_quantidade,
                $op_data_context['produto_id']
            ];
            $conn->execute_query($sql_adjust_estoque, $params_adjust_estoque);

            // 3. Atualizar a movimentação de estoque correspondente
            // Tentaremos encontrar e atualizar uma movimentação de estoque já existente ligada a este apontamento.
            // Se não houver (ex: erro no LIKE ou registro antigo), criaremos uma nova "movimentação de ajuste" para a diferença.
            $sql_update_mov_estoque = "UPDATE movimentacoes_estoque SET quantidade = ?, data_hora_movimentacao = ?, origem_destino = ?, observacoes = ? WHERE produto_id = ? AND origem_destino LIKE ? AND tipo_movimentacao = 'entrada' LIMIT 1";
            
            // Para encontrar a movimentação específica da OP
            $origem_destino_like = "Produção OP: " . $op_data_context['numero_op'] . "%";

            $params_update_mov_estoque = [
                $temp_quantidade_produzida,
                $temp_data_apontamento,
                "Produção OP: " . $op_data_context['numero_op'] . " (Máquina ID: " . $temp_maquina_id . ", Operador ID: " . $temp_operador_id . ") [EDITADO]", 
                $temp_observacoes_apontamento,
                $op_data_context['produto_id'],
                $origem_destino_like
            ];
            
            // Execute a query e verifique affected_rows diretamente na conexão
            $conn->execute_query($sql_update_mov_estoque, $params_update_mov_estoque);
            if ($conn->affected_rows === 0) { // Correção: Acessar affected_rows da conexão
                // Se a movimentação existente não foi encontrada/atualizada (ex: erro no LIKE ou registro antigo),
                // podemos registrar uma nova "movimentação de ajuste" para a diferença.
                if (abs($diferenca_quantidade) > 0.001) { // Apenas cria ajuste se houver diferença real
                    $sql_new_adj_mov = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
                    $adj_type = ($diferenca_quantidade > 0) ? 'ajuste_entrada' : 'ajuste_saida';
                    $adj_quantity = abs($diferenca_quantidade);
                    $adj_obs = "Ajuste por edição de apontamento OP " . $op_data_context['numero_op'] . " - Original: " . $original_quantidade_produzida . ", Nova: " . $temp_quantidade_produzida . ". Obs: " . $temp_observacoes_apontamento;
                    
                    $conn->execute_query($sql_new_adj_mov, [
                        $op_data_context['produto_id'], $adj_type, $adj_quantity, date('Y-m-d H:i:s'), "Ajuste OP " . $op_data_context['numero_op'], $adj_obs
                    ]);
                }
            }

            // 4. Recalcular total apontado para a OP e atualizar status
            $sql_total_produzido = "SELECT SUM(quantidade_produzida) AS total_produzido FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL"; // Apenas apontamentos ATIVOS
            $result_total = $conn->execute_query($sql_total_produzido, [$apontamento_data['ordem_producao_id']]);
            $row_total = $result_total->fetch_assoc();
            $total_produzido_op = $row_total['total_produzido'];

            $new_op_status = $op_data_context['status'];
            $new_op_data_conclusao = $op_data_context['data_conclusao'];

            if ($total_produzido_op >= $op_data_context['quantidade_produzir']) {
                $new_op_status = 'concluida';
                if (empty($op_data_context['data_conclusao'])) {
                    $new_op_data_conclusao = date('Y-m-d H:i:s');
                }
            } else {
                if ($op_data_context['status'] === 'pendente' || $op_data_context['status'] === 'em_producao') {
                    $new_op_status = 'em_producao';
                }
                $new_op_data_conclusao = NULL;
            }

            $sql_update_op_status = "UPDATE ordens_producao SET status = ?, data_conclusao = ? WHERE id = ?";
            $conn->execute_query($sql_update_op_status, [$new_op_status, $new_op_data_conclusao, $apontamento_data['ordem_producao_id']]);

            $conn->commit();
            $message = "Apontamento atualizado com sucesso! Estoque e status da OP reajustados. Status da OP: " . $new_op_status;
            $message_type = "success";
            $_POST = array(); // Limpa o formulário de apontamento para evitar re-submissão
            
            // Adicionado: Redireciona para a própria página de edição com a mensagem de sucesso
            header("Location: " . BASE_URL . "/modules/ordens_producao/editar_apontamento.php?id=" . $apontamento_id . "&message=" . urlencode($message) . "&type=" . urlencode($message_type));
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro na transação de atualização de apontamento: " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal na transação de atualização de apontamento: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Recupera mensagens da URL (após redirecionamento de sucesso)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = sanitizeInput($_GET['message']);
    $message_type = sanitizeInput($_GET['type']);
}

// Calcula a necessidade real
// NECESSIDADE REAL: Quantidade a Produzir da OP - Estoque Atual do Produto (do op_data_context)
$necessidade_real = ($op_data_context['quantidade_produzir'] ?? 0) - ($op_data_context['produto_estoque_atual'] ?? 0);
$necessidade_real = max(0, $necessidade_real); // Garante que a necessidade não seja negativa

?>

<h2>Editar Apontamento de Produção</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($apontamento_data && $op_data_context): ?>
    <div class="op-details">
        <p><strong>OP:</strong> <?php echo htmlspecialchars($op_data_context['numero_op'] ?? 'N/A'); ?></p>
        <p><strong>Produto:</strong> <?php echo htmlspecialchars($op_data_context['produto_nome'] . ' (' . $op_data_context['produto_codigo'] . ')'); ?></p>
        <p><strong>Qtd. a Produzir da OP:</strong> <?php echo number_format($op_data_context['quantidade_produzir'], 2, ',', '.'); ?></p>
        <p><strong>Estoque Atual:</strong> <?php echo number_format($op_data_context['produto_estoque_atual'], 2, ',', '.'); ?></p>
        <p><strong>Necessidade real:</strong> <?php echo number_format($necessidade_real, 2, ',', '.'); ?></p>
        <p><strong>Status da OP:</strong> <span class="status-<?php echo htmlspecialchars($op_data_context['status']); ?>"><?php echo htmlspecialchars(ucfirst($op_data_context['status'])); ?></span></p>
        <?php if ($op_data_context['status'] === 'concluida' && !empty($op_data_context['data_conclusao'])): ?>
            <p style="color: green; font-weight: bold;">Concluída em: <?php echo date('d/m/Y H:i', strtotime($op_data_context['data_conclusao'])); ?></p>
        <?php endif; ?>
    </div>

    <form action="editar_apontamento.php?id=<?php echo $apontamento_id; ?>" method="POST">
        <div class="form-group">
            <label for="maquina_id">Máquina Utilizada:</label>
            <select id="maquina_id" name="maquina_id_display" required disabled>
                <option value="">Selecione uma Máquina</option>
                <?php
                // Pre-seleciona a máquina atual do apontamento
                $current_maquina_id = $post_values['maquina_id'] ?? $apontamento_data['maquina_id'];
                foreach ($maquinas_ativas as $maquina): ?>
                    <option value="<?php echo htmlspecialchars($maquina['id']); ?>" <?php echo ($current_maquina_id == $maquina['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($maquina['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <!-- Input oculto para enviar o valor da máquina desativada -->
            <input type="hidden" name="maquina_id" value="<?php echo htmlspecialchars($current_maquina_id); ?>">
        </div>

        <div class="form-group">
            <label for="operador_id">Operador:</label>
            <select id="operador_id" name="operador_id" required>
                <option value="">Selecione um Operador</option>
                <?php
                $current_operador_id = $post_values['operador_id'] ?? $apontamento_data['operador_id'];
                foreach ($operadores as $operador): ?>
                    <option value="<?php echo htmlspecialchars($operador['id']); ?>" <?php echo ($current_operador_id == $operador['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($operador['nome'] . ' (' . $operador['matricula'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="quantidade_produzida">Quantidade Produzida:</label>
            <input type="number" id="quantidade_produzida" name="quantidade_produzida" step="0.01" value="<?php echo htmlspecialchars($post_values['quantidade_produzida'] ?? $apontamento_data['quantidade_produzida']); ?>" required min="0.01" placeholder="Ex: 50.00">
        </div>

        <div class="form-group">
            <label for="data_apontamento">Data Apontamento:</label>
            <input type="datetime-local" id="data_apontamento" name="data_apontamento" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($post_values['data_apontamento'] ?? $apontamento_data['data_apontamento']))); ?>" required>
        </div>

        <div class="form-group full-width">
            <label for="observacoes_apontamento">Observações do Apontamento:</label>
            <textarea id="observacoes_apontamento" name="observacoes_apontamento" placeholder="Detalhes sobre este apontamento de produção..."><?php echo htmlspecialchars($post_values['observacoes_apontamento'] ?? $apontamento_data['observacoes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button submit">Atualizar Apontamento</button>
    </form>
<?php else: ?>
    <p class="error" style="text-align: center;">Apontamento não encontrado para edição.</p>
<?php endif; ?>

<a href="apontar.php?id=<?php echo htmlspecialchars($apontamento_data['ordem_producao_id'] ?? ''); ?>" class="back-link">Voltar para Apontamento da OP</a>
<a href="index.php" class="back-link">Voltar para a lista de Ordens de Produção</a>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
