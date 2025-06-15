<?php
// modules/bom/editar.php
// Esta página permite editar um item da Lista de Materiais (BoM) existente.

// Inicia a sessão para usar variáveis de sessão
session_start();

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define o fuso horário padrão do PHP para Brasília.
date_default_timezone_set('America/Sao_Paulo');

// Inclui os arquivos de configuração e o cabeçalho
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variáveis para mensagens de sucesso/erro
$message = '';
$message_type = '';
$bom_item_data = null; // Dados do item da BoM a ser editado

// Pega o ID do item da BoM da URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Se o ID for inválido, redireciona
if ($id <= 0) {
    header("Location: " . BASE_URL . "/modules/bom/index.php?message=" . urlencode("ID do item da BoM inválido para edição.") . "&type=error");
    exit();
}

// --- Lógica para buscar os dados do item da BoM para preencher o formulário ---
$sql_select = "SELECT lm.*, p_pai.nome AS produto_pai_nome, p_pai.codigo AS produto_pai_codigo, p_filho.nome AS produto_filho_nome, p_filho.codigo AS produto_filho_codigo FROM lista_materiais lm JOIN produtos p_pai ON lm.produto_pai_id = p_pai.id JOIN produtos p_filho ON lm.produto_filho_id = p_filho.id WHERE lm.id = ? AND lm.deleted_at IS NULL";
try {
    $result_bom_data = $conn->execute_query($sql_select, [$id]);
    if ($result_bom_data) {
        $bom_item_data = $result_bom_data->fetch_assoc();
        $result_bom_data->free();
        if (!$bom_item_data) {
            $message = "Item da Lista de Materiais não encontrado para edição.";
            $message_type = "error";
        }
    } else {
        $message = "Erro ao carregar dados do item da BoM: " . $conn->error;
        $message_type = "error";
        error_log("Erro ao carregar dados do item da BoM: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    $message = "Erro fatal ao carregar dados do item da BoM (SQL): " . $e->getMessage();
    $message_type = "error";
    error_log("Erro fatal ao carregar dados do item da BoM: " . $e->getMessage());
}

// Processa o formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0 && $bom_item_data) {
    // Sanitiza e valida as entradas
    // Produtos Pai e Filho são readonly no HTML, usa os valores originais do DB
    $temp_produto_pai_id = $bom_item_data['produto_pai_id']; 
    $temp_produto_filho_id = $bom_item_data['produto_filho_id']; 
    $temp_quantidade_necessaria = (float) sanitizeInput(isset($_POST['quantidade_necessaria']) ? $_POST['quantidade_necessaria'] : 0.0);
    $temp_observacoes = sanitizeInput(isset($_POST['observacoes']) ? $_POST['observacoes'] : '');

    // Validação básica de campos obrigatórios
    if (empty($temp_quantidade_necessaria) || $temp_quantidade_necessaria <= 0) {
        $message = "Quantidade Necessária deve ser maior que zero.";
        $message_type = "error";
    } else {
        // Inicia transação
        $conn->begin_transaction();
        try {
            // 1. Atualizar o item na lista de materiais
            $sql_update_bom = "UPDATE lista_materiais SET quantidade_necessaria = ?, observacoes = ? WHERE id = ?";
            
            $params_update_bom = [
                $temp_quantidade_necessaria,
                $temp_observacoes,
                $id
            ];

            $conn->execute_query($sql_update_bom, $params_update_bom);

            $conn->commit();
            $message = "Item da Lista de Materiais atualizado com sucesso!";
            $message_type = "success";
            
            // Recarrega os dados do item da BoM após o sucesso para refletir na tela
            $result_bom_data_reloaded = $conn->execute_query($sql_select, [$id]);
            if ($result_bom_data_reloaded) {
                $bom_item_data = $result_bom_data_reloaded->fetch_assoc();
                $result_bom_data_reloaded->free();
            }

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Erro ao atualizar item da Lista de Materiais (SQL): " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal ao atualizar item da BoM: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

// Dados do produto original para preencher o campo de busca (JS)
$initial_produto_pai_search_value = '';
if (isset($bom_item_data['produto_pai_id']) && !empty($bom_item_data['produto_pai_id'])) {
    $initial_produto_pai_search_value = $bom_item_data['produto_pai_nome'] . ' (' . $bom_item_data['produto_pai_codigo'] . ')';
}

$initial_produto_filho_search_value = '';
if (isset($bom_item_data['produto_filho_id']) && !empty($bom_item_data['produto_filho_id'])) {
    $initial_produto_filho_search_value = $bom_item_data['produto_filho_nome'] . ' (' . $bom_item_data['produto_filho_codigo'] . ')';
}

?>

<h2>Editar Item da Lista de Materiais (BoM)</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($bom_item_data): ?>
    <form action="editar.php?id=<?php echo $bom_item_data['id']; ?>" method="POST">
        <div class="form-group">
            <label for="produto_pai_search">Produto Pai (Acabado/Montado):</label>
            <input type="text" id="produto_pai_search" value="<?php echo $initial_produto_pai_search_value; ?>" readonly>
            <input type="hidden" id="produto_pai_id_hidden" name="produto_pai_id_hidden" value="<?php echo htmlspecialchars($bom_item_data['produto_pai_id']); ?>">
        </div>

        <div class="form-group">
            <label for="produto_filho_search">Produto Filho (Componente/Material):</label>
            <input type="text" id="produto_filho_search" value="<?php echo $initial_produto_filho_search_value; ?>" readonly>
            <input type="hidden" id="produto_filho_id_hidden" name="produto_filho_id_hidden" value="<?php echo htmlspecialchars($bom_item_data['produto_filho_id']); ?>">
        </div>

        <div class="form-group">
            <label for="quantidade_necessaria">Quantidade Necessária (por unidade do Pai):</label>
            <input type="number" id="quantidade_necessaria" name="quantidade_necessaria" step="0.0001" value="<?php echo htmlspecialchars($post_values['quantidade_necessaria'] ?? $bom_item_data['quantidade_necessaria']); ?>" required min="0.0001" placeholder="Ex: 2.5000">
        </div>

        <div class="form-group full-width">
            <label for="observacoes">Observações:</label>
            <textarea id="observacoes" name="observacoes" placeholder="Observações sobre este item na BoM..."><?php echo htmlspecialchars($post_values['observacoes'] ?? $bom_item_data['observacoes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button submit">Atualizar Item da BoM</button>
    </form>
<?php else: ?>
    <p class="error" style="text-align: center;">Item da Lista de Materiais não encontrado para edição.</p>
<?php endif; ?>

<a href="index.php" class="back-link">Voltar para a Lista de Materiais (BoM)</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Scripts para toggleInput não são necessários aqui, pois campos de produto são readonly.
        // Apenas para evitar erros de referência se eles estivessem no adicionar.php
    });
</script>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
