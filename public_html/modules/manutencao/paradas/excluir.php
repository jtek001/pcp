<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Verifica se o ID foi fornecido e é numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID da parada inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];
$conn = connectDB();

$conn->begin_transaction();
try {
    // Primeiro, busca o ID da máquina associada a esta parada
    $sql_get_maquina = "SELECT maquina_id FROM paradas_maquina WHERE id = ?";
    $result = $conn->execute_query($sql_get_maquina, [$id]);
    $parada = $result->fetch_assoc();

    if (!$parada) {
        throw new Exception("Registro de parada não encontrado.");
    }
    $maquina_id = $parada['maquina_id'];

    // Lógica de Soft Delete: atualiza a coluna 'deleted_at' com a data e hora atuais
    $sql_delete = "UPDATE paradas_maquina SET deleted_at = NOW() WHERE id = ?";
    $conn->execute_query($sql_delete, [$id]);

    // --- OBSERVAÇÃO: LÓGICA DE ATUALIZAÇÃO INTELIGENTE DE STATUS ---
    // 1. Verifica se existem OUTRAS paradas ativas (em aberto) para a mesma máquina.
    $sql_check_other_stops = "SELECT COUNT(*) as total FROM paradas_maquina WHERE maquina_id = ? AND data_hora_fim IS NULL AND deleted_at IS NULL";
    $other_stops_count = $conn->execute_query($sql_check_other_stops, [$maquina_id])->fetch_assoc()['total'];

    // 2. Só altera o status para 'operacional' se não houver mais nenhuma parada em aberto.
    if ($other_stops_count == 0) {
        $sql_update_maquina = "UPDATE maquinas SET status = 'operacional' WHERE id = ?";
        $conn->execute_query($sql_update_maquina, [$maquina_id]);
        $_SESSION['message'] = "Registro de parada excluído e status da máquina atualizado para 'operacional'.";
    } else {
        $_SESSION['message'] = "Registro de parada excluído. O status da máquina não foi alterado pois existem outras paradas em aberto.";
    }

    $conn->commit();
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "Erro ao excluir o registro de parada: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}


$conn->close();
header("Location: index.php");
exit();
?>
