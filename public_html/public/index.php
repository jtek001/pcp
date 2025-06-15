<?php
// public/index.php
// Este é o ponto de entrada principal do sistema.
// Ele inclui o cabeçalho, exibe o conteúdo da página inicial e o rodapé.

// Inicia a sessão (se já não estiver iniciada) - CRÍTICO para mensagens de sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclui o arquivo de configuração que define BASE_URL e outras funes
require_once '../config/database.php';

// Define o módulo atual como 'home' para o destaque no menu
$_GET['module'] = 'home'; // Usado no header para destacar o link "Início"

// Inclui o cabeçalho padrão
require_once '../includes/header.php';

// Conecta ao banco de dados
$conn = connectDB();

// Variáveis para mensagens de sucesso/erro
$message = '';
$message_type = '';

// Recupera mensagens da sesso (para login bem-sucedido, por exemplo)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    // Limpa as mensagens da sessão
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
} elseif (isset($_GET['message'])) { // OU recupera mensagens passadas via GET (ex: após logout)
    $message = sanitizeInput($_GET['message']);
    $message_type = sanitizeInput($_GET['type'] ?? 'info'); // 'info' como padrão se tipo não for especificado
}


// --- Lógica para buscar os Indicadores ---
$op_ativas_count = 0;
$total_programado_ops_ativas = 0.00;
$total_apontado_ops_ativas = 0.00;
$produtos_abaixo_minimo_count = 0;
$maquinas_operacionais_count = 0;
$maquinas_paradas_count = 0;
$producao_diaria_maquina_produto = []; // Dados para o gráfico

// Total de OPs Ativas (pendente ou em_producao) e Quantidade Programada/Apontada
$sql_ops_ativas = "SELECT 
                        COUNT(op.id) AS op_ativas_count,
                        SUM(op.quantidade_produzir) AS total_programado,
                        SUM(COALESCE(ap.quantidade_produzida, 0)) AS total_apontado
                    FROM 
                        ordens_producao op
                    LEFT JOIN 
                        apontamentos_producao ap ON op.id = ap.ordem_producao_id AND ap.deleted_at IS NULL
                    WHERE 
                        op.deleted_at IS NULL 
                        AND op.status IN ('pendente', 'em_producao')
                    GROUP BY NULL"; 

