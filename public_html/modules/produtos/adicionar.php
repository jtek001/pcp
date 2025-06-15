<?php
// modules/produtos/adicionar.php
// Esta página contém o formulário para adicionar um novo produto e a lógica de inserção.

// Habilita a exibição de todos os erros PHP para depuração (REMOVER EM PRODUÇÃO)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui os arquivos de configuração e o cabeçalho
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variáveis para mensagens de sucesso/erro - GARANTIDAS DE SEREM INICIALIZADAS
$message = '';
$message_type = '';

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
        // Loga erro se a query falhar
        error_log("Erro ao buscar valores para lookup (Tabela: " . $table_name . ", Coluna: " . $column_name . "): " . $conn->error);
    }
    return $values;
}

// Busca valores únicos para os dropdowns das tabelas de lookup
$grupos = getDistinctValues($conn, 'grupos_lookup', 'nome');
$subgrupos = getDistinctValues($conn, 'subgrupos_lookup', 'nome');
$modelos = getDistinctValues($conn, 'modelos_lookup', 'nome');
$acabamentos = getDistinctValues($conn, 'acabamentos_lookup', 'nome');
$familias = getDistinctValues($conn, 'familias_lookup', 'nome');
$unidades_medida = getDistinctValues($conn, 'unidades_medida_lookup', 'nome');


