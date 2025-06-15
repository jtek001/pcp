<?php
// modules/ordens_producao/excluir_apontamento.php
// Esta página é responsável por "excluir" (soft delete) um apontamento de produção.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração (para ter acesso à função connectDB e sanitizeInput)
require_once __DIR__ . '/../../config/database.php';

// Variáveis para armazenar a mensagem de feedback
$message = '';
$message_type = '';
$redirect_op_id = 0; // ID da OP para redirecionar de volta

// Pega o ID do apontamento da URL (GET)
$apontamento_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Verifica se o ID é válido
if ($apontamento_id > 0) {
    // Conecta ao banco de dados
    $conn = connectDB();

    // Primeiro, busca a quantidade produzida ORIGINAL deste apontamento e o ID da OP, antes de qualquer alteração
    $original_quantidade_produzida = 0;
    $apontamento_op_id = 0;
    $apontamento_produto_id = 0;
    $apontamento_maquina_id = 0;
    $apontamento_operador_id = 0;
    $apontamento_numero_op = '';

    $sql_get_apontamento_details = "SELECT ap.quantidade_produzida, ap.ordem_producao_id, ap.maquina_id, ap.operador_id, op.numero_op, op.produto_id FROM apontamentos_producao ap JOIN ordens_producao op ON ap.ordem_producao_id = op.id WHERE ap.id = ?";
    
    try {
        $result_details = $conn->execute_query($sql_get_apontamento_details, [$apontamento_id]);
        if ($result_details && $row_details = $result_details->fetch_assoc()) {
            $original_quantidade_produzida = $row_details['quantidade_produzida'];
            $apontamento_op_id = $row_details['ordem_producao_id'];
            $apontamento_produto_id = $row_details['produto_id'];
            $apontamento_maquina_id = $row_details['maquina_id'];
            $apontamento_operador_id = $row_details['operador_id'];
            $apontamento_numero_op = $row_details['numero_op'];
            $result_details->free();
        } else {
            throw new mysqli_sql_exception("Detalhes do apontamento não encontrados para ID: " . $apontamento_id);
        }
    } catch (mysqli_sql_exception $e) {
        $message = "Erro ao obter detalhes do apontamento: " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal ao obter detalhes do apontamento: " . $e->getMessage());
        // Redireciona com erro se não conseguir pegar os detalhes iniciais
        header("Location: " . BASE_URL . "/modules/ordens_producao/index.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit();
    }

    $redirect_op_id = $apontamento_op_id; // Define o ID da OP para o redirecionamento final

    // Inicia transação para garantir atomicidade
    $conn->begin_transaction();
    try {
        // --- 1. Realizar o "soft delete" no apontamento ---
        // Atualiza o campo 'deleted_at' com a data e hora atual
        $sql_soft_delete_apontamento = "UPDATE apontamentos_producao SET deleted_at = NOW() WHERE id = ?";
        $conn->execute_query($sql_soft_delete_apontamento, [$apontamento_id]);

        // --- 2. Atualizar o estoque do produto (REMOVER a quantidade que foi apontada) ---
        $sql_update_estoque = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?";
        $conn->execute_query($sql_update_estoque, [$original_quantidade_produzida, $apontamento_produto_id]);

        // --- 3. Registrar a movimentação de estoque de saída (ajuste por exclusão) ---
        $sql_mov_estoque = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
        
        // Para logar o operador no registro da movimentação de estoque (se for possível buscar o nome)
        $operator_name_for_log = '';
        // CORRIGIDO: Buscar nome do operador da tabela 'operadores'
        $sql_get_operator_name = "SELECT nome, matricula FROM operadores WHERE id = ?"; 
        $result_op_name = $conn->execute_query($sql_get_operator_name, [$apontamento_operador_id]);
        if ($result_op_name && $row_op_name = $result_op_name->fetch_assoc()) {
            $operator_name_for_log = $row_op_name['nome'] . ' (' . $row_op_name['matricula'] . ')';
            $result_op_name->free();
        }

        $origem_destino_log = "Remoção Apontamento OP: " . $apontamento_numero_op . " (Máquina ID: " . $apontamento_maquina_id . ", Operador: " . $operator_name_for_log . ") [EXCLUÍDO]";
        
        $conn->execute_query($sql_mov_estoque, [
            $apontamento_produto_id,
            'saida', // Tipo de movimentação é saída (revertendo o apontamento)
            $original_quantidade_produzida,
            date('Y-m-d H:i:s'), // Data/hora da remoção
            $origem_destino_log,
            "Remoção de apontamento de produção ID: " . $apontamento_id
        ]);

        // --- 4. Recalcular total produzido para a OP e reavaliar status ---
        $sql_total_produzido = "SELECT SUM(COALESCE(quantidade_produzida, 0)) AS total_produzido FROM apontamentos_producao WHERE ordem_producao_id = ? AND deleted_at IS NULL"; // Apenas apontamentos ATIVOS
        $result_total = $conn->execute_query($sql_total_produzido, [$apontamento_op_id]);
        $row_total = $result_total->fetch_assoc();
        $total_produzido_op = $row_total['total_produzido'];

        // Buscar dados atuais da OP para determinar o novo status
        $sql_op_current_data = "SELECT quantidade_produzir, status, data_conclusao FROM ordens_producao WHERE id = ?";
        $result_op_current = $conn->execute_query($sql_op_current_data, [$apontamento_op_id]);
        $op_current_data = $result_op_current->fetch_assoc();

        $new_op_status = $op_current_data['status'];
        $new_op_data_conclusao = $op_current_data['data_conclusao'];

        // Lógica para reajustar o status da OP
        if ($total_produzido_op < $op_current_data['quantidade_produzir']) {
            // Se a quantidade total produzida (após a exclusão) for menor que a meta da OP
            if ($new_op_status === 'concluida') { // Se a OP estava concluída, agora volta para 'em_producao'
                $new_op_status = 'em_producao';
                $new_op_data_conclusao = NULL; // Remove a data de conclusão se não estiver mais concluída
            }
        }
        // Se ainda não atingiu a meta, e estava pendente, continua pendente.
        // Se estava em produção, continua em produção.

        // Atualiza o status e data de conclusão da OP
        $sql_update_op_status = "UPDATE ordens_producao SET status = ?, data_conclusao = ? WHERE id = ?";
        $conn->execute_query($sql_update_op_status, [$new_op_status, $new_op_data_conclusao, $apontamento_op_id]);

        $conn->commit();
        $message = "Apontamento excluído (logicamente) com sucesso! Estoque e status da OP reajustados. Novo status da OP: " . $new_op_status;
        $message_type = "success";

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $message = "Erro na transação de exclusão de apontamento: " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal na transação de exclusão de apontamento: " . $e->getMessage());
    }

    // Fecha a conexão com o banco de dados
    $conn->close();
} else {
    // Se o ID não for válido, define uma mensagem de erro
    $message = "ID do apontamento inválido para exclusão.";
    $message_type = "error";
}

// Redireciona de volta para a página de apontamento da OP original
// Se o redirect_op_id for 0, redireciona para a lista geral de OPs
$redirect_url = ($redirect_op_id > 0) ? "apontar.php?id=" . $redirect_op_id : "index.php";
header("Location: " . BASE_URL . "/modules/ordens_producao/" . $redirect_url . "&message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>
