<?php
// modules/maquinas/editar.php
// Esta página contém o formulário para editar uma máquina existente.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração e o cabeçalho
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variáveis para mensagens de sucesso/erro
$message = '';
$message_type = '';
$maquina_data = null; // Para armazenar os dados da máquina a ser editada

// Função para buscar valores únicos de uma coluna em uma tabela específica
function getDistinctValues($conn, $table_name, $column_name) {
    $values = [];
    $sql = "SELECT DISTINCT " . $column_name . " FROM " . $table_name . " WHERE " . $column_name . " IS NOT NULL AND " . $column_name . " != '' ORDER BY " . $column_name . " ASC";
    
    // DEBUG: Imprime a query SQL sendo executada
    echo "<!-- DEBUG SQL in getDistinctValues: " . htmlspecialchars($sql) . " -->\n";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $values[] = $row[$column_name];
        }
    } else {
        error_log("Erro ao buscar valores para lookup (Tabela: " . $table_name . ", Coluna: " . $column_name . "): " . $conn->error);
    }
    return $values;
}

// Busca valores únicos para os dropdowns (incluindo localizações)
$localizacoes = getDistinctValues($conn, 'localizacoes_lookup', 'nome');


// Pega o ID da máquina da URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Se o ID for inválido, redireciona de volta para a lista
if ($id <= 0) {
    header("Location: " . BASE_URL . "/modules/maquinas/index.php?message=" . urlencode("ID da máquina inválido para edição.") . "&type=error");
    exit();
}

// --- Lógica para buscar os dados da máquina para preencher o formulário ---
$sql_select = "SELECT * FROM maquinas WHERE id = ?";
$stmt_select = $conn->prepare($sql_select);

if ($stmt_select) {
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();

    if ($result_select->num_rows > 0) {
        $maquina_data = $result_select->fetch_assoc();
    } else {
        $message = "Máquina não encontrada para edição.";
        $message_type = "error";
    }
    $stmt_select->close();
} else {
    $message = "Erro na preparação da consulta de seleção: " . $conn->error;
    $message_type = "error";
}

