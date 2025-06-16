<?php
// modules/materiais_insumos/excluir.php
// Esta página é responsável por "excluir" (soft delete) uma entrada de material/insumo.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração
require_once __DIR__ . '/../../config/database.php';

// Variáveis para armazenar a mensagem de feedback
$message = '';
$message_type = '';
$redirect_id = 0; // Para redirecionar para a página da lista

// Pega o ID da entrada da URL (GET)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $conn = connectDB();
    $conn->begin_transaction();

    try {
        // 1. Obter a quantidade original da entrada e o produto_id antes de marcar como excluída
        $original_quantidade = 0;
        $produto_id_from_entry = 0;
        $sql_get_entry_details = "SELECT produto_id, quantidade, numero_nota_fiscal FROM materiais_insumos_entrada WHERE id = ?";
        $result_details = $conn->execute_query($sql_get_entry_details, [$id]);
        if ($result_details && $row_details = $result_details->fetch_assoc()) {
            $produto_id_from_entry = $row_details['produto_id'];
            $original_quantidade = $row_details['quantidade'];
            $numero_nota_fiscal_log = $row_details['numero_nota_fiscal'];
            $result_details->free();
        } else {
            throw new mysqli_sql_exception("Detalhes da entrada não encontrados para ID: " . $id);
        }

        // 2. Realizar o "soft delete" na entrada
        $sql_soft_delete = "UPDATE materiais_insumos_entrada SET deleted_at = NOW() WHERE id = ?";
        $conn->execute_query($sql_soft_delete, [$id]);

        // 3. Ajustar o estoque do produto (Remover a quantidade que foi adicionada por esta entrada)
        $sql_adjust_estoque = "UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id = ?";
        $conn->execute_query($sql_adjust_estoque, [$original_quantidade, $produto_id_from_entry]);

        // 4. Registrar uma movimentação de estoque de "saída" (ajuste por exclusão)
        $sql_mov_estoque = "INSERT INTO movimentacoes_estoque (produto_id, tipo_movimentacao, quantidade, data_hora_movimentacao, origem_destino, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
        
        $params_mov_estoque = [
            $produto_id_from_entry,
            'saida', // Tipo de movimentação é 'saída' para reverter a entrada
            $original_quantidade,
            date('Y-m-d H:i:s'), // Data/hora da remoção
            "Remoção Entrada Material NF: " . $numero_nota_fiscal_log . " (ID Entrada: " . $id . ")",
            "Entrada de material/insumo excluída (soft delete)."
        ];
        $conn->execute_query($sql_mov_estoque, $params_mov_estoque);

        $conn->commit();
        $message = "Entrada de material/insumo excluída (logicamente) com sucesso! Estoque e movimentação ajustados.";
        $message_type = "success";

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $message = "Erro na transação de exclusão de entrada: " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal na transação de exclusão de entrada: " . $e->getMessage());
    }

    $conn->close();
} else {
    $message = "ID da entrada de material inválido para exclusão.";
    $message_type = "error";
}

// Redireciona de volta para a página de listagem de entradas
header("Location: " . BASE_URL . "/modules/materiais/index.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>
