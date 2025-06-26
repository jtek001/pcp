<?php
// /modules/ordens_producao/editar_apontamento.php

ob_start();
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$apontamento_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$apontamento_id) {
    header("Location: index.php"); // Redireciona se não houver ID
    exit();
}

// --- LÓGICA DE ATUALIZAÇÃO DO APONTAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op_id_retorno = filter_input(INPUT_POST, 'ordem_producao_id', FILTER_VALIDATE_INT);
    $maquina_id_nova = filter_input(INPUT_POST, 'maquina_id', FILTER_VALIDATE_INT);
    $operador_id_novo = filter_input(INPUT_POST, 'operador_id', FILTER_VALIDATE_INT);
    $quantidade_nova = (float) sanitizeInput($_POST['quantidade_produzida']);
    $data_apontamento_nova = sanitizeInput($_POST['data_apontamento']);
    $observacoes_novas = sanitizeInput($_POST['observacoes']);

    if ($maquina_id_nova && $operador_id_novo && $quantidade_nova > 0) {
        $conn->begin_transaction();
        try {
            // 1. Busca os dados originais do apontamento
            $sql_get_old = "SELECT * FROM apontamentos_producao WHERE id = ?";
            $apontamento_antigo = $conn->execute_query($sql_get_old, [$apontamento_id])->fetch_assoc();
            $quantidade_antiga = (float)$apontamento_antigo['quantidade_produzida'];
            
            // 2. Calcula a diferença na produção
            $diferenca_producao = $quantidade_nova - $quantidade_antiga;

            // 3. Ajusta o estoque do produto acabado
            $sql_get_op_produto = "SELECT produto_id FROM ordens_producao WHERE id = ?";
            $produto_op_id = $conn->execute_query($sql_get_op_produto, [$op_id_retorno])->fetch_assoc()['produto_id'];
            $sql_ajuste_estoque = "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?";
            $conn->execute_query($sql_ajuste_estoque, [$diferenca_producao, $produto_op_id]);

            // 4. Ajusta o empenho dos materiais
            $sql_bom_items = "SELECT produto_filho_id, quantidade_necessaria FROM lista_materiais WHERE produto_pai_id = ? AND deleted_at IS NULL";
            $result_bom_items = $conn->execute_query($sql_bom_items, [$produto_op_id]);
            if ($result_bom_items) {
                while ($bom_item = $result_bom_items->fetch_assoc()) {
                    $material_id = (int)$bom_item['produto_filho_id'];
                    $diferenca_material = (float)$bom_item['quantidade_necessaria'] * $diferenca_producao;
                    if ($diferenca_material != 0) {
                        $conn->execute_query("UPDATE empenho_materiais SET quantidade_empenhada = GREATEST(0, quantidade_empenhada - ?) WHERE produto_id = ? AND ordem_producao_id = ?", [$diferenca_material, $material_id, $op_id_retorno]);
                        $conn->execute_query("UPDATE produtos SET estoque_empenhado = GREATEST(0, estoque_empenhado - ?) WHERE id = ?", [$diferenca_material, $material_id]);
                    }
                }
            }

            // 5. Atualiza o apontamento em si
            $sql_update_apontamento = "UPDATE apontamentos_producao SET maquina_id = ?, operador_id = ?, quantidade_produzida = ?, data_apontamento = ?, observacoes = ? WHERE id = ?";
            $conn->execute_query($sql_update_apontamento, [$maquina_id_nova, $operador_id_novo, $quantidade_nova, $data_apontamento_nova, $observacoes_novas, $apontamento_id]);

            $conn->commit();
            $_SESSION['message'] = "Apontamento atualizado com sucesso!";
            $_SESSION['message_type'] = "success";
            header("Location: apontar.php?id=" . $op_id_retorno);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Erro ao atualizar apontamento: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
            header("Location: editar_apontamento.php?id=" . $apontamento_id);
            exit();
        }
    }
}

// --- Lógica para buscar os dados e exibir o formulrio (GET) ---
require_once __DIR__ . '/../../includes/header.php';

$sql_get = "SELECT * FROM apontamentos_producao WHERE id = ?";
$apontamento = $conn->execute_query($sql_get, [$apontamento_id])->fetch_assoc();
if (!$apontamento) {
    die("Apontamento não encontrado.");
}

$maquinas_ativas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$operadores = $conn->query("SELECT id, nome, matricula FROM operadores WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
?>

<h2>Editar Apontamento de Produção</h2>

<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo $_SESSION['message']; ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
<?php endif; ?>

<form action="editar_apontamento.php?id=<?php echo $apontamento_id; ?>" method="POST">
    <input type="hidden" name="ordem_producao_id" value="<?php echo $apontamento['ordem_producao_id']; ?>">
    
    <div class="form-group">
        <label for="maquina_id">Máquina Utilizada:</label>
        <select id="maquina_id" name="maquina_id" required>
            <?php foreach ($maquinas_ativas as $maquina): ?>
                <option value="<?php echo $maquina['id']; ?>" <?php echo ($apontamento['maquina_id'] == $maquina['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($maquina['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="operador_id">Operador:</label>
        <select id="operador_id" name="operador_id" required>
            <?php foreach ($operadores as $operador): ?>
                <option value="<?php echo $operador['id']; ?>" <?php echo ($apontamento['operador_id'] == $operador['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($operador['nome'] . ' (' . $operador['matricula'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="quantidade_produzida">Quantidade Produzida:</label>
        <input type="number" id="quantidade_produzida" name="quantidade_produzida" value="<?php echo htmlspecialchars($apontamento['quantidade_produzida']); ?>" step="0.01" required>
    </div>
    <div class="form-group">
        <label for="data_apontamento">Data Apontamento:</label>
        <input type="datetime-local" id="data_apontamento" name="data_apontamento" value="<?php echo date('Y-m-d\TH:i', strtotime($apontamento['data_apontamento'])); ?>" required>
    </div>
    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes"><?php echo htmlspecialchars($apontamento['observacoes']); ?></textarea>
    </div>
    <button type="submit" class="button submit">Salvar Alterações</button>
</form>

<a href="apontar.php?id=<?php echo $apontamento['ordem_producao_id']; ?>" class="back-link">Voltar para a OP</a>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