try {
    $result_ops_ativas = $conn->execute_query($sql_ops_ativas);
    if ($result_ops_ativas) {
        $row = $result_ops_ativas->fetch_assoc();
        $op_ativas_count = $row['op_ativas_count'] ?? 0;
        $total_programado_ops_ativas = $row['total_programado'] ?? 0.00;
        $total_apontado_ops_ativas = $row['total_apontado'] ?? 0.00;
        $result_ops_ativas->free();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro ao buscar indicadores de OPs ativas: " . $e->getMessage());
}

// Produtos Abaixo do Estoque Mínimo
$sql_abaixo_minimo = "SELECT COUNT(id) AS count FROM produtos WHERE estoque_atual < estoque_minimo AND deleted_at IS NULL";
try {
    $result_abaixo_minimo = $conn->execute_query($sql_abaixo_minimo);
    if ($result_abaixo_minimo) {
        $row = $result_abaixo_minimo->fetch_assoc();
        $produtos_abaixo_minimo_count = $row['count'] ?? 0;
        $result_abaixo_minimo->free();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro ao buscar produtos abaixo do mínimo: " . $e->getMessage());
}

// Máquinas Operacionais e Paradas
$sql_maquinas_status = "SELECT 
                            SUM(CASE WHEN status = 'operacional' THEN 1 ELSE 0 END) AS operacional_count,
                            SUM(CASE WHEN status IN ('manutencao', 'parada') THEN 1 ELSE 0 END) AS paradas_count
                        FROM 
                            maquinas 
                        WHERE 
                            deleted_at IS NULL";
try {
    $result_maquinas_status = $conn->execute_query($sql_maquinas_status);
    if ($result_maquinas_status) {
        $row = $result_maquinas_status->fetch_assoc();
        $maquinas_operacionais_count = $row['operacional_count'] ?? 0;
        $maquinas_paradas_count = $row['paradas_count'] ?? 0;
        $result_maquinas_status->free();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro ao buscar status de máquinas: " . $e->getMessage());
}

// Dados para Grfico de Produção Diária
$sql_producao_diaria = "SELECT 
                            m.nome AS maquina_nome,
                            p.nome AS produto_nome,
                            SUM(ap.quantidade_produzida) AS total_produzido
                        FROM
                            apontamentos_producao ap
                        JOIN
                            maquinas m ON ap.maquina_id = m.id
                        JOIN
                            ordens_producao op ON ap.ordem_producao_id = op.id
                        JOIN
                            produtos p ON op.produto_id = p.id
                        WHERE
                            ap.deleted_at IS NULL
                            AND DATE(ap.data_apontamento) = CURDATE() 
                        GROUP BY
                            m.nome, p.nome
                        ORDER BY
                            total_produzido DESC";
try {
    $result_producao_diaria = $conn->query($sql_producao_diaria); 
    if ($result_producao_diaria) {
        while ($row = $result_producao_diaria->fetch_assoc()) {
            $producao_diaria_maquina_produto[] = $row;
        }
        $result_producao_diaria->free();
    } else {
        error_log("Erro ao buscar produção diária para gráfico: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao buscar produção diária para grfico: " . $e->getMessage());
}

// --- INÍCIO DA ALTERAÇÃO: Resumo das Ordens de Produção (SEM Paginação) (09/06/2025 - IA) ---
// Buscar dados para a Tabela de Resumo de OPs (sem paginação aqui, apenas as OPs ativas)
$ordens_producao_resumo = [];
$sql_fetch_ops_summary = "SELECT 
                            op.id, op.numero_op, op.numero_pedido, 
                            p.nome AS produto_nome, 
                            op.quantidade_produzir, 
                            op.data_emissao, op.status, 
                            SUM(COALESCE(ap.quantidade_produzida, 0)) AS quantidade_apontada 
                        FROM 
                            ordens_producao op 
                        JOIN 
                            produtos p ON op.produto_id = p.id 
                        LEFT JOIN 
                            apontamentos_producao ap ON op.id = ap.ordem_producao_id AND ap.deleted_at IS NULL
                        WHERE 
                            op.deleted_at IS NULL 
                        GROUP BY op.id 
                        ORDER BY op.id DESC"; // Removido LIMIT e OFFSET
try {
    $stmt_fetch_ops_summary = $conn->execute_query($sql_fetch_ops_summary); // Removido parâmetros de paginaão
    if ($stmt_fetch_ops_summary) {
        while ($row = $stmt_fetch_ops_summary->fetch_assoc()) {
            $ordens_producao_resumo[] = $row;
        }
        $stmt_fetch_ops_summary->free();
    } else {
        error_log("Erro ao carregar resumo de OPs: " . $conn->error);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro fatal ao carregar resumo de OPs: " . $e->getMessage());
}
// --- FIM DA ALTERAÇÃO: Resumo das Ordens de Produção ---


// Fecha a conexão com o banco de dados
$conn->close();
?>

<h1>Bem-vindo ao Sistema de PCP</h1>
<p style="text-align: center; margin-bottom: 30px;">
    Utilize o menu acima para navegar pelas funcionalidades do sistema.
    Este é um sistema de Planejamento e Controle da Produção (PCP) básico,
    projetado para ser modular e fácil de expandir.
</p>

<?php if ($message): // Exibe a mensagem de feedback (sucesso ou erro) ?>
    <div class="message <?php echo htmlspecialchars($message_type); ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<h2>Indicadores Atuais do PCP</h2>
<div class="indicators-container">
    <div class="indicator-card">
        <h3>OPs Ativas</h3>
        <p class="indicator-number"><?php echo $op_ativas_count; ?></p>
        <p class="indicator-description">Ordens de Produção Pendentes ou Em Produço</p>
    </div>

    <div class="indicator-card">
        <h3>Qtd. Programada (OPs Ativas)</h3>
        <p class="indicator-number"><?php echo number_format($total_programado_ops_ativas, 2, ',', '.'); ?></p>
        <p class="indicator-description">Total a produzir em OPs ativas</p>
    </div>

    <div class="indicator-card">
        <h3>Qtd. Apontada (OPs Ativas)</h3>
        <p class="indicator-number"><?php echo number_format($total_apontado_ops_ativas, 2, ',', '.'); ?></p>
        <p class="indicator-description">Total já produzido para OPs ativas</p>
    </div>

    <div class="indicator-card <?php echo ($produtos_abaixo_minimo_count > 0) ? 'alert' : ''; ?>">
        <h3>Produtos Abaixo do Mínimo</h3>
        <p class="indicator-number"><?php echo $produtos_abaixo_minimo_count; ?></p>
        <p class="indicator-description">Itens com estoque abaixo do nível de segurança</p>
    </div>

    <div class="indicator-card">
        <h3>Máquinas Operacionais</h3>
        <p class="indicator-number"><?php echo $maquinas_operacionais_count; ?></p>
        <p class="indicator-description">Máquinas prontas para produção</p>
    </div>

    <div class="indicator-card <?php echo ($maquinas_paradas_count > 0) ? 'alert' : ''; ?>">
        <h3>Máquinas Paradas</h3>
        <p class="indicator-number"><?php echo $maquinas_paradas_count; ?></p>
        <p class="indicator-description">Máquinas em manutenção ou paradas</p>
    </div>
</div>

<h2 style="margin-top: 50px;">Produção Diária por Máquina e Produto (Hoje)</h2>
<?php if (!empty($producao_diaria_maquina_produto)): ?>
    <div class="chart-container">
        <div id="daily-production-chart"></div>
    </div>
<?php else: ?>
    <p style="text-align: center; margin-top: 20px;">Nenhum apontamento de produção registrado hoje.</p>
<?php endif; ?>

<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartData = <?php echo json_encode($producao_diaria_maquina_produto); ?>;
        
        if (chartData.length === 0) {
            console.log("Nenhum dado de produção para exibir no gráfico.");
            return;
        }

        const margin = { top: 40, right: 20, bottom: 120, left: 60 };
        const width = document.getElementById('daily-production-chart').offsetWidth - margin.left - margin.right;
        const height = 400 - margin.top - margin.bottom;

        const svg = d3.select("#daily-production-chart")
            .append("svg")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
            .append("g")
            .attr("transform", `translate(${margin.left},${margin.top})`);

        // Escalas
        const x = d3.scaleBand()
            .range([0, width])
            .padding(0.2);

        const y = d3.scaleLinear()
            .range([height, 0]);

        // Define os domínios (valores min/max) das escalas
        x.domain(chartData.map(d => `${d.maquina_nome} - ${d.produto_nome}`));
        y.domain([0, d3.max(chartData, d => parseFloat(d.total_produzido)) * 1.1]);

        // Eixos
        svg.append("g")
            .attr("transform", `translate(0,${height})`)
            .call(d3.axisBottom(x))
            .selectAll("text")
            .attr("transform", "rotate(-45)")
            .style("text-anchor", "end")
            .style("font-size", "10px")
            .style("fill", "#555");

        svg.append("g")
            .call(d3.axisLeft(y))
            .selectAll("text")
            .style("font-size", "10px")
            .style("fill", "#555");

        // Barras
        svg.selectAll(".bar")
            .data(chartData)
            .enter().append("rect")
            .attr("class", "bar")
            .attr("x", d => x(`${d.maquina_nome} - ${d.produto_nome}`))
            .attr("y", d => y(parseFloat(d.total_produzido)))
            .attr("width", x.bandwidth())
            .attr("height", d => height - y(parseFloat(d.total_produzido)))
            .attr("fill", "#3498db");

        // Tooltip
        const tooltip = d3.select("body").append("div")
            .attr("class", "tooltip")
            .style("opacity", 0)
            .style("position", "absolute")
            .style("background-color", "white")
            .style("border", "1px solid #ccc")
            .style("padding", "8px")
            .style("border-radius", "8px")
            .style("pointer-events", "none")
            .style("font-size", "12px")
            .style("box-shadow", "0 2px 5px rgba(0,0,0,0.2)");

        svg.selectAll(".bar")
            .on("mouseover", function(event, d) {
                d3.select(this).attr("fill", "#2980b9");
                tooltip.transition()
                    .duration(200)
                    .style("opacity", .9);
                tooltip.html(`Máquina: <strong>${d.maquina_nome}</strong><br/>Produto: <strong>${d.produto_nome}</strong><br/>Produzido: <strong>${parseFloat(d.total_produzido).toFixed(2).replace('.', ',')}</strong>`)
                    .style("left", (event.pageX + 10) + "px")
                    .style("top", (event.pageY - 28) + "px");
            })
            .on("mouseout", function(event, d) {
                d3.select(this).attr("fill", "#3498db");
                tooltip.transition()
                    .duration(500)
                    .style("opacity", 0);
            });
    });
</script>

---
<h2 style="margin-top: 50px;">Resumo das Ordens de Produção</h2>
<?php if (!empty($ordens_producao_resumo)): ?>
    <table>
        <thead>
            <tr>
                <th>ORDEM</th>
                <th>Produto</th>
                <th>PROGRAMADO</th>
                <th>APONTADO</th>
                <th>Emissão</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordens_producao_resumo as $op): ?>
                <tr>
                    <td><?php echo htmlspecialchars($op['numero_op']); ?></td>
                    <td><?php echo htmlspecialchars($op['produto_nome']); ?></td>
                    <td><?php echo number_format($op['quantidade_produzir'], 2, ',', '.'); ?></td>
                    <td><?php echo number_format($op['quantidade_apontada'], 2, ',', '.'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($op['data_emissao'])); ?></td>
                    <td><?php echo htmlspecialchars($op['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php /* // Removida Paginação
    <div class="pagination" style="margin-top: 20px;">
        <?php
        $ops_summary_pagination_base_query = http_build_query(array_filter([
            'module' => 'home', 
        ]));

        if ($total_ops_summary_pages > 1) {
            if ($ops_summary_current_page > 1) {
                echo '<a href="?' . $ops_summary_pagination_base_query . '&ops_page=' . ($ops_summary_current_page - 1) . '" class="page-link">&laquo; Anterior</a>';
            }

            for ($i = 1; $i <= $total_ops_summary_pages; $i++) {
                $active_class = ($i == $ops_summary_current_page) ? 'active' : '';
                echo '<a href="?' . $ops_summary_pagination_base_query . '&ops_page=' . $i . '" class="page-link ' . $active_class . '">' . $i . '</a>';
            }

            if ($ops_summary_current_page < $total_ops_summary_pages) {
                echo '<a href="?' . $ops_summary_pagination_base_query . '&ops_page=' . ($ops_summary_current_page + 1) . '" class="page-link">Próxima &raquo;</a>';
            }
        }
        ?>
    </div>
    */ ?>

<?php else: ?>
    <p style="text-align: center; margin-top: 20px;">Nenhuma Ordem de Produção ativa encontrada para o resumo.</p>
<?php endif; ?>

<?php
// Inclui o rodapé padrão
require_once '../includes/footer.php';
?>