// Verifica se o formulário foi submetido via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitiza e valida as entradas do usuário, garantindo que nunca sejam NULL antes de serem passadas para a query
    $temp_nome = sanitizeInput(isset($_POST['nome']) ? $_POST['nome'] : '');
    $temp_codigo = sanitizeInput(isset($_POST['codigo']) ? $_POST['codigo'] : '');
    $temp_grupo = sanitizeInput(isset($_POST['grupo']) ? $_POST['grupo'] : '');
    $temp_subgrupo = sanitizeInput(isset($_POST['subgrupo']) ? $_POST['subgrupo'] : '');

    $temp_modelo = sanitizeInput(isset($_POST['modelo']) ? $_POST['modelo'] : '');
    $temp_acabamento = sanitizeInput(isset($_POST['acabamento']) ? $_POST['acabamento'] : '');
    $temp_familia = sanitizeInput(isset($_POST['familia']) ? $_POST['familia'] : '');
    $temp_desenho = sanitizeInput(isset($_POST['desenho']) ? $_POST['desenho'] : '');

    $temp_velocidade = (float) sanitizeInput(isset($_POST['velocidade']) ? $_POST['velocidade'] : 0.0);
    $temp_espessura = (float) sanitizeInput(isset($_POST['espessura']) ? $_POST['espessura'] : 0.0);
    $temp_largura = (float) sanitizeInput(isset($_POST['largura']) ? $_POST['largura'] : 0.0);
    $temp_perimetro_mm = (float) sanitizeInput(isset($_POST['perimetro_mm']) ? $_POST['perimetro_mm'] : 0.0);
    $temp_area_perfil_mm2 = (float) sanitizeInput(isset($_POST['area_perfil_mm2']) ? $_POST['area_perfil_mm2'] : 0.0);
    $temp_comprimento = (float) sanitizeInput(isset($_POST['comprimento']) ? $_POST['comprimento'] : 0.0);

    $temp_altura_embalagem = (int) sanitizeInput(isset($_POST['altura_embalagem']) ? $_POST['altura_embalagem'] : 0);
    $temp_largura_embalagem = (int) sanitizeInput(isset($_POST['largura_embalagem']) ? $_POST['largura_embalagem'] : 0);
    $temp_pecas_por_embalagem = (int) sanitizeInput(isset($_POST['pecas_por_embalagem']) ? $_POST['pecas_por_embalagem'] : 0);
    $temp_pecas_por_fardo = (int) sanitizeInput(isset($_POST['pecas_por_fardo']) ? $_POST['pecas_por_fardo'] : 0);

    $temp_codigo2 = sanitizeInput(isset($_POST['codigo2']) ? $_POST['codigo2'] : '');
    $temp_unidade_medida2 = sanitizeInput(isset($_POST['unidade_medida2']) ? $_POST['unidade_medida2'] : '');
    $temp_descricao = sanitizeInput(isset($_POST['descricao']) ? $_POST['descricao'] : '');
    $temp_unidade_medida = sanitizeInput(isset($_POST['unidade_medida']) ? $_POST['unidade_medida'] : ''); 
    
    $temp_estoque_minimo = (float) sanitizeInput(isset($_POST['estoque_minimo']) ? $_POST['estoque_minimo'] : 0.0);
    $temp_estoque_atual = (float) sanitizeInput(isset($_POST['estoque_atual']) ? $_POST['estoque_atual'] : 0.0);


    // Insere/Atualiza os valores de lookup se forem novos nas tabelas de lookup
    $lookup_fields_to_process = [
        'grupo' => ['value' => $temp_grupo, 'table' => 'grupos_lookup'],
        'subgrupo' => ['value' => $temp_subgrupo, 'table' => 'subgrupos_lookup'],
        'modelo' => ['value' => $temp_modelo, 'table' => 'modelos_lookup'],
        'acabamento' => ['value' => $temp_acabamento, 'table' => 'acabamentos_lookup'],
        'familia' => ['value' => $temp_familia, 'table' => 'familias_lookup'],
        'unidade_medida' => ['value' => $temp_unidade_medida, 'table' => 'unidades_medida_lookup'] 
    ];

    foreach ($lookup_fields_to_process as $field_name => $field_data) {
        $field_value = $field_data['value'];
        $lookup_table = $field_data['table']; 

        if (!empty($field_value)) {
            // Usando execute_query para inserções no lookup
            // execute_query é mais robusto e não precisa de prepare/bind/execute separado
            $sql_check_insert = "INSERT IGNORE INTO " . $lookup_table . " (nome) VALUES (?)";
            try {
                $conn->execute_query($sql_check_insert, [$field_value]);
            } catch (mysqli_sql_exception $e) {
                error_log("Erro ao inserir lookup em " . $lookup_table . ": " . $e->getMessage());
                // Remover die() para não parar a execução, apenas logar.
            }
        }
    }


    // Prepara a consulta SQL para inserção na tabela 'produtos'
    $sql_insert = "INSERT INTO produtos (nome, codigo, grupo, subgrupo, modelo, acabamento, familia, desenho, velocidade, espessura, largura, perimetro_mm, area_perfil_mm2, comprimento, altura_embalagem, largura_embalagem, pecas_por_embalagem, pecas_por_fardo, codigo2, unidade_medida2, descricao, unidade_medida, estoque_minimo, estoque_atual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // Array de parâmetros para execute_query, na ordem exata dos placeholders na query
    $params = [
        $temp_nome,
        $temp_codigo,
        $temp_grupo,
        $temp_subgrupo,
        $temp_modelo,
        $temp_acabamento,
        $temp_familia,
        $temp_desenho,
        $temp_velocidade,
        $temp_espessura,
        $temp_largura,
        $temp_perimetro_mm,
        $temp_area_perfil_mm2,
        $temp_comprimento,
        $temp_altura_embalagem,
        $temp_largura_embalagem,
        $temp_pecas_por_embalagem,
        $temp_pecas_por_fardo,
        $temp_codigo2,
        $temp_unidade_medida2,
        $temp_descricao,
        $temp_unidade_medida,
        $temp_estoque_minimo,
        $temp_estoque_atual
    ];

    // Executa a consulta usando execute_query()
    try {
        $result_insert = $conn->execute_query($sql_insert, $params);

        if ($result_insert === TRUE) {
            $message = "Produto cadastrado com sucesso!";
            $message_type = "success";
            // Limpa os campos do formulário para um novo cadastro
            $_POST = array();
        } else {
            // Este bloco é para erros que não são exceções (ex: false retornado)
            $message = "Erro ao cadastrar produto: " . $conn->error;
            $message_type = "error";
            error_log("Erro ao inserir produto: " . $conn->error);
        }
    } catch (mysqli_sql_exception $e) {
        $message = "Erro ao cadastrar produto (SQL): " . $e->getMessage();
        $message_type = "error";
        error_log("Erro fatal ao inserir produto: " . $e->getMessage());
    }
}

