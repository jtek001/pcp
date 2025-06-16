<?php
// /public/index.php - Página principal (Dashboard)

// Inicia a sessão para verificar o login do usuário
session_start();

// Define o fuso horário para garantir a consistência das datas
date_default_timezone_set('America/Sao_Paulo');

// --- OBSERVAÇÃO: VERIFICAÇÃO DE CAMINHOS ---
// Adicionada uma verificação para garantir que os ficheiros de inclusão são encontrados.
// Se houver um erro, ele será exibido de forma clara.

$config_path = __DIR__ . '/../config/database.php';
$header_path = __DIR__ . '/../includes/header.php';

if (!file_exists($config_path)) {
    die("Erro Crítico: O ficheiro de configuração (database.php) não foi encontrado. Caminho verificado: " . $config_path);
}
require_once $config_path;

if (!file_exists($header_path)) {
    die("Erro Crítico: O ficheiro de cabeçalho (header.php) não foi encontrado. Caminho verificado: " . $header_path);
}

// Conecta ao banco de dados
$conn = connectDB();

// A lógica de login e o cabeçalho serão carregados após a conexão
require_once $header_path;


// --- Lógica para os Indicadores (KPIs) ---
// Total de OPs Ativas (Pendentes ou Em Produção)
$sql_ops_ativas = "SELECT COUNT(id) as total FROM ordens_producao WHERE status IN ('pendente', 'em_producao') AND deleted_at IS NULL";
$total_ops_ativas = $conn->query($sql_ops_ativas)->fetch_assoc()['total'] ?? 0;

// Máquinas operacionais e paradas
$sql_maquinas_status = "SELECT status, COUNT(id) as total FROM maquinas WHERE deleted_at IS NULL GROUP BY status";
$maquinas_status_result = $conn->query($sql_maquinas_status)->fetch_all(MYSQLI_ASSOC);
$total_maquinas_operacionais = 0;
$total_maquinas_paradas = 0;
foreach ($maquinas_status_result as $status) {
    if ($status['status'] === 'operacional') {
        $total_maquinas_operacionais = $status['total'];
    } elseif ($status['status'] === 'parada' || $status['status'] === 'manutencao') {
        $total_maquinas_paradas += $status['total'];
    }
}


// --- Lógica para o Gráfico de Produção dos Últimos 7 Dias ---
$dados_grafico = [];
$hoje = new DateTime();
$uma_semana_atras = (new DateTime())->sub(new DateInterval('P6D')); // Pega os últimos 7 dias incluindo hoje

// Prepara um array com os últimos 7 dias para garantir que todos apareçam no gráfico
$periodo_dias = new DatePeriod($uma_semana_atras, new DateInterval('P1D'), $hoje->add(new DateInterval('P1D')));
$dados_agregados = [];
foreach ($periodo_dias as $dia) {
    $dados_agregados[$dia->format('Y-m-d')] = ['PC' => 0, 'M3' => 0];
}

// ALTERAÇÃO: Adicionado filtro "p.acabamento = 'Acabado'" para buscar apenas produtos acabados.
$sql_producao_semanal = "SELECT 
                            DATE(ap.data_apontamento) as dia,
                            SUM(CASE WHEN UPPER(p.unidade_medida) = 'PC' THEN ap.quantidade_produzida ELSE 0 END) AS total_quantidade_pc,
                            SUM(CASE WHEN UPPER(p.unidade_medida) = 'M3' OR UPPER(p.unidade_medida2) = 'M3' THEN calcularVolume(ap.quantidade_produzida, p.espessura, p.largura, p.comprimento) ELSE 0 END) AS total_volume_m3
                         FROM apontamentos_producao ap
                         JOIN ordens_producao op ON ap.ordem_producao_id = op.id
                         JOIN produtos p ON op.produto_id = p.id
                         WHERE ap.data_apontamento >= ? 
                           AND p.acabamento = 'Acabado'
                           AND ap.deleted_at IS NULL
                         GROUP BY DATE(ap.data_apontamento)";

$stmt_producao = $conn->prepare($sql_producao_semanal);
$data_inicio_busca = $uma_semana_atras->format('Y-m-d');
$stmt_producao->bind_param("s", $data_inicio_busca);
$stmt_producao->execute();
$result_producao = $stmt_producao->get_result();

// Preenche o array com os dados do banco
while ($row = $result_producao->fetch_assoc()) {
    if (isset($dados_agregados[$row['dia']])) {
        $dados_agregados[$row['dia']]['PC'] = (float)$row['total_quantidade_pc'];
        $dados_agregados[$row['dia']]['M3'] = (float)$row['total_volume_m3'];
    }
}
$stmt_producao->close();

