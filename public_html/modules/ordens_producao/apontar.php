<?php
// modules/ordens_producao/apontar.php
ob_start();
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();

// --- LÓGICA DE EXCLUSÃO DE APONTAMENTO (PADRONIZADA) ---
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $apontamento_id_para_excluir = (int)$_GET['delete_id'];
    $op_id_retorno = (int)$_GET['id'];

    $conn->begin_transaction();
    try {
        $sql_get_apontamento = "SELECT * FROM apontamentos_producao WHERE id = ?";
        $apontamento_data = $conn->execute_query($sql_get_apontamento, [$apontamento_id_para_excluir])->fetch_assoc();

        if ($apontamento_data) {
            $quantidade_a_estornar = (float)$apontamento_data['quantidade_produzida'];
            $op_id_do_apontamento = (int)$apontamento_data['ordem_producao_id'];
            
            $sql_get_op_produto = "SELECT produto_id FROM ordens_producao WHERE id = ?";
            $produto_op_id = $conn->execute_query($sql_get_op_produto, [$op_id_do_apontamento])->fetch_assoc()['produto_id'];

            $sql_reverte_estoque_acabado = "UPDATE produtos SET estoque_atual = GREATEST(0, estoque_atual - ?) WHERE id = ?";
            $conn->execute_query($sql_reverte_estoque_acabado, [$quantidade_a_estornar, $produto_op_id]);

            $sql_bom_items = "SELECT produto_filho_id, quantidade_necessaria FROM lista_materiais WHERE produto_pai_id = ? AND deleted_at IS NULL";
            $result_bom_items = $conn->execute_query($sql_bom_items, [$produto_op_id]);
            if ($result_bom_items) {
                while ($bom_item = $result_bom_items->fetch_assoc()) {
                    $material_id = (int)$bom_item['produto_filho_id'];
                    $quantidade_material_a_reverter = (float)$bom_item['quantidade_necessaria'] * $quantidade_a_estornar;

                    if ($quantidade_material_a_reverter > 0) {
                        $conn->execute_query("UPDATE empenho_materiais SET quantidade_empenhada = quantidade_empenhada + ? WHERE produto_id = ? AND ordem_producao_id = ? AND deleted_at IS NULL", [$quantidade_material_a_reverter, $material_id, $op_id_do_apontamento]);
                        $conn->execute_query("UPDATE produtos SET estoque_empenhado = estoque_empenhado + ? WHERE id = ?", [$quantidade_material_a_reverter, $material_id]);
                    }
                }
            }

            $conn->execute_query("DELETE FROM apontamentos_producao WHERE id = ?", [$apontamento_id_para_excluir]);
            
            $numero_op_a_buscar = $conn->query("SELECT numero_op FROM ordens_producao WHERE id = " . $op_id_do_apontamento)->fetch_assoc()['numero_op'];
            $obs_movimentacao = "Entrada por apontamento. Lote: " . $numero_op_a_buscar . '-' . $apontamento_id_para_excluir;
            $conn->execute_query("DELETE FROM movimentacoes_estoque WHERE observacoes = ?", [$obs_movimentacao]);

            // OBSERVAÇÃO: Verifica o status da OP e reabre se estiver 'concluida'
            $sql_get_op_status = "SELECT status FROM ordens_producao WHERE id = ?";
            $op_status_result = $conn->execute_query($sql_get_op_status, [$op_id_do_apontamento])->fetch_assoc();
            if ($op_status_result && $op_status_result['status'] === 'concluida') {
                $sql_reopen_op = "UPDATE ordens_producao SET status = 'em_producao', data_conclusao = NULL WHERE id = ?";
                $conn->execute_query($sql_reopen_op, [$op_id_do_apontamento]);
            }

            $conn->commit();
            $_SESSION['message'] = "Apontamento excluído e estoques revertidos com sucesso.";
            $_SESSION['message_type'] = "success";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Erro ao excluir apontamento: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    header("Location: apontar.php?id=" . $op_id_retorno);
    exit();
}


// Processa a criação de um novo apontamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $temp_maquina_id = sanitizeInput($_POST['maquina_id'] ?? '');
    $temp_quantidade_produzida = (float) sanitizeInput($_POST['quantidade_produzida'] ?? 0.0);
    $temp_data_apontamento = isset($_POST['data_apontamento']) && $_POST['data_apontamento'] !== '' ? sanitizeInput($_POST['data_apontamento']) : date('Y-m-d H:i:s');
    $temp_operador_id = sanitizeInput($_POST['operador_id'] ?? '');
    $temp_observacoes_apontamento = sanitizeInput($_POST['observacoes_apontamento'] ?? '');

    if (empty($temp_maquina_id) || empty($temp_quantidade_produzida) || $temp_quantidade_produzida <= 0 || empty($temp_operador_id)) {
        $_SESSION['message'] = "Máquina, Quantidade Produzida, Data do Apontamento e Operador são obrigatórios.";
        $_SESSION['message_type'] = "error";
        header("Location: apontar.php?id=" . $op_id);
        exit();
    }
    
    $conn->begin_transaction();
    try {
        $op_data = $conn->execute_query("SELECT * FROM ordens_producao WHERE id = ?", [$op_id])->fetch_assoc();
        
        $sql_apontamento = "INSERT INTO apontamentos_producao (ordem_producao_id, maquina_id, operador_id, quantidade_produzida, data_apontamento, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
        $conn->execute_query($sql_apontamento, [$op_id, $temp_maquina_id, $temp_operador_id, $temp_quantidade_produzida, $temp_data_apontamento, $temp_observacoes_apontamento]);
        $new_apontamento_id = $conn->insert_id;

        $lote_numero_gerado = $op_data['numero_op'] . '-' . $new_apontamento_id;
        $conn->execute_query("UPDATE apontamentos_producao SET lote_numero = ? WHERE id = ?", [$lote_numero_gerado, $new_apontamento_id]);

        $conn->execute_query("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?", [$temp_quantidade_produzida, $op_data['produto_id']]);
        
        $params_mov_acabado = [$op_data['produto_id'], 'entrada', $temp_quantidade_produzida, $temp_data_apontamento, "Produção OP: " . $op_data['numero_op'], "Entrada por apontamento. Lote: " . $lote_numero_gerado];
        $conn->execute_query("INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)", $params_mov_acabado);

        $result_bom_items = $conn->execute_query("SELECT produto_filho_id, quantidade_necessaria FROM lista_materiais WHERE produto_pai_id = ? AND deleted_at IS NULL", [$op_data['produto_id']]);
        if ($result_bom_items) {
            while ($bom_item = $result_bom_items->fetch_assoc()) {
                $material_id = (int)$bom_item['produto_filho_id'];
                $quantidade_consumida = (float)$bom_item['quantidade_necessaria'] * $temp_quantidade_produzida;
                if ($quantidade_consumida > 0) {
                    $conn->execute_query("UPDATE produtos SET estoque_empenhado = GREATEST(0, estoque_empenhado - ?) WHERE id = ?", [$quantidade_consumida, $material_id]);
                    $conn->execute_query("UPDATE empenho_materiais SET quantidade_empenhada = GREATEST(0, quantidade_empenhada - ?) WHERE produto_id = ? AND ordem_producao_id = ? AND deleted_at IS NULL", [$quantidade_consumida, $material_id, $op_id]);
                }
            }
        }

        $total_produzido_op = (float)($conn->execute_query("SELECT SUM(COALESCE(quantidade_produzida, 0)) AS total FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL", [$op_id])->fetch_assoc()['total'] ?? 0.00);
        $new_status = $op_data['status'];
        if ($total_produzido_op >= $op_data['quantidade_produzir']) {
            $new_status = 'concluida';
            $conn->execute_query("UPDATE ordens_producao SET status = ?, data_conclusao = NOW() WHERE id = ?", [$new_status, $op_id]);
        } elseif ($op_data['status'] === 'pendente') {
            $new_status = 'em_producao';
            $conn->execute_query("UPDATE ordens_producao SET status = ? WHERE id = ?", [$new_status, $op_id]);
        }
        
        $conn->commit();
        
        $_SESSION['message'] = "Apontamento registrado com sucesso! Status da OP: " . $new_status;
        $_SESSION['message_type'] = "success";
        header("Location: " . BASE_URL . "/modules/ordens_producao/gerar_etiqueta.php?id=" . $new_apontamento_id . "&op_id=" . $op_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Erro na transação de apontamento: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: apontar.php?id=" . $op_id);
        exit();
    }
}


require_once __DIR__ . '/../../includes/header.php';

$op_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($op_id <= 0) die("ID da OP inválido.");

$op_data = $conn->execute_query("SELECT op.*, p.nome AS produto_nome, p.codigo AS produto_codigo, p.estoque_atual AS produto_estoque_atual FROM ordens_producao op JOIN produtos p ON op.produto_id = p.id WHERE op.id = ?", [$op_id])->fetch_assoc();
if (!$op_data) die("Ordem de Produção não encontrada.");

$maquinas_ativas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL AND status = 'operacional' ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$operadores = $conn->query("SELECT id, nome, matricula FROM operadores WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$apontamentos_anteriores = $conn->execute_query("SELECT ap.*, m.nome AS maquina_nome, ol.nome AS operador_nome, ol.matricula AS operador_matricula FROM apontamentos_producao ap JOIN maquinas m ON ap.maquina_id = m.id LEFT JOIN operadores ol ON ap.operador_id = ol.id WHERE ap.ordem_producao_id = ? AND ap.deleted_at IS NULL ORDER BY ap.data_apontamento DESC", [$op_id])->fetch_all(MYSQLI_ASSOC);

$quantidade_total_apontada = (float)($conn->execute_query("SELECT SUM(COALESCE(quantidade_produzida, 0.00)) AS total FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL", [$op_id])->fetch_assoc()['total'] ?? 0.00);
$necessidade_real = max(0, (float)$op_data['quantidade_produzir'] - $quantidade_total_apontada);
?>

<h2>Apontamento de Produção para OP: <?php echo htmlspecialchars($op_data['numero_op']); ?></h2>

<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
<?php endif; ?>

<div class="op-details">
    <p><strong>Produto:</strong> <?php echo htmlspecialchars($op_data['produto_nome'] . ' (' . $op_data['produto_codigo'] . ')'); ?></p>
    <p><strong>Qtd. a Produzir:</strong> <?php echo number_format($op_data['quantidade_produzir'], 2, ',', '.'); ?></p>
    <p><strong>Já Produzido:</strong> <?php echo number_format($quantidade_total_apontada, 2, ',', '.'); ?></p>
    <p><strong>Necessidade Real:</strong> <?php echo number_format($necessidade_real, 2, ',', '.'); ?></p>
    <p><strong>Status da OP:</strong> <span class="status-<?php echo htmlspecialchars($op_data['status']); ?>"><?php echo htmlspecialchars(ucfirst($op_data['status'])); ?></span></p>
</div>

<?php if ($op_data['status'] !== 'concluida' && $op_data['status'] !== 'cancelada'): ?>
<form action="apontar.php?id=<?php echo $op_data['id']; ?>" method="POST">
    <div class="form-group">
        <label for="maquina_id">Máquina Utilizada:</label>
        <select id="maquina_id" name="maquina_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($maquinas_ativas as $maquina): ?>
                <option value="<?php echo $maquina['id']; ?>" <?php echo ($op_data['maquina_id'] == $maquina['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($maquina['nome']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="operador_id">Operador:</label>
        <select id="operador_id" name="operador_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($operadores as $operador): ?>
                <option value="<?php echo $operador['id']; ?>"><?php echo htmlspecialchars($operador['nome'] . ' (' . $operador['matricula'] . ')'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="quantidade_produzida">Quantidade Produzida Agora:</label>
        <input type="number" id="quantidade_produzida" name="quantidade_produzida" step="0.01" required min="0.01" max="<?php echo $necessidade_real; ?>" placeholder="Ex: 50.00">
    </div>
    <div class="form-group">
        <label for="data_apontamento">Data Apontamento:</label>
        <input type="datetime-local" id="data_apontamento" name="data_apontamento" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
    </div>
    <div class="form-group full-width">
        <label for="observacoes_apontamento">Observações:</label>
        <textarea id="observacoes_apontamento" name="observacoes_apontamento" placeholder="Detalhes..."></textarea>
    </div>
    <button type="submit" class="button submit">Registrar Apontamento</button>
</form>
<?php else: ?>
    <p class="success" style="text-align: center;">Esta Ordem de Produção já foi concluída ou cancelada.</p>
<?php endif; ?>

<h3 style="margin-top: 40px;">Apontamentos Anteriores desta OP</h3>
<?php if (!empty($apontamentos_anteriores)): ?>
    <table>
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Máquina</th>
                <th>Operador</th>
                <th>Qtd. Apontada</th>
                <th>Lote</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apontamentos_anteriores as $apontamento): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($apontamento['data_apontamento'])); ?></td>
                    <td><?php echo htmlspecialchars($apontamento['maquina_nome'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($apontamento['operador_nome'] ?? 'N/A') . ' (' . htmlspecialchars($apontamento['operador_matricula'] ?? 'N/A') . ')'; ?></td>
                    <td><?php echo number_format($apontamento['quantidade_produzida'], 2, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars($apontamento['lote_numero'] ?? 'N/A'); ?></td>
                    <td>
                        <a href="editar_apontamento.php?id=<?php echo $apontamento['id']; ?>" class="button edit small">Editar</a>
                        <a href="apontar.php?id=<?php echo $op_id; ?>&delete_id=<?php echo $apontamento['id']; ?>" class="button delete small" onclick="return confirm('Tem certeza que deseja excluir este apontamento? A ação irá reverter o estoque e os empenhos.');">Excluir</a>
                        <a href="<?php echo BASE_URL; ?>/modules/ordens_producao/gerar_etiqueta.php?id=<?php echo $apontamento['id']; ?>" target="_blank" class="button small">Imprimir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="text-align: center;">Nenhum apontamento registrado para esta Ordem de Produção.</p>
<?php endif; ?>
    
<a href="index.php" class="back-link">Voltar para a lista de Ordens de Produção</a>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