// --- Lógica para processar a submissão do formulário de edição ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    // Sanitiza e valida as entradas (com valores padrão para garantir não-NULL)
    $temp_nome = sanitizeInput(isset($_POST['nome']) ? $_POST['nome'] : '');
    $temp_descricao = sanitizeInput(isset($_POST['descricao']) ? $_POST['descricao'] : '');
    $temp_status = sanitizeInput(isset($_POST['status']) ? $_POST['status'] : 'operacional'); 
    $temp_numero_serie = sanitizeInput(isset($_POST['numero_serie']) ? $_POST['numero_serie'] : '');
    // Campo tag_ativo não é alterado no POST se for readonly. Pega-se o valor original do DB.
    $temp_tag_ativo = sanitizeInput($maquina_data['tag_ativo'] ?? ''); 
    $temp_fabricante = sanitizeInput(isset($_POST['fabricante']) ? $_POST['fabricante'] : '');
    $temp_modelo_maquina = sanitizeInput(isset($_POST['modelo_maquina']) ? $_POST['modelo_maquina'] : '');
    $temp_tipo_maquina = sanitizeInput(isset($_POST['tipo_maquina']) ? $_POST['tipo_maquina'] : '');
    $temp_localizacao = sanitizeInput(isset($_POST['localizacao']) ? $_POST['localizacao'] : ''); // Campo de localização
    $temp_data_aquisicao = isset($_POST['data_aquisicao']) && $_POST['data_aquisicao'] !== '' ? sanitizeInput($_POST['data_aquisicao']) : NULL;
    $temp_data_ultima_manutencao = isset($_POST['data_ultima_manutencao']) && $_POST['data_ultima_manutencao'] !== '' ? sanitizeInput($_POST['data_ultima_manutencao']) : NULL;
    $temp_capacidade_hora = (float) sanitizeInput(isset($_POST['capacidade_hora']) ? $_POST['capacidade_hora'] : 0.0);
    $temp_unidade_capacidade = sanitizeInput(isset($_POST['unidade_capacidade']) ? $_POST['unidade_capacidade'] : '');

    // Validação básica de campos obrigatórios
    if (empty($temp_nome) || empty($temp_status)) {
        $message = "Nome e Status são campos obrigatórios.";
        $message_type = "error";
    } else {
        // Insere/Atualiza os valores de lookup se forem novos na tabela localizacoes_lookup
        if (!empty($temp_localizacao)) {
            $sql_check_insert_location = "INSERT IGNORE INTO localizacoes_lookup (nome) VALUES (?)";
            try {
                $conn->execute_query($sql_check_insert_location, [$temp_localizacao]);
            } catch (mysqli_sql_exception $e) {
                error_log("Erro ao inserir lookup de localização: " . $e->getMessage());
            }
        }

        // Prepara a consulta SQL para atualização
        // O campo 'tag_ativo' é removido do UPDATE pois não deve ser alterado, assim como o ID.
        $sql_update = "UPDATE maquinas SET nome = ?, descricao = ?, status = ?, numero_serie = ?, fabricante = ?, modelo_maquina = ?, tipo_maquina = ?, localizacao = ?, data_aquisicao = ?, data_ultima_manutencao = ?, capacidade_hora = ?, unidade_capacidade = ? WHERE id = ?";
        
        // Array de parâmetros para execute_query - ATENÇÃO À ORDEM, 'tag_ativo' não está aqui.
        $params = [
            $temp_nome,
            $temp_descricao,
            $temp_status,
            $temp_numero_serie,
            $temp_fabricante,
            $temp_modelo_maquina,
            $temp_tipo_maquina,
            $temp_localizacao,
            $temp_data_aquisicao,
            $temp_data_ultima_manutencao,
            $temp_capacidade_hora,
            $temp_unidade_capacidade,
            $id
        ];

        try {
            $result_update = $conn->execute_query($sql_update, $params);

            if ($result_update === TRUE) {
                $message = "Máquina atualizada com sucesso!";
                $message_type = "success";
                // Atualiza os dados na variável $maquina_data para exibir os novos valores
                $maquina_data['nome'] = $temp_nome;
                $maquina_data['descricao'] = $temp_descricao;
                $maquina_data['status'] = $temp_status;
                $maquina_data['numero_serie'] = $temp_numero_serie;
                // $maquina_data['tag_ativo'] não é atualizado pois não vem do POST
                $maquina_data['fabricante'] = $temp_fabricante;
                $maquina_data['modelo_maquina'] = $temp_modelo_maquina;
                $maquina_data['tipo_maquina'] = $temp_tipo_maquina;
                $maquina_data['localizacao'] = $temp_localizacao;
                $maquina_data['data_aquisicao'] = $temp_data_aquisicao;
                $maquina_data['data_ultima_manutencao'] = $temp_data_ultima_manutencao;
                $maquina_data['capacidade_hora'] = $temp_capacidade_hora;
                $maquina_data['unidade_capacidade'] = $temp_unidade_capacidade;
            } else {
                $message = "Erro ao atualizar máquina: " . $conn->error;
                $message_type = "error";
                error_log("Erro ao atualizar máquina: " . $conn->error);
            }
        } catch (mysqli_sql_exception $e) {
            $message = "Erro ao atualizar máquina (SQL): " . $e->getMessage();
            $message_type = "error";
            error_log("Erro fatal ao atualizar máquina: " . $e->getMessage());
        }
    }
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];
?>

