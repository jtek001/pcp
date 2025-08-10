<?php
// Ativa o buffer de saída para evitar problemas de "headers already sent"
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

// Processa o formulário de consumo ou estorno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'consumir';
    $op_id_post = filter_input(INPUT_POST, 'op_id', FILTER_VALIDATE_INT);

    if ($action === 'consumir') {
        $apontamento_id = filter_input(INPUT_POST, 'apontamento_id', FILTER_VALIDATE_INT);
        $maquina_id = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT);
        $quantidade_consumida = filter_input(INPUT_POST, 'quantidade_consumida', FILTER_VALIDATE_FLOAT);
        $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
        $turno_id = filter_input(INPUT_POST, 'turno_id', FILTER_VALIDATE_INT);
        $data_consumo = sanitizeInput($_POST['data_consumo']);

        if ($op_id_post && $apontamento_id && $maquina_id && $quantidade_consumida > 0 && $operador_id && $turno_id) {
            $conn->begin_transaction();
            try {
                $sql_lote = "SELECT * FROM apontamentos_producao WHERE id = ?";
                $lote_data = $conn->execute_query($sql_lote, [$apontamento_id])->fetch_assoc();
                
                if (!$lote_data || $quantidade_consumida > $lote_data['quantidade_produzida']) {
                    throw new Exception("Quantidade consumida excede o saldo do lote.");
                }

                $produto_id_lote = $conn->execute_query("SELECT produto_id FROM ordens_producao WHERE id = ?", [$lote_data['ordem_producao_id']])->fetch_assoc()['produto_id'];

                $sql_consumo = "INSERT INTO consumo_producao (apontamento_id, ordem_producao_id, produto_material_id, quantidade_consumida, data_consumo, responsavel_id, turno_id, maquina_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $conn->execute_query($sql_consumo, [$apontamento_id, $op_id_post, $produto_id_lote, $quantidade_consumida, $data_consumo, $operador_id, $turno_id, $maquina_id]);

                // Marca o lote original como utilizado (soft delete)
                $conn->execute_query("UPDATE apontamentos_producao SET data_consumo = NOW() WHERE id = ?", [$apontamento_id]);

                // Dá baixa no estoque do produto
                $conn->execute_query("UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?", [$quantidade_consumida, $produto_id_lote]);

                // Se o consumo foi parcial, cria um novo lote de devolução com o saldo
                $diferenca = $lote_data['quantidade_produzida'] - $quantidade_consumida;
                $novo_lote_numero_devolucao = null;
                if ($diferenca > 0) {
                    // CORREÇÃO: A query de devolução foi corrigida para ter o número correto de placeholders.
                    $sql_devolucao = "INSERT INTO apontamentos_producao (ordem_producao_id, maquina_id, operador_id, turno_id, quantidade_produzida, data_apontamento, observacoes, lote_numero) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
                    $obs_devolucao = 'Devolução do lote ' . $lote_data['lote_numero'];
                    $numero_op_base = $conn->query("SELECT numero_op FROM ordens_producao WHERE id = " . $lote_data['ordem_producao_id'])->fetch_assoc()['numero_op'];
                    $novo_lote_numero_devolucao = $numero_op_base . '-DEV' . $apontamento_id;

                    $conn->execute_query($sql_devolucao, [$lote_data['ordem_producao_id'], $lote_data['maquina_id'], $operador_id, $lote_data['turno_id'], $diferenca, $obs_devolucao, $novo_lote_numero_devolucao]);
                }

                $conn->commit();
                
                if ($novo_lote_numero_devolucao) {
                    $_SESSION['message'] = "Consumo parcial registrado! O saldo foi devolvido para o novo lote: <strong>" . $novo_lote_numero_devolucao . "</strong>.";
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
            $_SESSION['message'] = "Todos os campos são obrigatórios.";
            $_SESSION['message_type'] = "warning";
        }
    } 
    // ... (lógica de estorno)

    header("Location: consumo.php?op_id=" . $op_id_post);
    exit();
}

$op_id = filter_input(INPUT_GET, 'op_id', FILTER_VALIDATE_INT);
if (!$op_id) die("ID da Ordem de Produção não fornecido.");

