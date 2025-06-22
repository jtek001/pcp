<?php
ob_start();
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produto_id = filter_input(INPUT_POST, 'produto_id_hidden', FILTER_VALIDATE_INT);
    $descricao = sanitizeInput($_POST['descricao']);
    $ativo = filter_input(INPUT_POST, 'ativo', FILTER_VALIDATE_INT);

    if ($produto_id && !empty($descricao)) {
        // Verifica se já existe um roteiro para este produto
        $check_sql = "SELECT id FROM roteiros WHERE produto_id = ? AND deleted_at IS NULL";
        $existing = $conn->execute_query($check_sql, [$produto_id])->fetch_assoc();

        if ($existing) {
            $_SESSION['message'] = "Erro: Já existe um roteiro cadastrado para este produto.";
            $_SESSION['message_type'] = "error";
        } else {
            try {
                $sql = "INSERT INTO roteiros (produto_id, descricao, ativo) VALUES (?, ?, ?)";
                $conn->execute_query($sql, [$produto_id, $descricao, $ativo]);
                $_SESSION['message'] = "Roteiro cadastrado com sucesso!";
                $_SESSION['message_type'] = "success";
                header("Location: index.php");
                exit();
            } catch (mysqli_sql_exception $e) {
                $_SESSION['message'] = "Erro ao cadastrar roteiro: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        }
    } else {
        $_SESSION['message'] = "Produto e Descrição são campos obrigatórios.";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: adicionar.php");
    exit();
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-plus-circle"></i> Adicionar Novo Roteiro</h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['message_type']); ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <form action="adicionar.php" method="POST">
        <div class="form-group full-width">
            <label for="produto_search">Produto (que ainda não possui roteiro):</label>
            <input type="text" id="produto_search" list="product_options" placeholder="Digite para buscar produto (código ou nome)" required>
            <datalist id="product_options"></datalist>
            <input type="hidden" id="produto_id_hidden" name="produto_id_hidden" required>
        </div>
        
        <div class="form-group full-width">
            <label for="descricao">Descrição do Roteiro</label>
            <input type="text" name="descricao" id="descricao" value="Roteiro Padrão" required>
        </div>
        
        <div class="form-group">
            <label for="ativo">Status</label>
            <select name="ativo" id="ativo" required>
                <option value="1" selected>Ativo</option>
                <option value="0">Inativo</option>
            </select>
        </div>

        <div class="form-group full-width">
            <button type="submit" class="button submit">Salvar Roteiro</button>
        </div>
    </form>
    <a href="index.php" class="back-link">Voltar para a lista</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const produtoSearchInput = document.getElementById('produto_search');
        const productOptionsDatalist = document.getElementById('product_options');
        const produtoIdHiddenInput = document.getElementById('produto_id_hidden');
        const baseUrl = '<?php echo BASE_URL; ?>'; 

        let debounceTimeout;

        produtoSearchInput.addEventListener('input', function() {
            produtoIdHiddenInput.value = '';
            const searchTerm = this.value;
            
            if (searchTerm.length < 2) { 
                productOptionsDatalist.innerHTML = ''; 
                return;
            }

            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                fetch(`${baseUrl}/modules/roteiros/ajax_get_produtos_sem_roteiro.php?term=${encodeURIComponent(searchTerm)}`) 
                    .then(response => response.json())
                    .then(data => {
                        productOptionsDatalist.innerHTML = ''; 
                        data.forEach(product => {
                            const option = document.createElement('option');
                            option.value = `${product.nome} (${product.codigo})`;
                            option.setAttribute('data-id', product.id);
                            productOptionsDatalist.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Erro ao buscar produtos:', error));
            }, 300); 
        });

        produtoSearchInput.addEventListener('change', function() {
            const selectedOption = Array.from(productOptionsDatalist.options).find(option => option.value === this.value);
            if (selectedOption) {
                produtoIdHiddenInput.value = selectedOption.getAttribute('data-id');
            }
        });
    });
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
ob_end_flush();
?>
