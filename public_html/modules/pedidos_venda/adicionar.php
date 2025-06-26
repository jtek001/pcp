<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
// OBSERVAÇÃO: Define o fuso horário para garantir a hora correta.
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = filter_input(INPUT_POST, 'cliente_id_hidden', FILTER_VALIDATE_INT);
    $numero_pedido = sanitizeInput($_POST['numero_pedido']);
    $data_pedido = sanitizeInput($_POST['data_pedido']);
    $data_previsao_entrega = sanitizeInput($_POST['data_previsao_entrega']) ?: null;
    $observacoes = sanitizeInput($_POST['observacoes']);
    
    if ($cliente_id && !empty($data_pedido) && !empty($numero_pedido)) {
        try {
            $sql_pedido = "INSERT INTO pedidos_venda (cliente_id, numero_pedido, data_pedido, data_previsao_entrega, observacoes, status) VALUES (?, ?, ?, ?, ?, 'Aguardando Itens')";
            $conn->execute_query($sql_pedido, [$cliente_id, $numero_pedido, $data_pedido, $data_previsao_entrega, $observacoes]);
            $pedido_id = $conn->insert_id;

            $_SESSION['message'] = "Cabeçalho do Pedido Nº {$pedido_id} criado com sucesso! Agora, adicione os itens.";
            $_SESSION['message_type'] = "success";
            header("Location: editar.php?id=" . $pedido_id);
            exit();

        } catch (Exception $e) {
            $_SESSION['message'] = "Erro ao criar o cabeçalho do pedido: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Cliente, Pedido e Data do Pedido são obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    header("Location: adicionar.php");
    exit();
}

$default_data_prevista = date('Y-m-d', strtotime('+30 days'));
$default_numero_pedido = date('ymdHis');
?>

<div class="container mt-4">
    <h2><i class="fas fa-plus-circle"></i> Novo Pedido de Venda - Etapa 1/2</h2>
    <p class="lead">Primeiro, selecione o cliente e defina as datas do pedido.</p>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="adicionar.php" method="POST" id="form-pedido-header">
        
        <div class="form-group full-width">
            <label for="cliente_search">Cliente</label>
            <input type="text" id="cliente_search" list="cliente_options" class="form-control" placeholder="Digite o nome ou CNPJ do cliente" autocomplete="off" required>
            <datalist id="cliente_options"></datalist>
            <input type="hidden" name="cliente_id_hidden" id="cliente_id_hidden" required>
        </div>

        <div class="form-group full-width">
            <div class="row">
                <div class="col-md-4">
                    <label for="numero_pedido">Número do Pedido</label>
                    <input type="text" name="numero_pedido" id="numero_pedido" class="form-control" value="<?php echo $default_numero_pedido; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="data_pedido">Data do Pedido</label>
                    <input type="date" name="data_pedido" id="data_pedido" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="data_previsao_entrega">Previsão de Entrega</label>
                    <input type="date" name="data_previsao_entrega" id="data_previsao_entrega" class="form-control" value="<?php echo $default_data_prevista; ?>">
                </div>
            </div>
        </div>
        
        <div class="form-group full-width">
            <label for="observacoes">Observações</label>
            <textarea name="observacoes" rows="3"></textarea>
        </div>

        <div class="full-width" style="text-align: center; grid-column: 1 / -1;">
             <button type="submit" class="button submit">Criar Pedido e Adicionar Itens</button>
        </div>
    </form>
    
    <a href="index.php" class="back-link">Cancelar e Voltar</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>';
    const clienteSearchInput = document.getElementById('cliente_search');
    const clienteOptionsDatalist = document.getElementById('cliente_options');
    const clienteIdHiddenInput = document.getElementById('cliente_id_hidden');
    
    let debounceCliente;
    clienteSearchInput.addEventListener('input', function() {
        clienteIdHiddenInput.value = '';
        const searchTerm = this.value;

        clearTimeout(debounceCliente);
        if (searchTerm.length < 2) {
            clienteOptionsDatalist.innerHTML = '';
            return;
        }
        debounceCliente = setTimeout(() => {
            fetch(`${baseUrl}/modules/pedidos_venda/ajax_get_clientes_for_pedido.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    clienteOptionsDatalist.innerHTML = '';
                    data.forEach(cliente => {
                        const option = document.createElement('option');
                        option.value = `${cliente.nome} (${cliente.cnpj || 'N/A'})`;
                        option.setAttribute('data-id', cliente.id);
                        clienteOptionsDatalist.appendChild(option);
                    });
                });
        }, 300);
    });

    clienteSearchInput.addEventListener('change', function() {
        const selectedOption = Array.from(clienteOptionsDatalist.options).find(option => option.value === this.value);
        if (selectedOption) {
            clienteIdHiddenInput.value = selectedOption.getAttribute('data-id');
        }
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
$conn->close();
ob_end_flush();
?>
