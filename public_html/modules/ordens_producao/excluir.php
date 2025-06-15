<?php
// modules/ordens_producao/excluir.php
// Esta página é responsável por "excluir" (soft delete) uma Ordem de Produção do banco de dados e redirecionar.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração (para ter acesso à função connectDB e sanitizeInput)
require_once __DIR__ . '/../../config/database.php';

// Variáveis para armazenar a mensagem de feedback
$message = '';
$message_type = '';

// Pega o ID da Ordem de Produção da URL (GET)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Verifica se o ID é válido
if ($id > 0) {
    // Conecta ao banco de dados
    $conn = connectDB();

    $conn->begin_transaction(); // Inicia a transação
    try {
        // --- 1. Obter todos os empenhos relacionados a esta OP antes de excluí-la ---
        $empenhos_da_op = [];
        $sql_get_empenhos = "SELECT id, produto_id, quantidade_empenhada FROM empenho_materiais WHERE ordem_producao_id = ? AND deleted_at IS NULL";
        $result_empenhos = $conn->execute_query($sql_get_empenhos, [$id]);

        if ($result_empenhos) {
            while ($row_empenho = $result_empenhos->fetch_assoc()) {
                $empenhos_da_op[] = $row_empenho;
            }
            $result_empenhos->free();
        } else {
            // Se houver um erro ao buscar empenhos, logar, mas não necessariamente abortar (depende da criticidade)
            error_log("Erro ao buscar empenhos para OP " . $id . ": " . $conn->error);
        }

        // --- 2. Desfazer o empenho nos produtos e marcar os empenhos como deleted_at ---
        if (!empty($empenhos_da_op)) {
            foreach ($empenhos_da_op as $empenho) {
                // Diminuir o estoque_empenhado do produto
                $sql_update_produto_empenhado = "UPDATE produtos SET estoque_empenhado = estoque_empenhado - ? WHERE id = ?";
                $conn->execute_query($sql_update_produto_empenhado, [$empenho['quantidade_empenhada'], $empenho['produto_id']]);

                // Marcar o registro de empenho como deleted_at
                $sql_soft_delete_empenho = "UPDATE empenho_materiais SET deleted_at = NOW() WHERE id = ?";
                $conn->execute_query($sql_soft_delete_empenho, [$empenho['id']]);
            }
        }

        // --- 3. Realizar o "soft delete" na Ordem de Produção ---
        $sql = "UPDATE ordens_producao SET deleted_at = NOW() WHERE id = ?";
        $params = [$id];
        $result_update = $conn->execute_query($sql, $params);

        if ($result_update === TRUE) {
            $message = "Ordem de Produção e empenhos relacionados excluídos (logicamente) com sucesso!";
            $message_type = "success";
        } else {
            throw new mysqli_sql_exception("Erro ao marcar Ordem de Produção como excluída: " . $conn->error);
        }

        $conn->commit(); // Confirma a transação se tudo deu certo

    } catch (mysqli_sql_exception $e) {
        $conn->rollback(); // Reverte a transação em caso de erro
        $message = "Erro na transação de exclusão da Ordem de Produção/Empenho: " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal na transação de exclusão da OP/Empenho: " . $e->getMessage());
    }

    // Fecha a conexão com o banco de dados
    $conn->close();
} else {
    // Se o ID não for válido, define uma mensagem de erro
    $message = "ID da Ordem de Produção inválido para exclusão.";
    $message_type = "error";
}

// Redireciona de volta para a página de listagem de OPs, passando a mensagem de feedback
header("Location: " . BASE_URL . "/modules/ordens_producao/index.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>
