<?php
// modules/ordens_producao/adicionar.php

ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = connectDB();

/**
 * Gera um número de OP único.
 */
function generateUniqueOpNumber($conn) {
    do {
        $generated_op = date('ym') . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $count = $conn->execute_query("SELECT COUNT(*) FROM ordens_producao WHERE numero_op = ?", [$generated_op])->fetch_row()[0];
    } while ($count > 0);
    return $generated_op;
}

/**
 * Função recursiva para MRP.
 */
function gerarOpsFilhasEReservas($conn, $op_pai_id, $produto_pai_id, $quantidade_a_produzir, $numero_pedido_pai, $data_conclusao_pai) {
    $ops_criadas = 0;
    $sql_bom_items = "SELECT p.id as material_id, p.estoque_atual, lm.quantidade_necessaria 
                      FROM lista_materiais lm 
                      JOIN produtos p ON lm.produto_filho_id = p.id 
                      WHERE lm.produto_pai_id = ? AND lm.deleted_at IS NULL";
    
    $result_bom_items = $conn->execute_query($sql_bom_items, [$produto_pai_id]);

    if ($result_bom_items) {
        while ($bom_item = $result_bom_items->fetch_assoc()) {
            $material_id = $bom_item['material_id'];
            $necessidade_bruta = (float)$bom_item['quantidade_necessaria'] * $quantidade_a_produzir;

            if ($necessidade_bruta > 0) {
                // 1. Empenha o material para a OP pai.
                $numero_op_pai = $conn->execute_query("SELECT numero_op FROM ordens_producao WHERE id = ?", [$op_pai_id])->fetch_assoc()['numero_op'];
                $params_empenho = [$material_id, $op_pai_id, $necessidade_bruta, $necessidade_bruta, "Empenho para OP " . $numero_op_pai];
                $conn->execute_query("INSERT INTO empenho_materiais (produto_id, ordem_producao_id, quantidade_empenhada, quantidade_inicial, observacoes) VALUES (?, ?, ?, ?, ?)", $params_empenho);
                $conn->execute_query("UPDATE produtos SET estoque_empenhado = estoque_empenhado + ? WHERE id = ?", [$necessidade_bruta, $material_id]);

                // 2. Verifica se o material é um semiacabado (tem roteiro) para gerar OP filha.
                $roteiro_check = $conn->execute_query("SELECT id FROM roteiros WHERE produto_id = ? AND ativo = 1 AND deleted_at IS NULL", [$material_id])->fetch_assoc();
                if ($roteiro_check) {
                    $estoque_disponivel = (float)$bom_item['estoque_atual'];
                    $necessidade_liquida = $necessidade_bruta - $estoque_disponivel;

                    if ($necessidade_liquida > 0) {
                        // LÓGICA PARA HERDAR DADOS DA OP MÃE
                        $maquina_filha_id = null;
                        $sql_roteiro_etapa = "SELECT grupo_id FROM roteiro_etapas WHERE roteiro_id = ? ORDER BY sequencia ASC LIMIT 1";
                        $result_etapa = $conn->execute_query($sql_roteiro_etapa, [$roteiro_check['id']]);
                        if ($etapa = $result_etapa->fetch_assoc()) {
                            // Este ID é de um grupo, não de uma máquina. A OP filha pode ser associada ao grupo
                            $maquina_filha_id = $etapa['grupo_id'];
                        }

                        $data_conclusao_filha = $data_conclusao_pai ? date('Y-m-d', strtotime($data_conclusao_pai . ' -2 days')) : null;
                        $numero_op_filha = generateUniqueOpNumber($conn);
                        $data_emissao_filha = date('Y-m-d');
                        
                        $params_op_filha = [$op_pai_id, $numero_op_filha, $numero_pedido_pai, $material_id, $maquina_filha_id, $necessidade_liquida, $data_emissao_filha, $data_conclusao_filha];
                        $conn->execute_query("INSERT INTO ordens_producao (op_mae_id, numero_op, numero_pedido, produto_id, maquina_id, quantidade_produzir, data_emissao, data_prevista_conclusao, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')", $params_op_filha);
                        $op_filha_id = $conn->insert_id;
                        $ops_criadas++;
                        
                        // 3. RECURSÃO: Chama a si mesma para a nova OP filha.
                        $ops_criadas += gerarOpsFilhasEReservas($conn, $op_filha_id, $material_id, $necessidade_liquida, $numero_pedido_pai, $data_conclusao_filha);
                    }
                }
            }
        }
        $result_bom_items->free();
    }
    return $ops_criadas;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $temp_numero_op = sanitizeInput($_POST['numero_op'] ?? '');
    $temp_numero_pedido = sanitizeInput($_POST['numero_pedido'] ?? '');
    $temp_produto_id = filter_input(INPUT_POST, 'produto_id_hidden', FILTER_VALIDATE_INT);
    $temp_grupo_id = filter_input(INPUT_POST, 'grupo_id', FILTER_VALIDATE_INT) ?: null;
    $temp_quantidade_produzir = (float) sanitizeInput($_POST['quantidade_produzir'] ?? 0.0);
    $temp_data_emissao = sanitizeInput($_POST['data_emissao'] ?? '');
    $temp_data_prevista_conclusao = !empty($_POST['data_prevista_conclusao']) ? sanitizeInput($_POST['data_prevista_conclusao']) : null;
    $temp_observacoes = sanitizeInput($_POST['observacoes'] ?? '');

    // OBSERVAÇÃO: Validação agora inclui o número do pedido. A opção "avulso" foi removida.
    if (empty($temp_numero_op) || empty($temp_produto_id) || empty($temp_quantidade_produzir) || empty($temp_data_emissao) || empty($temp_numero_pedido)) {
        $_SESSION['message'] = "Todos os campos obrigatórios devem ser preenchidos, incluindo a seleção de um Pedido de Venda aprovado.";
        $_SESSION['message_type'] = "error";
        header("Location: adicionar.php");
        exit();
    }
    
    $conn->begin_transaction();
    try {
        if (!empty($temp_numero_pedido)) {
            $conn->execute_query("INSERT IGNORE INTO pedidos_venda_lookup (numero_pedido) VALUES (?)", [$temp_numero_pedido]);
        }
        
        $params_insert_op = [$temp_numero_op, $temp_numero_pedido, $temp_produto_id, $temp_grupo_id, $temp_quantidade_produzir, $temp_data_emissao, $temp_data_prevista_conclusao, $temp_observacoes];
        $conn->execute_query("INSERT INTO ordens_producao (numero_op, numero_pedido, produto_id, grupo_id, quantidade_produzir, data_emissao, data_prevista_conclusao, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $params_insert_op);
        $op_mae_id = $conn->insert_id;
        
        $ops_filhas_criadas = gerarOpsFilhasEReservas($conn, $op_mae_id, $temp_produto_id, $temp_quantidade_produzir, $temp_numero_pedido, $temp_data_prevista_conclusao);

        $conn->commit();
        
        $message = "Ordem de Produção criada com sucesso!";
        if ($ops_filhas_criadas > 0) {
            $message .= " $ops_filhas_criadas OP(s) filha(s) foram geradas automaticamente.";
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = "success";
        header("Location: index.php");
        exit();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ($conn->errno == 1062) ? "Erro: O número da OP já existe." : "Erro ao criar Ordem de Produção: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: adicionar.php");
        exit();
    }
}

require_once __DIR__ . '/../../includes/header.php';

$sql_pedidos = "SELECT pv.numero_pedido, fc.nome as cliente_nome 
                FROM pedidos_venda pv 
                JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id 
                WHERE pv.status = 'Aprovado' AND pv.deleted_at IS NULL 
                ORDER BY pv.numero_pedido DESC";
$pedidos_venda = $conn->query($sql_pedidos)->fetch_all(MYSQLI_ASSOC);

$random_numero_op = generateUniqueOpNumber($conn);
$default_data_prevista = date('Y-m-d', strtotime('+7 days'));
?>

<h2>Criar Nova Ordem de Produção</h2>

<?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>"><?php echo htmlspecialchars($_SESSION['message']); ?></div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
<?php endif; ?>

<form action="adicionar.php" method="POST">
    <div class="form-group">
        <label for="numero_op">Número da OP:</label>
        <input type="text" id="numero_op" name="numero_op" value="<?php echo htmlspecialchars($_POST['numero_op'] ?? $random_numero_op); ?>" readonly>
    </div>

    <div class="form-group">
        <label for="numero_pedido">Número do Pedido (Aprovados)*:</label>
        <select id="numero_pedido" name="numero_pedido" required>
            <option value="">Selecione um pedido...</option>
            <?php foreach ($pedidos_venda as $pedido): ?>
                <option value="<?php echo htmlspecialchars($pedido['numero_pedido']); ?>">
                    <?php echo htmlspecialchars('Pedido: ' . $pedido['numero_pedido'] . ' - Cliente: ' . $pedido['cliente_nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="produto_codigo_search">Código do Produto:</label>
        <input type="text" id="produto_codigo_search" placeholder="Digite o código e saia do campo" required>
        <input type="hidden" id="produto_id_hidden" name="produto_id_hidden" required>
    </div>

    <div class="form-group">
        <label for="produto_nome_display">Nome do Produto:</label>
        <input type="text" id="produto_nome_display" readonly>
    </div>

    <div class="form-group">
        <label for="grupo_id">Grupo de Máquinas:</label>
        <select id="grupo_id" name="grupo_id">
            <option value="">Selecione um produto para ver os grupos</option>
        </select>
    </div>

    <div class="form-group">
        <label for="quantidade_produzir">Quantidade a Produzir:</label>
        <input type="number" id="quantidade_produzir" name="quantidade_produzir" step="0.01" required>
    </div>

    <div class="form-group">
        <label for="data_emissao">Data de Emissão:</label>
        <input type="date" id="data_emissao" name="data_emissao" value="<?php echo date('Y-m-d'); ?>" required>
    </div>

    <div class="form-group">
        <label for="data_prevista_conclusao">Data Prevista de Conclusão:</label>
        <input type="date" id="data_prevista_conclusao" name="data_prevista_conclusao" value="<?php echo $default_data_prevista; ?>">
    </div>

    <div class="form-group full-width">
        <label for="observacoes">Observações:</label>
        <textarea id="observacoes" name="observacoes"></textarea>
    </div>

    <button type="submit" class="button submit">Criar Ordem de Produção</button>
</form>

<a href="index.php" class="back-link">Voltar para a lista de Ordens de Produção</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseUrl = '<?php echo BASE_URL; ?>'; 
        
        const produtoCodigoInput = document.getElementById('produto_codigo_search');
        const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
        const produtoNomeDisplay = document.getElementById('produto_nome_display');
        const grupoSelect = document.getElementById('grupo_id');

        function resetProductFields() {
            produtoIdHiddenInput.value = '';
            produtoNomeDisplay.value = '';
            grupoSelect.innerHTML = '<option value="">Selecione um produto para ver os grupos</option>';
        }

        produtoCodigoInput.addEventListener('blur', function() {
            const codigo = this.value.trim();
            if (codigo === '') {
                resetProductFields();
                return;
            }
            
            fetch(`${baseUrl}/modules/ordens_producao/ajax_get_produto_com_roteiro.php?codigo=${encodeURIComponent(codigo)}`)
                .then(response => {
                    if (!response.ok) return response.json().then(err => Promise.reject(err));
                    return response.json();
                })
                .then(data => {
                    produtoIdHiddenInput.value = data.id;
                    produtoNomeDisplay.value = data.nome;

                    grupoSelect.innerHTML = '<option value="">Selecione um grupo...</option>';
                    if (data.grupos && data.grupos.length > 0) {
                        data.grupos.forEach(grupo => {
                            const option = document.createElement('option');
                            option.value = grupo.id;
                            option.textContent = grupo.nome_grupo;
                            grupoSelect.appendChild(option);
                        });
                    } else {
                        grupoSelect.innerHTML = '<option value="">Nenhum grupo de trabalho encontrado para este roteiro</option>';
                    }
                })
                .catch(error => {
                    alert(error.error || 'Ocorreu um erro ao buscar o produto.');
                    resetProductFields();
                    this.value = '';
                    this.focus();
                });
        });
    });
</script>

<?php
$conn->close();
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>