$op_sql = "SELECT op.numero_op, p.nome as produto_nome, p.codigo as produto_codigo, op.grupo_id FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id WHERE op.id = ?";
$op_details = $conn->execute_query($op_sql, [$op_id])->fetch_assoc();

$maquinas_do_grupo = [];
if ($op_details && $op_details['grupo_id']) {
    $sql_maquinas = "SELECT m.id, m.nome FROM maquinas m JOIN maquina_grupo_associacao mga ON m.id = mga.maquina_id WHERE mga.grupo_id = ? AND m.status = 'operacional' AND m.deleted_at IS NULL ORDER BY m.nome";
    $maquinas_do_grupo = $conn->execute_query($sql_maquinas, [$op_details['grupo_id']])->fetch_all(MYSQLI_ASSOC);
}

$operadores = $conn->query("SELECT id, nome FROM operadores WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$turnos = $conn->query("SELECT id, nome_turno FROM turnos WHERE deleted_at IS NULL ORDER BY nome_turno ASC")->fetch_all(MYSQLI_ASSOC);
$consumos_anteriores_sql = "SELECT cp.id as consumo_id, cp.data_consumo, ap.lote_numero, p.nome as material_nome, cp.quantidade_consumida, resp.nome as responsavel_nome FROM consumo_producao cp LEFT JOIN apontamentos_producao ap ON cp.apontamento_id = ap.id JOIN produtos p ON cp.produto_material_id = p.id LEFT JOIN operadores resp ON cp.responsavel_id = resp.id WHERE cp.ordem_producao_id = ? AND cp.deleted_at IS NULL AND EXISTS (SELECT 1 FROM roteiros r WHERE r.produto_id = p.id AND r.deleted_at IS NULL) ORDER BY cp.data_consumo DESC";
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

    <form action="consumo.php?op_id=<?php echo $op_id; ?>" method="POST" id="form-consumo">
        <input type="hidden" name="op_id" value="<?php echo $op_id; ?>">
        <input type="hidden" name="action" value="consumir">
        
        <div class="form-group full-width">
            <label for="lote_search">Buscar por Número do Lote:</label>
            <input type="text" id="lote_search" placeholder="Digite o número completo do lote e saia do campo">
            <input type="hidden" name="apontamento_id" id="apontamento_id" required>
        </div>

        <div id="lote-details" class="full-width" style="display: none;">
            <div class="alert alert-info">
                <p><strong>Produto:</strong> <span id="produto-nome"></span></p>
                <p><strong>Qtd. Disponível no Lote:</strong> <span id="produto-qtd"></span></p>
            </div>
        </div>

        <div class="form-group">
            <label for="maquina_id">Máquina Utilizada</label>
            <select name="maquina_id" id="maquina_id" required>
                <option value="">Selecione uma máquina</option>
                <?php foreach ($maquinas_do_grupo as $maquina): ?>
                <option value="<?php echo $maquina['id']; ?>"><?php echo htmlspecialchars($maquina['nome']); ?></option>
                <?php endforeach; ?>
            </select>
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
            <label for="turno_id">Turno</label>
            <select name="turno_id" id="turno_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($turnos as $turno): ?>
                <option value="<?php echo $turno['id']; ?>"><?php echo htmlspecialchars($turno['nome_turno']); ?></option>
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
                            <td class="text-end"><?php echo number_format($consumo['quantidade_consumida'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($consumo['responsavel_nome'] ?? 'N/A'); ?></td>
                            <td>
                                <button type="button" class="button delete small" onclick="showDeleteModal('consumo_producao', <?php echo $consumo['consumo_id']; ?>)">Estornar</button>
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

    function resetAll() {
        loteDetailsDiv.style.display = 'none';
        apontamentoIdInput.value = '';
        produtoNomeSpan.textContent = '';
        produtoQtdSpan.textContent = '';
        quantidadeConsumidaInput.value = '';
    }

    loteSearchInput.addEventListener('blur', function() {
        const loteNumero = this.value.trim();
        if (loteNumero === '') {
            resetAll();
            return;
        }

        fetch(`ajax_get_lote_details_global.php?lote=${loteNumero}`)
            .then(response => {
                if (!response.ok) return response.json().then(err => Promise.reject(err));
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
                resetAll();
                this.value = '';
                this.focus();
            });
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
ob_end_flush();
?>
