<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$consumo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$op_id_retorno = null;

if (!$consumo_id) {
    $_SESSION['message'] = "ID do consumo inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$conn->begin_transaction();
try {
    $sql_get_consumo = "SELECT * FROM consumo_producao WHERE id = ?";
    $consumo_data = $conn->execute_query($sql_get_consumo, [$consumo_id])->fetch_assoc();

    if (!$consumo_data) {
        throw new Exception("Registro de consumo de insumo não encontrado.");
    }

    $op_id_retorno = (int)$consumo_data['ordem_producao_id'];
    $quantidade_estornada = (float)$consumo_data['quantidade_consumida'];
    $produto_id_estornado = (int)$consumo_data['produto_material_id'];

    // Soft delete do registro de consumo
    $conn->execute_query("UPDATE consumo_producao SET deleted_at = NOW() WHERE id = ?", [$consumo_id]);

    // Devolve a quantidade ao estoque do produto
    $conn->execute_query("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id = ?", [$quantidade_estornada, $produto_id_estornado]);

    // Registra a movimentação de estorno (entrada)
    $obs_mov_reversal = "Estorno do Consumo de Insumo ID: " . $consumo_id;
    $conn->execute_query("INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, origem_destino, observacoes) VALUES (?, 'entrada', ?, ?, ?)", [$produto_id_estornado, $quantidade_estornada, 'Estorno Insumo', $obs_mov_reversal]);

    $conn->commit();
    $_SESSION['message'] = "Consumo de insumo estornado com sucesso! O estoque foi atualizado.";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao estornar insumo: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Redireciona de volta para a página de insumos da OP correta
header("Location: insumos.php?id=" . $op_id_retorno);
exit();
?>