<h2>Editar Máquina</h2>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($maquina_data): ?>
    <form action="editar.php?id=<?php echo $maquina_data['id']; ?>" method="POST">
        <div class="form-group">
            <label for="nome">Nome da Máquina:</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($post_values['nome'] ?? $maquina_data['nome']); ?>" maxlength="100" required placeholder="Ex: CNC Fresa XYZ-3000">
        </div>

        <div class="form-group">
            <label for="status">Status:</label>
            <select id="status" name="status" required>
                <option value="operacional" <?php echo (($post_values['status'] ?? $maquina_data['status']) == 'operacional') ? 'selected' : ''; ?>>Operacional</option>
                <option value="manutencao" <?php echo (($post_values['status'] ?? $maquina_data['status']) == 'manutencao') ? 'selected' : ''; ?>>Manutenção</option>
                <option value="parada" <?php echo (($post_values['status'] ?? $maquina_data['status']) == 'parada') ? 'selected' : ''; ?>>Parada</option>
            </select>
        </div>

        <div class="form-group">
            <label for="numero_serie">Número de Série:</label>
            <input type="text" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($post_values['numero_serie'] ?? $maquina_data['numero_serie'] ?? ''); ?>" maxlength="50" placeholder="Ex: SN-123456789">
        </div>

        <div class="form-group">
            <label for="tag_ativo">Tag de Ativo:</label>
            <input type="text" id="tag_ativo" name="tag_ativo" value="<?php echo htmlspecialchars($post_values['tag_ativo'] ?? $maquina_data['tag_ativo'] ?? ''); ?>" maxlength="30" placeholder="Ex: MAQ-001" readonly>
        </div>

        <div class="form-group">
            <label for="fabricante">Fabricante:</label>
            <input type="text" id="fabricante" name="fabricante" value="<?php echo htmlspecialchars($post_values['fabricante'] ?? $maquina_data['fabricante'] ?? ''); ?>" maxlength="100" placeholder="Ex: Siemens">
        </div>

        <div class="form-group">
            <label for="modelo_maquina">Modelo da Máquina:</label>
            <input type="text" id="modelo_maquina" name="modelo_maquina" value="<?php echo htmlspecialchars($post_values['modelo_maquina'] ?? $maquina_data['modelo_maquina'] ?? ''); ?>" maxlength="50" placeholder="Ex: XYZ-PRO">
        </div>

        <div class="form-group">
            <label for="tipo_maquina">Tipo de Máquina:</label>
            <input type="text" id="tipo_maquina" name="tipo_maquina" value="<?php echo htmlspecialchars($post_values['tipo_maquina'] ?? $maquina_data['tipo_maquina'] ?? ''); ?>" maxlength="50" placeholder="Ex: Fresa CNC">
        </div>

        <div class="form-group">
            <label for="localizacao">Localização:</label>
            <select id="localizacao_select" name="localizacao" data-initial-value="<?php echo htmlspecialchars($post_values['localizacao'] ?? $maquina_data['localizacao'] ?? ''); ?>">
                <option value="">Selecione uma Localização</option>
                <?php
                $current_localizacao = $post_values['localizacao'] ?? $maquina_data['localizacao'];
                if (!empty($current_localizacao) && !in_array($current_localizacao, $localizacoes)) {
                    echo '<option value="' . htmlspecialchars($current_localizacao) . '" selected>' . htmlspecialchars($current_localizacao) . '</option>';
                }
                foreach ($localizacoes as $loc_opt) {
                    $selected = ($current_localizacao == $loc_opt) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($loc_opt) . '" ' . $selected . '>' . htmlspecialchars($loc_opt) . '</option>';
                }
                ?>
            </select>
            <input type="text" id="localizacao_text" style="display:none;" maxlength="100" placeholder="Digite uma nova Localização">
            <a href="#" class="toggle-link" onclick="toggleInput(event, 'localizacao'); return false;">Novo</a>
        </div>

        <div class="form-group">
            <label for="data_aquisicao">Data de Aquisição:</label>
            <input type="date" id="data_aquisicao" name="data_aquisicao" value="<?php echo htmlspecialchars($post_values['data_aquisicao'] ?? $maquina_data['data_aquisicao'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="data_ultima_manutencao">Última Manutenção:</label>
            <input type="date" id="data_ultima_manutencao" name="data_ultima_manutencao" value="<?php echo htmlspecialchars($post_values['data_ultima_manutencao'] ?? $maquina_data['data_ultima_manutencao'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="capacidade_hora">Capacidade por Hora:</label>
            <input type="number" id="capacidade_hora" name="capacidade_hora" step="0.01" value="<?php echo htmlspecialchars($post_values['capacidade_hora'] ?? $maquina_data['capacidade_hora'] ?? ''); ?>" placeholder="Ex: 150.50">
        </div>

        <div class="form-group">
            <label for="unidade_capacidade">Unidade Capacidade:</label>
            <input type="text" id="unidade_capacidade" name="unidade_capacidade" value="<?php echo htmlspecialchars($post_values['unidade_capacidade'] ?? $maquina_data['unidade_capacidade'] ?? ''); ?>" maxlength="20" placeholder="Ex: Peças/h">
        </div>

        <div class="form-group full-width">
            <label for="descricao">Descrição Detalhada:</label>
            <textarea id="descricao" name="descricao" placeholder="Detalhes sobre a máquina, especificações técnicas..."><?php echo htmlspecialchars($post_values['descricao'] ?? $maquina_data['descricao'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button submit">Atualizar Máquina</button>
    </form>
<?php else: ?>
    <p class="error" style="text-align: center;">Máquina não encontrada para edição.</p>
<?php endif; ?>

<a href="index.php" class="back-link">Voltar para a lista de máquinas</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Função para alternar entre select e input de texto (reutilizada de produtos)
        window.toggleInput = function(event, fieldName) {
            event.preventDefault(); // Previne o comportamento padrão do link (ir para #)

            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = event.target; // O link clicado

            if (selectElement.style.display === 'none') {
                // Ativar select
                selectElement.style.display = 'block';
                selectElement.setAttribute('name', fieldName); // Reativa o name para o select
                textInputElement.style.display = 'none';
                textInputElement.removeAttribute('name'); // Desativa o name para o input de texto
                textInputElement.value = ''; // Limpa o input de texto
                linkElement.textContent = 'Novo'; // Altera o texto do link
            } else {
                // Ativar input de texto
                selectElement.style.display = 'none';
                selectElement.removeAttribute('name'); // Desativa o name para o select
                textInputElement.style.display = 'block';
                textInputElement.setAttribute('name', fieldName); // Ativa o name para o input de texto
                textInputElement.focus();
                linkElement.textContent = 'Voltar'; // Altera o texto do link
            }
        };

        // Lógica para garantir que o campo correto seja submetido (select ou text input)
        document.querySelector('form').addEventListener('submit', function(event) {
            const fieldsToToggle = ['localizacao']; // Campos com toggle em máquinas
            fieldsToToggle.forEach(fieldName => {
                const selectElement = document.getElementById(fieldName + '_select');
                const textInputElement = document.getElementById(fieldName + '_text');

                // Garante que apenas o campo visível tenha o atributo 'name' para ser submetido
                if (textInputElement.style.display === 'block') {
                    selectElement.removeAttribute('name');
                    textInputElement.setAttribute('name', fieldName);
                } else {
                    textInputElement.removeAttribute('name');
                    selectElement.setAttribute('name', fieldName);
                }
            });
        });

        // Lógica para manter o estado do campo (select/input) após um POST com erro ou edição
        const fieldsToReconcile = ['localizacao'];
        fieldsToReconcile.forEach(fieldName => {
            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = textInputElement.nextElementSibling; // O link "Novo"
            
            // Determina o valor inicial a ser usado (do POST se houver erro, senão do PHP que preenche o select)
            const initialValue = selectElement.getAttribute('data-initial-value') || ''; 
            
            if (initialValue) {
                let foundInSelect = false;
                for (let i = 0; i < selectElement.options.length; i++) {
                    if (selectElement.options[i].value === initialValue) {
                        selectElement.value = initialValue;
                        foundInSelect = true;
                        break;
                    }
                }
                if (!foundInSelect) {
                    // Se o valor inicial não está nas opções do select, ativa o input de texto
                    selectElement.style.display = 'none';
                    selectElement.removeAttribute('name');
                    textInputElement.style.display = 'block';
                    textInputElement.setAttribute('name', fieldName);
                    textInputElement.value = initialValue;
                    linkElement.textContent = 'Voltar';
                } else {
                    // Se o valor foi encontrado no select, garante que o select esteja ativo
                    selectElement.style.display = 'block';
                    selectElement.setAttribute('name', fieldName);
                    textInputElement.style.display = 'none';
                    textInputElement.removeAttribute('name');
                    linkElement.textContent = 'Novo';
                }
            } else {
                // Se não há valor inicial, garante que o select esteja ativo e input de texto escondido
                selectElement.style.display = 'block';
                selectElement.setAttribute('name', fieldName);
                textInputElement.style.display = 'none';
                textInputElement.removeAttribute('name');
                linkElement.textContent = 'Novo';
            }
        });
    });
</script>

<?php
// Fecha a conexão com o banco de dados
$conn->close();
// Inclui o rodapé padrão
require_once __DIR__ . '/../../includes/footer.php';
?>