// Geração de código aleatório único para o campo 'codigo'
$random_codigo = '';
// Apenas gera novo código se a página é carregada via GET ou se um POST falhou (para re-preencher o form)
if ($_SERVER['REQUEST_METHOD'] === 'GET' || (isset($message_type) && $message_type == 'error')) {
    do {
        // Gera um número aleatório de 12 dígitos
        $generated_code = str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);

        // Verifica se o código já existe no banco de dados
        // Usando execute_query para verificação de unicidade
        $sql_check_unique = "SELECT COUNT(*) FROM produtos WHERE codigo = ?";
        $result_check_unique = $conn->execute_query($sql_check_unique, [$generated_code]);
        
        $count = 0;
        if ($result_check_unique) {
            $row = $result_check_unique->fetch_row();
            $count = $row[0];
            $result_check_unique->free(); // Libera o resultado
        } else {
            // Se execute_query falhar aqui (raro), assume que é duplicado para forçar nova geração
            error_log("Erro ao verificar unicidade do código: " . $conn->error);
            $count = 1; 
        }
    } while ($count > 0); // Repete enquanto o código gerado já existir
    $random_codigo = $generated_code;
}

// Se o formulário foi submetido e falhou, re-preenche os campos com os valores POSTed
$post_values = $_POST ?? [];

?>

<h2>Adicionar Novo Produto</h2>

