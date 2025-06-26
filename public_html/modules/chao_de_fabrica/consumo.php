<?php
// Ativa o buffer de saída para evitar problemas de "headers already sent"
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();

// Processa o formulário de consumo ou estorno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'consumir';
    $op_id_post = filter_input(INPUT_POST, 'op_id', FILTER_VALIDATE_INT);

    if ($action === 'consumir') {
        $apontamento_id = filter_input(INPUT_POST, 'apontamento_id', FILTER_VALIDATE_INT);
        $quantidade_consumida = filter_input(INPUT_POST, 'quantidade_consumida', FILTER_VALIDATE_FLOAT);
        $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
        $data_consumo = sanitizeInput($_POST['data_consumo']);

        if ($op_id_post && $apontamento_id && $quantidade_consumida > 0 && $operador_id) {
            $conn->begin_transaction();
            try {
                // 1. Busca dados do lote original para validação e para criar a devolução
                $sql_lote = "SELECT * FROM apontamentos_producao WHERE id = ?";
                $lote_data = $conn->execute_query($sql_lote, [$apontamento_id])->fetch_assoc();
                
                if (!$lote_data || $quantidade_consumida > $lote_data['quantidade_produzida']) {
                    throw new Exception("Quantidade consumida não pode ser maior que a quantidade disponível no lote.");
                }
                
                $produto_id_lote = $conn->execute_query("SELECT produto_id FROM ordens_producao WHERE id = ?", [$lote_data['ordem_producao_id']])->fetch_assoc()['produto_id'];

                // 2. Inserir na tabela de consumo
                $sql_consumo = "INSERT INTO consumo_producao (apontamento_id, ordem_producao_id, produto_material_id, quantidade_consumida, data_consumo, responsavel_id) VALUES (?, ?, ?, ?, ?, ?)";
                $conn->execute_query($sql_consumo, [$apontamento_id, $op_id_post, $produto_id_lote, $quantidade_consumida, $data_consumo, $operador_id]);

                // 3. Registrar a SAÍDA no estoque
                $sql_movimentacao = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, origem_destino, observacoes) VALUES (?, 'saida', ?, ?, ?)";
                $obs_mov = "Consumo referente ao lote: " . $lote_data['lote_numero'];
                $conn->execute_query($sql_movimentacao, [$produto_id_lote, $quantidade_consumida, 'Consumo Produção', $obs_mov]);

                // 4. Atualizar o estoque_atual do produto
                $sql_update_estoque = "UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?";
                $conn->execute_query($sql_update_estoque, [$quantidade_consumida, $produto_id_lote]);
                
                // 5. Marca o lote original como utilizado (soft delete)
                $conn->execute_query("UPDATE apontamentos_producao SET deleted_at = NOW() WHERE id = ?", [$apontamento_id]);

                // 6. Se o consumo foi parcial, cria um novo lote de devolução com o saldo
                $diferenca = $lote_data['quantidade_produzida'] - $quantidade_consumida;
                $novo_lote_numero_devolucao = null;

                if ($diferenca > 0) {
                    $sql_devolucao = "INSERT INTO apontamentos_producao (ordem_producao_id, maquina_id, operador_id, quantidade_produzida, data_apontamento, observacoes, lote_numero) VALUES (?, ?, ?, ?, NOW(), ?, ?)";
                    $obs_devolucao = 'devolucao do lote ' . $lote_data['lote_numero'];
                    
                    // Gera um novo número de lote para a devolução
                    $numero_op_base = $conn->query("SELECT numero_op FROM ordens_producao WHERE id = " . $lote_data['ordem_producao_id'])->fetch_assoc()['numero_op'];
                    $novo_lote_numero_devolucao = $numero_op_base . '-DEV' . $apontamento_id;

                    $conn->execute_query($sql_devolucao, [$lote_data['ordem_producao_id'], $lote_data['maquina_id'], $operador_id, $diferenca, $obs_devolucao, $novo_lote_numero_devolucao]);
                }

                $conn->commit();

                // OBSERVAÇÃO: Mensagem de sucesso dinâmica
                if ($novo_lote_numero_devolucao) {
                    $_SESSION['message'] = "Consumo parcial registrado! O saldo foi devolvido para o novo lote: <strong>" . $novo_lote_numero_devolucao . "</strong>. Por favor, anote na nova etiqueta.";
                } else {
                    $_SESSION['message'] = "Consumo total do lote registrado com sucesso!";
                }
                $_SESSION['message_type'] = "success";

            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "Erro ao registrar consumo: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Todos os campos são obrigatórios e a quantidade deve ser maior que zero.";
            $_SESSION['message_type'] = "warning";
        }
    } elseif ($action === 'estornar') {
        $consumo_id = filter_input(INPUT_POST, 'consumo_id', FILTER_VALIDATE_INT);
        if ($consumo_id) {
            $conn->begin_transaction();
            try {
                $sql_get_consumo = "SELECT * FROM consumo_producao WHERE id = ?";
                $consumo_data = $conn->execute_query($sql_get_consumo, [$consumo_id])->fetch_assoc();

                if (!$consumo_data) {
                    throw new Exception("Registro de consumo não encontrado.");
                }

                $original_apontamento_id = $consumo_data['apontamento_id'];

                // 1. Reativa o lote original
                $conn->execute_query("UPDATE apontamentos_producao SET deleted_at = NULL WHERE id = ?", [$original_apontamento_id]);

                // 2. Devolve a quantidade ao estoque geral do produto
                $sql_return_stock = "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?";
                $conn->execute_query($sql_return_stock, [$consumo_data['quantidade_consumida'], $consumo_data['produto_material_id']]);

                // 3. Registra a movimentação de estorno
                $sql_mov_reversal = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, origem_destino, observacoes) VALUES (?, 'entrada', ?, ?, ?)";
                $obs_mov_reversal = "Estorno do Consumo ID: " . $consumo_id;
                $conn->execute_query($sql_mov_reversal, [$consumo_data['produto_material_id'], $consumo_data['quantidade_consumida'], 'Estorno Consumo', $obs_mov_reversal]);
                
                // 4. Se um lote de devolução foi criado, ele deve ser removido
                $lote_original_numero = $conn->execute_query("SELECT lote_numero FROM apontamentos_producao WHERE id = ?", [$original_apontamento_id])->fetch_assoc()['lote_numero'];
                $obs_devolucao_esperada = 'devolucao do lote ' . $lote_original_numero;
                $conn->execute_query("DELETE FROM apontamentos_producao WHERE observacoes = ?", [$obs_devolucao_esperada]);

                // 5. Exclui o registro de consumo
                $conn->execute_query("DELETE FROM consumo_producao WHERE id = ?", [$consumo_id]);

                $conn->commit();
                $_SESSION['message'] = "Consumo estornado com sucesso! O estoque e o lote original foram restaurados.";
                $_SESSION['message_type'] = "success";

            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "Erro ao estornar consumo: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "ID de consumo inválido para estorno.";
            $_SESSION['message_type'] = "warning";
        }
    }

    header("Location: consumo.php?op_id=" . $op_id_post);
    exit();
}