// Prepara os arrays finais para o Chart.js
$dados_grafico_labels = array_map(fn($data) => date('d/m', strtotime($data)), array_keys($dados_agregados));
$dados_grafico_pc = array_column($dados_agregados, 'PC');
$dados_grafico_m3 = array_column($dados_agregados, 'M3');

?>

<div class="container mt-4">
    <?php
    // OBSERVAÇÃO: Bloco para exibir mensagens de feedback (ex: login bem-sucedido)
    if (isset($_SESSION['message'])) {
        echo "<div class='message " . htmlspecialchars($_SESSION['message_type']) . "'>" . $_SESSION['message'] . "</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
    <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
    <p class="lead">Visão geral das operações e indicadores chave de desempenho.</p>

    <!-- Indicadores -->
    <div class="row indicators-container">
        <div class="col-md-4 mb-4">
            <div class="indicator-card">
                <h3>OPs Ativas</h3>
                <p class="indicator-number"><?php echo $total_ops_ativas; ?></p>
                <p class="indicator-description">Ordens pendentes ou em produção.</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="indicator-card <?php echo ($total_maquinas_paradas > 0) ? 'alert' : ''; ?>">
                <h3>Máquinas Paradas</h3>
                <p class="indicator-number"><?php echo $total_maquinas_paradas; ?></p>
                <p class="indicator-description">Manutenção ou problemas.</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="indicator-card">
                <h3>Máquinas Operacionais</h3>
                <p class="indicator-number" style="color: #27ae60;"><?php echo $total_maquinas_operacionais; ?></p>
                <p class="indicator-description">Equipamentos prontos para uso.</p>
            </div>
        </div>
    </div>
    
    <!-- Gráfico de Produção -->
    <div class="card mt-4">
        <div class="card-header">
            <h4>Produção de Produtos Acabados nos Últimos 7 Dias</h4>
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                <button id="btnVerPC" class="button small">Ver em Qtde (PC)</button>
                <button id="btnVerM3" class="button small active">Ver em Volume (M³)</button>
            </div>
            <canvas id="graficoProducaoDashboard" style="max-height: 350px;"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('graficoProducaoDashboard');
    if (ctx) {
        const labels = <?php echo json_encode($dados_grafico_labels); ?>;
        const dataPC = <?php echo json_encode($dados_grafico_pc); ?>;
        const dataM3 = <?php echo json_encode($dados_grafico_m3); ?>;
        
        const btnPC = document.getElementById('btnVerPC');
        const btnM3 = document.getElementById('btnVerM3');

        const chartData = {
            labels: labels,
            datasets: [{
                label: 'Produção Diária (M³)',
                data: dataM3,
                backgroundColor: 'rgba(41, 128, 185, 0.7)',
                borderColor: 'rgba(41, 128, 185, 1)',
                borderWidth: 1
            }]
        };

        const chartOptions = {
            scales: { y: { beginAtZero: true } },
            plugins: { tooltip: { callbacks: { label: function(context) { return ` Total: ${context.parsed.y.toFixed(2)}`; } } } }
        };

        const myChart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: chartOptions
        });

        function updateChart(metric) {
            if (metric === 'PC') {
                myChart.data.datasets[0].label = 'Produção Diária (PC)';
                myChart.data.datasets[0].data = dataPC;
                myChart.options.scales.y.ticks.callback = value => value.toFixed(2) + ' PC';
                myChart.options.plugins.tooltip.callbacks.label = context => ` Total: ${context.parsed.y.toFixed(2)} PC`;
                btnPC.classList.add('active');
                btnM3.classList.remove('active');
            } else { // M3
                myChart.data.datasets[0].label = 'Produção Diária (M³)';
                myChart.data.datasets[0].data = dataM3;
                myChart.options.scales.y.ticks.callback = value => value.toFixed(2) + ' M³';
                myChart.options.plugins.tooltip.callbacks.label = context => ` Total: ${context.parsed.y.toFixed(2)} M³`;
                btnM3.classList.add('active');
                btnPC.classList.remove('active');
            }
            myChart.update();
        }

        btnPC.addEventListener('click', () => updateChart('PC'));
        btnM3.addEventListener('click', () => updateChart('M3'));

        // Inicia com a visualização padrão (M³)
        updateChart('M3');
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
$conn->close();
?>
