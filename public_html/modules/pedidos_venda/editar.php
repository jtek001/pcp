<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';

$conn = connectDB();
$pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$pedido_id) {
    $_SESSION['message'] = "ID do pedido inválido.";
    $_SESSION['message_type'] = "error";
    header("Location: index.php");
    exit();
}

// Processa ações (adicionar, remover, finalizar, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_FLOAT);
        $preco_unitario = filter_input(INPUT_POST, 'preco_unitario', FILTER_VALIDATE_FLOAT);
        
        if ($produto_id && $quantidade > 0) {
            $subtotal = $quantidade * $preco_unitario;
            $sql = "INSERT INTO pedidos_venda_itens (pedido_venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiddd", $pedido_id, $produto_id, $quantidade, $preco_unitario, $subtotal);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'delete_item') {
        $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        if ($item_id) {
            $conn->execute_query("DELETE FROM pedidos_venda_itens WHERE id = ?", [$item_id]);
        }
    } elseif ($action === 'approve_order') {
        $conn->execute_query("UPDATE pedidos_venda SET status = 'Aprovado' WHERE id = ?", [$pedido_id]);
        $_SESSION['message'] = "Pedido enviado para aprovação.";
        $_SESSION['message_type'] = "success";
    } elseif ($action === 'mark_completed') {
        $conn->execute_query("UPDATE pedidos_venda SET status = 'Concluido' WHERE id = ?", [$pedido_id]);
        $_SESSION['message'] = "Pedido marcado como Concluído.";
        $_SESSION['message_type'] = "success";
    }

    // Recalcula o total do pedido após qualquer alteração de itens
    if ($action === 'add_item' || $action === 'delete_item') {
        $total_result = $conn->execute_query("SELECT SUM(subtotal) as total FROM pedidos_venda_itens WHERE pedido_venda_id = ?", [$pedido_id])->fetch_assoc();
        $novo_total = $total_result['total'] ?? 0.00;
        $conn->execute_query("UPDATE pedidos_venda SET valor_total = ? WHERE id = ?", [$novo_total, $pedido_id]);
    }

    header("Location: editar.php?id=" . $pedido_id);
    exit();
}


// Busca os dados do pedido e do cliente
$sql_pedido = "SELECT pv.*, fc.nome as cliente_nome, fc.cnpj 
               FROM pedidos_venda pv 
               JOIN fornecedores_clientes_lookup fc ON pv.cliente_id = fc.id
               WHERE pv.id = ?";
$pedido = $conn->execute_query($sql_pedido, [$pedido_id])->fetch_assoc();

if (!$pedido) {
    die("Pedido não encontrado.");
}