// --- LÓGICA PARA EXIBIÇÃO DA PÁGINA (MÉTODO GET) ---
$op_id = filter_input(INPUT_GET, 'op_id', FILTER_VALIDATE_INT);
if (!$op_id) {
    die("ID da Ordem de Produção não fornecido.");
}

$op_sql = "SELECT op.numero_op, p.nome as produto_nome, p.codigo as produto_codigo FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id WHERE op.id = ?";
$op_details = $conn->execute_query($op_sql, [$op_id])->fetch_assoc();

$operadores = $conn->query("SELECT id, nome FROM operadores WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

// ALTERAÇÃO: Adicionado filtro por família = 'Semiacabado'
$consumos_anteriores_sql = "SELECT 
                                cp.id as consumo_id,
                                cp.data_consumo,
                                ap.lote_numero,
                                p.nome as material_nome,
                                cp.quantidade_consumida,
                                resp.nome as responsavel_nome
                            FROM consumo_producao cp
                            LEFT JOIN apontamentos_producao ap ON cp.apontamento_id = ap.id
                            JOIN produtos p ON cp.produto_material_id = p.id
                            LEFT JOIN operadores resp ON cp.responsavel_id = resp.id
                            WHERE cp.ordem_producao_id = ? 
                              AND cp.deleted_at IS NULL 
                              AND UPPER(p.familia) = 'SEMIACABADO'
                            ORDER BY cp.data_consumo DESC";
$consumos_anteriores = $conn->execute_query($consumos_anteriores_sql, [$op_id])->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <h2>Consumo de Lote de Produção</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if($op_details): ?>
    <div class="alert alert-secondary">
        <strong>OP:</strong> <?php echo htmlspecialchars($op_details['numero_op']); ?> | 
        <strong>Produto:</strong> <?php echo htmlspecialchars($op_details['produto_nome'] . ' (' . $op_details['produto_codigo'] . ')'); ?>
    </div>
    <?php endif; ?>

    <form action="consumo.php" method="POST" id="form-consumo">
        <input type="hidden" name="op_id" value="<?php echo $op_id; ?>">
        <input type="hidden" name="action" value="consumir">
        
        <div class="form-group full-width">
            <label for="lote_search">Buscar por Número do Lote:</label>
            <input type="text" id="lote_search" placeholder="Digite o número completo do lote e saia do campo">
            <input type="hidden" name="apontamento_id" id="apontamento_id" required>
        </div>

        <div id="lote-details" class="full-width" style="display: none;">
            <div class="alert alert-info">
                <h5>Detalhes do Lote Selecionado</h5>
                <p><strong>Produto:</strong> <span id="produto-nome"></span></p>
                <p><strong>Quantidade Disponível no Lote:</strong> <span id="produto-qtd"></span></p>
            </div>
        </div>

        <div class="form-group">
            <label for="operador_id">Operador Responsável</label>
            <select name="operador_id" id="operador_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($operadores as $operador): ?>
                <option value="<?php echo $operador['id']; ?>"><?php echo htmlspecialchars($operador['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="quantidade_consumida">Quantidade a Consumir</label>
            <input type="number" name="quantidade_consumida" id="quantidade_consumida" step="0.01" required>
        </div>
        <div class="form-group">
            <label for="data_consumo">Data do Consumo</label>
            <input type="datetime-local" name="data_consumo" id="data_consumo" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
        </div>
        
        <div class="full-width" style="text-align: center; grid-column: 1 / -1;">
             <button type="submit" class="button submit" style="width: auto; min-width: 200px; justify-self: center;">Registrar Consumo</button>
        </div>
    </form>

    <div class="card mt-4">
        <div class="card-header">
            <h3>Semiacabados Consumidos Nesta OP</h3>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>Data Consumo</th>
                        <th>Lote de Origem</th>
                        <th>Material</th>
                        <th>Qtd. Consumida</th>
                        <th>Responsável</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($consumos_anteriores)): ?>
                        <?php foreach ($consumos_anteriores as $consumo): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($consumo['data_consumo'])); ?></td>
                            <td><?php echo htmlspecialchars($consumo['lote_numero']); ?></td>
                            <td><?php echo htmlspecialchars($consumo['material_nome']); ?></td>
                            <td><?php echo number_format($consumo['quantidade_consumida'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($consumo['responsavel_nome'] ?? 'N/A'); ?></td>
                            <td>
                                <button type="button" class="button delete small" onclick="estornarConsumo(<?php echo $consumo['consumo_id']; ?>, <?php echo $op_id; ?>)">Estornar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Nenhum consumo de semiacabado registrado para esta OP.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <a href="index.php" class="back-link">Voltar para a lista de Ordens de Produção</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loteSearchInput = document.getElementById('lote_search');
    
    const loteDetailsDiv = document.getElementById('lote-details');
    const apontamentoIdInput = document.getElementById('apontamento_id');
    const produtoNomeSpan = document.getElementById('produto-nome');
    const produtoQtdSpan = document.getElementById('produto-qtd');
    const quantidadeConsumidaInput = document.getElementById('quantidade_consumida');

    function resetLoteDetails() {
        loteDetailsDiv.style.display = 'none';
        apontamentoIdInput.value = '';
        produtoNomeSpan.textContent = '';
        produtoQtdSpan.textContent = '';
        quantidadeConsumidaInput.value = '';
        quantidadeConsumidaInput.max = '';
    }

    loteSearchInput.addEventListener('blur', function() {
        const loteNumero = this.value.trim();

        if (loteNumero === '') {
            resetLoteDetails();
            return;
        }

        fetch(`ajax_get_lote_details_global.php?lote=${loteNumero}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => Promise.reject(err));
                }
                return response.json();
            })
            .then(lote => {
                apontamentoIdInput.value = lote.id;
                produtoNomeSpan.textContent = lote.produto_nome;
                produtoQtdSpan.textContent = lote.quantidade_produzida;
                quantidadeConsumidaInput.value = lote.quantidade_produzida;
                quantidadeConsumidaInput.max = lote.quantidade_produzida;
                loteDetailsDiv.style.display = 'block';
            })
            .catch(error => {
                alert(error.error || 'Ocorreu um erro ao buscar o lote.');
                resetLoteDetails();
                loteSearchInput.value = '';
                loteSearchInput.focus();
            });
    });
});

function estornarConsumo(consumoId, opId) {
    if (confirm('Tem certeza que deseja estornar este consumo?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'consumo.php';
        form.style.display = 'none';

        const opIdInput = document.createElement('input');
        opIdInput.type = 'hidden';
        opIdInput.name = 'op_id';
        opIdInput.value = opId;
        form.appendChild(opIdInput);

        const consumoIdInput = document.createElement('input');
        consumoIdInput.type = 'hidden';
        consumoIdInput.name = 'consumo_id';
        consumoIdInput.value = consumoId;
        form.appendChild(consumoIdInput);

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'estornar';
        form.appendChild(actionInput);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
ob_end_flush();
?>
