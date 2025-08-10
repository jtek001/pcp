<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

// Processa o formulário de consumo de insumo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op_id_post = filter_input(INPUT_POST, 'op_id', FILTER_VALIDATE_INT);
    $insumo_id = filter_input(INPUT_POST, 'insumo_id', FILTER_VALIDATE_INT);
    $quantidade_consumida = filter_input(INPUT_POST, 'quantidade_consumida', FILTER_VALIDATE_FLOAT);
    $operador_id = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
    $turno_id = filter_input(INPUT_POST, 'turno_id', FILTER_VALIDATE_INT);
    $data_consumo = sanitizeInput($_POST['data_consumo']);

    if ($op_id_post && $insumo_id && $quantidade_consumida > 0 && $operador_id && $turno_id) {
        $conn->begin_transaction();
        try {
            // Inserir na tabela de consumo
            $sql_consumo = "INSERT INTO consumo_producao (ordem_producao_id, produto_material_id, quantidade_consumida, data_consumo, responsavel_id, turno_id, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $obs_consumo = "Consumo de insumo direto para a OP.";
            $conn->execute_query($sql_consumo, [$op_id_post, $insumo_id, $quantidade_consumida, $data_consumo, $operador_id, $turno_id, $obs_consumo]);

            // Registrar a SAÍDA no estoque
            $sql_movimentacao = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, origem_destino, observacoes) VALUES (?, 'saida', ?, ?, ?)";
            $obs_mov = "Consumo de Insumo para OP";
            $conn->execute_query($sql_movimentacao, [$insumo_id, $quantidade_consumida, 'Produção', $obs_mov]);

            // Atualizar o estoque_atual do insumo
            $sql_update_estoque = "UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?";
            $conn->execute_query($sql_update_estoque, [$quantidade_consumida, $insumo_id]);

            $conn->commit();
            $_SESSION['message'] = "Consumo de insumo registrado com sucesso!";
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
    
    header("Location: insumos.php?id=" . $op_id_post);
    exit();
}

$op_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$op_id) die("ID da Ordem de Produção não fornecido.");

$op_sql = "SELECT op.numero_op, p.nome as produto_nome, p.codigo as produto_codigo FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id WHERE op.id = ?";
$op_details = $conn->execute_query($op_sql, [$op_id])->fetch_assoc();
$operadores = $conn->query("SELECT id, nome FROM operadores WHERE ativo = 1 and deleted_at IS NULL ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$turnos = $conn->query("SELECT id, nome_turno FROM turnos WHERE deleted_at IS NULL ORDER BY nome_turno ASC")->fetch_all(MYSQLI_ASSOC);
$consumos_insumos = $conn->execute_query("SELECT cp.id as consumo_id, cp.data_consumo, p.nome as material_nome, cp.quantidade_consumida, resp.nome as responsavel_nome FROM consumo_producao cp JOIN produtos p ON cp.produto_material_id = p.id LEFT JOIN operadores resp ON cp.responsavel_id = resp.id WHERE cp.ordem_producao_id = ? AND cp.deleted_at IS NULL AND cp.apontamento_id IS NULL ORDER BY cp.data_consumo DESC", [$op_id])->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <h2>Consumo de Insumos</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if($op_details): ?>
    <div class="alert alert-secondary">
        <strong>OP:</strong> <?php echo htmlspecialchars($op_details['numero_op']); ?> | 
        <strong>Produto Final:</strong> <?php echo htmlspecialchars($op_details['produto_nome'] . ' (' . $op_details['produto_codigo'] . ')'); ?>
    </div>
    <?php endif; ?>

    <form action="insumos.php?id=<?php echo $op_id; ?>" method="POST" id="form-insumos">
        <input type="hidden" name="op_id" value="<?php echo $op_id; ?>">
        
        <div class="form-group full-width">
            <label for="insumo_search">Buscar Matéria-Prima / Componente (Código):</label>
            <input type="text" id="insumo_search" placeholder="Digite o código do insumo e saia do campo">
            <input type="hidden" name="insumo_id" id="insumo_id" required>
        </div>

        <div id="insumo-details" class="full-width" style="display: none;">
            <div class="alert alert-info">
                <p><strong>Insumo Selecionado:</strong> <span id="insumo-nome"></span></p>
                <p><strong>Estoque Atual:</strong> <span id="insumo-estoque"></span></p>
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
             <button type="submit" class="button submit" style="width: auto; min-width: 200px; justify-self: center;">Registrar Consumo de Insumo</button>
        </div>
    </form>

    <div class="card mt-4">
        <div class="card-header">
            <h3>Insumos Consumidos Nesta OP</h3>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>Data Consumo</th>
                        <th>Insumo</th>
                        <th>Qtd. Consumida</th>
                        <th>Responsável</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($consumos_insumos)): ?>
                        <?php foreach ($consumos_insumos as $consumo): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($consumo['data_consumo'])); ?></td>
                            <td><?php echo htmlspecialchars($consumo['material_nome']); ?></td>
                            <td class="text-end"><?php echo number_format($consumo['quantidade_consumida'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($consumo['responsavel_nome'] ?? 'N/A'); ?></td>
                            <td>
                                <button type="button" class="button delete small" onclick="showDeleteModal('consumo_insumo', <?php echo $consumo['consumo_id']; ?>)">Estornar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Nenhum insumo consumido diretamente para esta OP ainda.</td>
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
    const insumoSearchInput = document.getElementById('insumo_search');
    const insumoDetailsDiv = document.getElementById('insumo-details');
    const insumoIdInput = document.getElementById('insumo_id');
    const insumoNomeSpan = document.getElementById('insumo-nome');
    const insumoEstoqueSpan = document.getElementById('insumo-estoque');
    const quantidadeConsumidaInput = document.getElementById('quantidade_consumida');

    function resetInsumoDetails() {
        insumoDetailsDiv.style.display = 'none';
        insumoIdInput.value = '';
        insumoNomeSpan.textContent = '';
        insumoEstoqueSpan.textContent = '';
        quantidadeConsumidaInput.value = '';
        quantidadeConsumidaInput.max = '';
    }

    insumoSearchInput.addEventListener('blur', function() {
        const searchTerm = this.value.trim();
        if (searchTerm === '') {
            resetInsumoDetails();
            return;
        }

        fetch(`ajax_get_insumo_by_term.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => {
                if (!response.ok) return response.json().then(err => Promise.reject(err));
                return response.json();
            })
            .then(insumo => {
                insumoIdInput.value = insumo.id;
                insumoNomeSpan.textContent = `${insumo.nome} (${insumo.codigo})`;
                insumoEstoqueSpan.textContent = insumo.estoque_atual;
                quantidadeConsumidaInput.max = insumo.estoque_atual;
                insumoDetailsDiv.style.display = 'block';
            })
            .catch(error => {
                alert(error.error || 'Erro ao buscar insumo.');
                resetInsumoDetails();
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