// Busca os itens do pedido
$itens = $conn->execute_query("SELECT pvi.*, p.nome as produto_nome, p.codigo as produto_codigo FROM pedidos_venda_itens pvi JOIN produtos p ON pvi.produto_id = p.id WHERE pvi.pedido_venda_id = ?", [$pedido_id])->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Gerir Pedido de Venda</h2>
    
    <div class="alert alert-secondary">
        <strong>Pedido Nº:</strong> <?php echo htmlspecialchars($pedido['numero_pedido']); ?> | 
        <strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome']); ?> |
        <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?> |
        <strong>Status:</strong> <span class="status-<?php echo strtolower(str_replace(' ', '-', $pedido['status'])); ?>"><?php echo htmlspecialchars($pedido['status']); ?></span>
    </div>

    <!-- Seção para adicionar novos itens (apenas se o pedido não estiver concluído ou cancelado) -->
    <?php if ($pedido['status'] !== 'Concluido' && $pedido['status'] !== 'Cancelado'): ?>
    <div class="card mb-4">
        <div class="card-header">Adicionar Itens ao Pedido</div>
        <div class="card-body">
            <div class="form-group full-width">
                <label for="produto_search">Buscar Produto</label>
                <input type="text" id="produto_search" class="form-control" placeholder="Digite o nome ou código...">
                <div id="produto_results" class="list-group position-absolute" style="z-index: 1000;"></div>
                <input type="hidden" id="produto_id">
            </div>
            <div class="row align-items-end mt-2">
                <div class="col-md-5">
                    <label for="quantidade">Quantidade</label>
                    <input type="number" id="quantidade" class="form-control" step="0.01" value="1">
                </div>
                <div class="col-md-5">
                    <label for="preco_unitario">Preço Unitário</label>
                    <input type="number" id="preco_unitario" class="form-control" step="0.01" value="0.00">
                </div>
                <div class="col-md-2">
                    <button type="button" class="button add" id="add_item_button" style="width: 100%; height: 38px;">Adicionar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabela de Itens do Pedido -->
    <div class="card">
        <div class="card-header">Itens do Pedido</div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="text-end">Quantidade</th>
                        <th class="text-end">Preço Unit.</th>
                        <th class="text-end">Subtotal</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody id="itens_pedido_body">
                    <?php foreach ($itens as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['produto_nome'] . ' (' . $item['produto_codigo'] . ')'); ?></td>
                        <td class="text-end"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                        <td class="text-end">R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                        <td class="text-end">R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></td>
                        <td>
                            <?php if ($pedido['status'] !== 'Concluido' && $pedido['status'] !== 'Cancelado'): ?>
                            <button type="button" class="button delete small" onclick="removerItem(<?php echo $item['id']; ?>)">Remover</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; font-size: 1.1em;">
                        <td colspan="3" class="text-end">TOTAL DO PEDIDO:</td>
                        <td class="text-end">R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <div class="mt-4 d-flex justify-content-between align-items-center">
        <a href="index.php" class="button button-clear">Voltar para a Lista</a>
        <div>
            <?php if($pedido['status'] === 'Aguardando Itens'): ?>
                 <button type="button" class="button submit" onclick="submitAction('approve_order')">Aprovar</button>
            <?php elseif($pedido['status'] === 'Aprovado' || $pedido['status'] === 'Em Producao'): ?>
                <button type="button" class="button" style="background-color: #f39c12;">Gerar OP</button>
                <button type="button" class="button submit" onclick="submitAction('mark_completed')">Finalizar</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// JavaScript para manipular as ações da página
function submitAction(actionName, itemId = null) {
    // Apenas para as ações que não precisam de confirmação, como 'approve_order' ou 'mark_completed'
    if (actionName === 'approve_order' || actionName === 'mark_completed') {
        if (!confirm('Tem certeza que deseja continuar com esta ação?')) {
            return;
        }
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'editar.php?id=<?php echo $pedido_id; ?>';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = actionName;
    form.appendChild(actionInput);

    if (itemId) {
        const itemIdInput = document.createElement('input');
        itemIdInput.type = 'hidden';
        itemIdInput.name = 'item_id';
        itemIdInput.value = itemId;
        form.appendChild(itemIdInput);
    }
    
    document.body.appendChild(form);
    form.submit();
}

function removerItem(itemId) {
    if (confirm('Tem certeza que deseja remover este item?')) {
        submitAction('delete_item', itemId);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = '<?php echo rtrim(BASE_URL, '/'); ?>';
    const produtoSearchInput = document.getElementById('produto_search');
    const produtoResultsDiv = document.getElementById('produto_results');
    const produtoIdInput = document.getElementById('produto_id');

    produtoSearchInput.addEventListener('keyup', function() {
        const query = this.value;
        if (query.length < 2) {
            produtoResultsDiv.innerHTML = '';
            return;
        }
        fetch(`${baseUrl}/modules/pedidos_venda/ajax_get_produtos_for_pedido.php?q=${query}`)
            .then(response => response.json())
            .then(data => {
                produtoResultsDiv.innerHTML = '';
                data.forEach(produto => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = `${produto.nome} (${produto.codigo})`;
                    item.onclick = (e) => {
                        e.preventDefault();
                        produtoSearchInput.value = item.textContent;
                        produtoIdInput.value = produto.id;
                        produtoResultsDiv.innerHTML = '';
                    };
                    produtoResultsDiv.appendChild(item);
                });
            });
    });

    document.getElementById('add_item_button').addEventListener('click', function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'editar.php?id=<?php echo $pedido_id; ?>';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="produto_id" value="${document.getElementById('produto_id').value}">
            <input type="hidden" name="quantidade" value="${document.getElementById('quantidade').value}">
            <input type="hidden" name="preco_unitario" value="${document.getElementById('preco_unitario').value}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
});
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
$conn->close();
ob_end_flush();
?>