<?php if ($message): ?>
    <!-- Exibe a mensagem de feedback (sucesso ou erro) -->
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<!-- Formulário para adicionar um produto -->
<form action="adicionar.php" method="POST">
    <div class="form-group">
        <label for="nome">Nome do Produto:</label>
        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($post_values['nome'] ?? ''); ?>" maxlength="40" required placeholder="Ex: Parafuso Philips">
    </div>

    <div class="form-group">
        <label for="codigo">Código:</label>
        <input type="text" id="codigo" name="codigo" value="<?php echo htmlspecialchars($post_values['codigo'] ?? $random_codigo); ?>" maxlength="20" required readonly placeholder="Gerado automaticamente">
    </div>

    <div class="form-group">
        <label for="grupo">Grupo:</label>
        <select id="grupo_select" name="grupo" required data-initial-value="<?php echo htmlspecialchars($post_values['grupo'] ?? ''); ?>">
            <option value="">Selecione um Grupo</option>
            <?php
            // Adiciona a opção digitada anteriormente se houver erro e a lista não contiver
            $current_grupo = $post_values['grupo'] ?? '';
            if (!empty($current_grupo) && !in_array($current_grupo, $grupos)) {
                echo '<option value="' . htmlspecialchars($current_grupo) . '" selected>' . htmlspecialchars($current_grupo) . '</option>';
            }
            foreach ($grupos as $grupo_opt) {
                $selected = ($current_grupo == $grupo_opt) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($grupo_opt) . '" ' . $selected . '>' . htmlspecialchars($grupo_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="grupo_text" style="display:none;" maxlength="20" placeholder="Digite um novo Grupo">
        <a href="#" class="toggle-link" onclick="toggleInput(event, 'grupo'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="subgrupo">Subgrupo:</label>
        <select id="subgrupo_select" name="subgrupo" required data-initial-value="<?php echo htmlspecialchars($post_values['subgrupo'] ?? ''); ?>">
            <option value="">Selecione um Subgrupo</option>
            <?php
            $current_subgrupo = $post_values['subgrupo'] ?? '';
            if (!empty($current_subgrupo) && !in_array($current_subgrupo, $subgrupos)) {
                echo '<option value="' . htmlspecialchars($current_subgrupo) . '" selected>' . htmlspecialchars($current_subgrupo) . '</option>';
            }
            foreach ($subgrupos as $subgrupo_opt) {
                $selected = ($current_subgrupo == $subgrupo_opt) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($subgrupo_opt) . '" ' . $selected . '>' . htmlspecialchars($subgrupo_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="subgrupo_text" style="display:none;" maxlength="20" placeholder="Digite um novo Subgrupo">
        <a href="#" class="toggle-link" onclick="toggleInput(event, 'subgrupo'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="modelo">Modelo:</label>
        <select id="modelo_select" name="modelo" data-initial-value="<?php echo htmlspecialchars($post_values['modelo'] ?? ''); ?>">
            <option value="">Nenhum/Selecione um Modelo</option>
            <?php
            $current_modelo = $post_values['modelo'] ?? '';
            if (!empty($current_modelo) && !in_array($current_modelo, $modelos)) {
                echo '<option value="' . htmlspecialchars($current_modelo) . '" selected>' . htmlspecialchars($current_modelo) . '</option>';
            }
            foreach ($modelos as $modelo_opt) {
                $selected = ($current_modelo == $modelo_opt) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($modelo_opt) . '" ' . $selected . '>' . htmlspecialchars($modelo_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="modelo_text" style="display:none;" maxlength="20" placeholder="Digite um novo Modelo">
        <a href="#" class="toggle-link" onclick="toggleInput(event, 'modelo'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="acabamento">Acabamento:</label>
        <select id="acabamento_select" name="acabamento" data-initial-value="<?php echo htmlspecialchars($post_values['acabamento'] ?? ''); ?>">
            <option value="">Nenhum/Selecione um Acabamento</option>
            <?php
            $current_acabamento = $post_values['acabamento'] ?? '';
            if (!empty($current_acabamento) && !in_array($current_acabamento, $acabamentos)) {
                echo '<option value="' . htmlspecialchars($current_acabamento) . '" selected>' . htmlspecialchars($current_acabamento) . '</option>';
            }
            foreach ($acabamentos as $acabamento_opt) {
                $selected = ($current_acabamento == $acabamento_opt) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($acabamento_opt) . '" ' . $selected . '>' . htmlspecialchars($acabamento_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="acabamento_text" style="display:none;" maxlength="20" placeholder="Digite um novo Acabamento">
        <a href="#" class="toggle-link" onclick="toggleInput(event, 'acabamento'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="familia">Família:</label>
        <select id="familia_select" name="familia" data-initial-value="<?php echo htmlspecialchars($post_values['familia'] ?? ''); ?>">
            <option value="">Nenhum/Selecione uma Família</option>
            <?php
            $current_familia = $post_values['familia'] ?? '';
            if (!empty($current_familia) && !in_array($current_familia, $familias)) {
                echo '<option value="' . htmlspecialchars($current_familia) . '" selected>' . htmlspecialchars($current_familia) . '</option>';
            }
            foreach ($familias as $familia_opt) {
                $selected = ($current_familia == $familia_opt) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($familia_opt) . '" ' . $selected . '>' . htmlspecialchars($familia_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="familia_text" style="display:none;" maxlength="20" placeholder="Digite uma nova Família">
        <a href="#" onclick="toggleInput(event, 'familia'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="desenho">Desenho:</label>
        <input type="text" id="desenho" name="desenho" value="<?php echo htmlspecialchars($post_values['desenho'] ?? ''); ?>" maxlength="20" placeholder="Ex: D-12345">
    </div>

    <div class="form-group">
        <label for="velocidade">Velocidade:</label>
        <input type="number" id="velocidade" name="velocidade" step="0.01" value="<?php echo htmlspecialchars($post_values['velocidade'] ?? '0.00'); ?>" required placeholder="Ex: 1500.00">
    </div>

    <div class="form-group">
        <label for="espessura">Espessura:</label>
        <input type="number" id="espessura" name="espessura" step="0.01" value="<?php echo htmlspecialchars($post_values['espessura'] ?? '0.00'); ?>" required placeholder="Ex: 2.50">
    </div>

    <div class="form-group">
        <label for="largura">Largura:</label>
        <input type="number" id="largura" name="largura" step="0.01" value="<?php echo htmlspecialchars($post_values['largura'] ?? '0.00'); ?>" required placeholder="Ex: 10.00">
    </div>

    <div class="form-group">
        <label for="perimetro_mm">Perímetro (mm):</label>
        <input type="number" id="perimetro_mm" name="perimetro_mm" step="0.01" value="<?php echo htmlspecialchars($post_values['perimetro_mm'] ?? ''); ?>" placeholder="Ex: 50.00">
    </div>

    <div class="form-group">
        <label for="area_perfil_mm2">Área de Perfil (mm²):</label>
        <input type="number" id="area_perfil_mm2" name="area_perfil_mm2" step="0.01" value="<?php echo htmlspecialchars($post_values['area_perfil_mm2'] ?? ''); ?>" placeholder="Ex: 25.00">
    </div>

    <div class="form-group">
        <label for="comprimento">Comprimento:</label>
        <input type="number" id="comprimento" name="comprimento" step="0.01" value="<?php echo htmlspecialchars($post_values['comprimento'] ?? ''); ?>" placeholder="Ex: 100.00">
    </div>

    <div class="form-group">
        <label for="altura_embalagem">Altura Embalagem:</label>
        <input type="number" id="altura_embalagem" name="altura_embalagem" step="1" value="<?php echo htmlspecialchars($post_values['altura_embalagem'] ?? ''); ?>" placeholder="Ex: 20">
    </div>

    <div class="form-group">
        <label for="largura_embalagem">Largura Embalagem:</label>
        <input type="number" id="largura_embalagem" name="largura_embalagem" step="1" value="<?php echo htmlspecialchars($post_values['largura_embalagem'] ?? ''); ?>" placeholder="Ex: 15">
    </div>

    <div class="form-group">
        <label for="pecas_por_embalagem">Peças por Embalagem:</label>
        <input type="number" id="pecas_por_embalagem" name="pecas_por_embalagem" step="1" readonly value="<?php echo htmlspecialchars($post_values['pecas_por_embalagem'] ?? ''); ?>" placeholder="Calculado automaticamente">
    </div>

    <div class="form-group">
        <label for="pecas_por_fardo">Peças por Fardo:</label>
        <input type="number" id="pecas_por_fardo" name="pecas_por_fardo" step="1" value="<?php echo htmlspecialchars($post_values['pecas_por_fardo'] ?? ''); ?>" placeholder="Ex: 500">
    </div>

    <div class="form-group">
        <label for="codigo2">Código 2:</label>
        <input type="text" id="codigo2" name="codigo2" value="<?php echo htmlspecialchars($post_values['codigo2'] ?? ''); ?>" maxlength="20" placeholder="Ex: CÓDIGO_ALT">
    </div>

    <div class="form-group">
        <label for="unidade_medida2">Unidade de Medida 2:</label>
        <input type="text" id="unidade_medida2" name="unidade_medida2" maxlength="5" value="<?php echo htmlspecialchars($post_values['unidade_medida2'] ?? ''); ?>" placeholder="Ex: PC">
    </div>

    <div class="form-group full-width">
        <label for="descricao">Descrição:</label>
        <textarea id="descricao" name="descricao" placeholder="Detalhes adicionais do produto" maxlength="40" required><?php echo htmlspecialchars($post_values['descricao'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
        <label for="unidade_medida">Unidade de Medida:</label>
        <select id="unidade_medida_select" name="unidade_medida" required data-initial-value="<?php echo htmlspecialchars($post_values['unidade_medida'] ?? ''); ?>">
            <option value="">Selecione uma Unidade</option>
            <?php
            $current_unidade_medida = $post_values['unidade_medida'] ?? '';
            if (!empty($current_unidade_medida) && !in_array($current_unidade_medida, $unidades_medida)) {
                echo '<option value="' . htmlspecialchars($current_unidade_medida) . '" selected>' . htmlspecialchars($current_unidade_medida) . '</option>';
            }
            foreach ($unidades_medida as $unidade_opt) {
                $selected = ($current_unidade_medida == $unidade_opt) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($unidade_opt) . '" ' . $selected . '>' . htmlspecialchars($unidade_opt) . '</option>';
            }
            ?>
        </select>
        <input type="text" id="unidade_medida_text" style="display:none;" maxlength="10" placeholder="Digite uma nova Unidade de Medida">
        <a href="#" onclick="toggleInput(event, 'unidade_medida'); return false;">Novo</a>
    </div>

    <div class="form-group">
        <label for="estoque_minimo">Estoque Mínimo:</label>
        <input type="number" id="estoque_minimo" name="estoque_minimo" step="0.01" value="<?php echo htmlspecialchars($post_values['estoque_minimo'] ?? '0.00'); ?>" placeholder="0.00">
    </div>

    <div class="form-group">
        <label for="estoque_atual">Estoque Atual:</label>
        <input type="number" id="estoque_atual" name="estoque_atual" step="0.01" value="<?php echo htmlspecialchars($post_values['estoque_atual'] ?? '0.00'); ?>" placeholder="0.00" readonly>
    </div>

    <button type="submit" class="button submit">Cadastrar Produto</button>
</form>

<!-- Link para voltar para a lista de produtos -->
<a href="index.php" class="back-link">Voltar para a lista de produtos</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alturaEmbalagemInput = document.getElementById('altura_embalagem');
        const larguraEmbalagemInput = document.getElementById('largura_embalagem');
        const pecasPorEmbalagemInput = document.getElementById('pecas_por_embalagem');

        function calculatePecasPorEmbalagem() {
            const altura = parseFloat(alturaEmbalagemInput.value) || 0;
            const largura = parseFloat(larguraEmbalagemInput.value) || 0;
            const resultado = altura * largura;
            pecasPorEmbalagemInput.value = resultado > 0 ? resultado.toFixed(0) : '';
        }

        alturaEmbalagemInput.addEventListener('input', calculatePecasPorEmbalagem);
        larguraEmbalagemInput.addEventListener('input', calculatePecasPorEmbalagem);
        calculatePecasPorEmbalagem(); // Initial calculation

        // Função para alternar entre select e input de texto
        window.toggleInput = function(event, fieldName) {
            event.preventDefault(); // Previne o comportamento padrão do link (ir para #)

            const selectElement = document.getElementById(fieldName + '_select');
            const textInputElement = document.getElementById(fieldName + '_text');
            const linkElement = event.target; // O link clicado

            if (selectElement.style.display === 'none') {
                // Ativar select (se estava no modo input de texto)
                selectElement.style.display = 'block';
                selectElement.setAttribute('name', fieldName); // Reativa o name para o select
                textInputElement.style.display = 'none';
                textInputElement.removeAttribute('name'); // Desativa o name para o input de texto
                textInputElement.value = ''; // Limpa o input de texto
                linkElement.textContent = 'Novo'; // Altera o texto do link
            } else {
                // Ativar input de texto (se estava no modo select)
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
            const fieldsToToggle = ['grupo', 'subgrupo', 'modelo', 'acabamento', 'familia', 'unidade_medida'];
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
        const fieldsToReconcile = ['grupo', 'subgrupo', 'modelo', 'acabamento', 'familia', 'unidade_medida'];
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
