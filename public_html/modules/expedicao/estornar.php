<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$log_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$log_id) {
    $_SESSION['message'] = "ID da movimentação inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: historico.php");
    exit();
}

$conn->begin_transaction();
try {
    // 1. Busca os dados do log para saber o que reverter
    $sql_get_log = "SELECT * FROM expedicao_log WHERE id = ?";
    $log_data = $conn->execute_query($sql_get_log, [$log_id])->fetch_assoc();

    if (!$log_data) {
        throw new Exception("Registro de log não encontrado.");
    }

    $produto_id = $log_data['produto_id'];
    $quantidade = (float)$log_data['quantidade'];
    $tipo_movimentacao = $log_data['tipo_movimentacao'];
    $lote_numero = $log_data['lote_numero'];

    if ($tipo_movimentacao === 'entrada') {
        // OBSERVAÇÃO: Nova validação para impedir estorno de entrada com saída já registrada.
        // 1. Verifica se já existe alguma saída para este lote.
        $sql_check_saida = "SELECT COUNT(*) as total FROM expedicao_log WHERE lote_numero = ? AND tipo_movimentacao = 'saida'";
        $saidas_count = $conn->execute_query($sql_check_saida, [$lote_numero])->fetch_assoc()['total'] ?? 0;

        if ($saidas_count > 0) {
            throw new Exception("Não é possível estornar esta entrada, pois o lote já possui registros de saída.");
        }
        
        // Estornar uma ENTRADA: devolve o item para o estoque de produção
        
        // 2. Devolve a quantidade ao saldo do lote de produção
        $conn->execute_query("UPDATE apontamentos_producao SET quantidade_produzida = quantidade_produzida + ?, deleted_at = NULL WHERE lote_numero = ?", [$quantidade, $lote_numero]);
        
        // 3. Subtrai do estoque da expedição
        $conn->execute_query("UPDATE produtos SET estoque_expedicao = GREATEST(0, estoque_expedicao - ?) WHERE id = ?", [$quantidade, $produto_id]);

    } elseif ($tipo_movimentacao === 'saida') {
        // Estornar uma SAÍDA: devolve o item para o estoque da expedição
        
        // 1. Adiciona a quantidade de volta ao estoque da expedição
        $conn->execute_query("UPDATE produtos SET estoque_expedicao = estoque_expedicao + ? WHERE id = ?", [$quantidade, $produto_id]);
    }

    // Remove o registro do log de expedição
    $conn->execute_query("DELETE FROM expedicao_log WHERE id = ?", [$log_id]);

    $conn->commit();
    $_SESSION['message'] = "Movimentação estornada com sucesso!";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao estornar movimentação: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

header("Location: historico.php");
exit();
?>
