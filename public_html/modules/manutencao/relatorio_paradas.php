<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_inicial_form = $_GET['data_inicial'] ?? date('Y-m-01\T00:00');
$data_final_form = $_GET['data_final'] ?? date('Y-m-t\T23:59');

// --- Lógica para buscar dados para os filtros dinâmicos ---
$maquinas_disponiveis = [];
$motivos_disponiveis = [];

$sql_maquinas = "SELECT DISTINCT m.id, m.nome 
                 FROM maquinas m
                 JOIN paradas_maquina pm ON m.id = pm.maquina_id
                 WHERE pm.data_hora_inicio BETWEEN ? AND ?
                 ORDER BY m.nome";
$maquinas_disponiveis = $conn->execute_query($sql_maquinas, [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);

$sql_motivos = "SELECT DISTINCT mp.id, mp.nome, mp.codigo
                FROM motivos_parada mp
                JOIN paradas_maquina pm ON mp.id = pm.motivo_id
                WHERE pm.data_hora_inicio BETWEEN ? AND ?
                ORDER BY mp.nome";
$motivos_disponiveis = $conn->execute_query($sql_motivos, [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);


// --- Lógica de Filtragem e Busca de Dados do Relatório ---
$dados_relatorio = [];
$dados_grafico = [];
$filtros_aplicados = [];
$error_message = '';

if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';

        $sql_base = "SELECT 
                        pm.data_hora_inicio, 
                        m.nome as maquina_nome,
                        mp.nome as motivo_nome,
                        pm.duracao_minutos
                     FROM paradas_maquina pm
                     JOIN maquinas m ON pm.maquina_id = m.id
                     JOIN motivos_parada mp ON pm.motivo_id = mp.id
                     WHERE pm.deleted_at IS NULL AND pm.duracao_minutos IS NOT NULL";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND pm.data_hora_inicio >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
            $filtros_aplicados['Data Inicial'] = date('d/m/Y H:i', strtotime($_GET['data_inicial']));
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND pm.data_hora_inicio <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
            $filtros_aplicados['Data Final'] = date('d/m/Y H:i', strtotime($_GET['data_final']));
        }
        if (!empty($_GET['maquina_id'])) {
            $sql_base .= " AND pm.maquina_id = ?";
            $params[] = $_GET['maquina_id'];
            $types .= 'i';
            $filtros_aplicados['Máquina'] = $conn->execute_query("SELECT nome FROM maquinas WHERE id = ?", [$_GET['maquina_id']])->fetch_assoc()['nome'];
        }
        if (!empty($_GET['motivo_id'])) {
            $sql_base .= " AND pm.motivo_id = ?";
            $params[] = $_GET['motivo_id'];
            $types .= 'i';
            $filtros_aplicados['Motivo'] = $conn->execute_query("SELECT nome FROM motivos_parada WHERE id = ?", [$_GET['motivo_id']])->fetch_assoc()['nome'];
        }

        $sql_base .= " ORDER BY pm.data_hora_inicio DESC";
        
        $stmt = $conn->prepare($sql_base);
        if ($stmt === false) throw new Exception("Erro ao preparar a consulta: " . $conn->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $dados_relatorio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Agrega dados para o gráfico por MOTIVO de parada
        $grafico_agregado = [];
        foreach($dados_relatorio as $dado) {
            @$grafico_agregado[$dado['motivo_nome']] += $dado['duracao_minutos'];
        }
        arsort($grafico_agregado);
        $dados_grafico['labels'] = array_keys($grafico_agregado);
        $dados_grafico['data_minutos'] = array_values($grafico_agregado);
        $dados_grafico['data_horas'] = array_map(fn($min) => $min / 60, $dados_grafico['data_minutos']);
        
        // --- NOVO: Cálculo para a linha do Pareto ---
        $total_geral_minutos_grafico = array_sum($dados_grafico['data_minutos']);
        $cumulative_percentage = 0;
        $dados_grafico['pareto'] = [];
        if ($total_geral_minutos_grafico > 0) {
            foreach ($dados_grafico['data_minutos'] as $value) {
                $cumulative_percentage += ($value / $total_geral_minutos_grafico) * 100;
                $dados_grafico['pareto'][] = $cumulative_percentage;
            }
        }

    } catch (Exception $e) {
        $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2>Relatório de Paradas de Máquina</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_paradas.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="data_inicial" class="form-label">Data Inicial</label>
                        <input type="datetime-local" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_form); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="data_final" class="form-label">Data Final</label>
                        <input type="datetime-local" class="form-control" name="data_final" value="<?php echo htmlspecialchars($data_final_form); ?>">
                    </div>
                     <div class="col-md-6 mb-3">
                        <label for="maquina_id" class="form-label">Máquina</label>
                        <select name="maquina_id" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach($maquinas_disponiveis as $maquina): ?>
                                <option value="<?php echo $maquina['id']; ?>" <?php echo (($_GET['maquina_id'] ?? '') == $maquina['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($maquina['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="motivo_id" class="form-label">Motivo</label>
                        <select name="motivo_id" class="form-select">
                            <option value="">Todos</option>
                             <?php foreach($motivos_disponiveis as $motivo): ?>
                                <option value="<?php echo $motivo['id']; ?>" <?php echo (($_GET['motivo_id'] ?? '') == $motivo['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($motivo['codigo'] . ' - ' . $motivo['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_paradas.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar']) && empty($error_message)): ?>
        <div class="card">
            <div class="card-header">Resultados</div>
            <div class="card-body">
                <?php if(!empty($filtros_aplicados)): ?>
                    <p><strong>Filtros aplicados:</strong> <?php echo implode('; ', array_map(fn($k, $v) => "<strong>$k:</strong> " . htmlspecialchars($v), array_keys($filtros_aplicados), $filtros_aplicados)); ?></p><hr>
                <?php endif; ?>

                <h4>Paradas Detalhadas</h4>
                <?php if (!empty($dados_relatorio)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Máquina</th>
                                <th>Motivo</th>
                                <th class="text-end">Duração (min)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total_geral = 0; foreach ($dados_relatorio as $dado): $total_geral += $dado['duracao_minutos']; ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($dado['data_hora_inicio'])); ?></td>
                                <td><?php echo htmlspecialchars($dado['maquina_nome']); ?></td>
                                <td><?php echo htmlspecialchars($dado['motivo_nome']); ?></td>
                                <td class="text-end"><?php echo number_format($dado['duracao_minutos'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: bold;">
                                <td colspan="3">TOTAL GERAL (minutos)</td>
                                <td class="text-end"><?php echo number_format($total_geral, 0, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <h4 class="mt-4">Gráfico de Pareto - Motivos de Parada</h4>
                    <div class="text-center mb-2">
                        <button id="btnVerMinutos" class="button small active">Ver em Minutos</button>
                        <button id="btnVerHoras" class="button small">Ver em Horas</button>
                    </div>
                    <canvas id="graficoParadas" style="max-height: 400px;"></canvas>

                <?php else: ?>
                    <div class="alert alert-warning">Nenhuma parada encontrada para os filtros selecionados.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Manutenção</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('graficoParadas');
    if (ctx) {
        const labels = <?php echo json_encode($dados_grafico['labels'] ?? []); ?>;
        const dataMinutos = <?php echo json_encode($dados_grafico['data_minutos'] ?? []); ?>;
        const dataHoras = <?php echo json_encode($dados_grafico['data_horas'] ?? []); ?>;
        const paretoData = <?php echo json_encode($dados_grafico['pareto'] ?? []); ?>;
        
        const btnMinutos = document.getElementById('btnVerMinutos');
        const btnHoras = document.getElementById('btnVerHoras');

        const chartData = {
            labels: labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Duração',
                    data: dataMinutos,
                    backgroundColor: 'rgba(231, 76, 60, 0.7)',
                    borderColor: 'rgba(192, 57, 43, 1)',
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Cumulativo %',
                    data: paretoData,
                    borderColor: 'rgba(41, 128, 185, 1)',
                    backgroundColor: 'rgba(41, 128, 185, 0.2)',
                    fill: true,
                    yAxisID: 'y1',
                }
            ]
        };
        
        const myChart = new Chart(ctx, {
            type: 'bar', // Tipo base, mas os datasets têm tipos individuais
            data: chartData,
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Duração' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        min: 0,
                        max: 100,
                        ticks: { callback: value => value.toFixed(0) + '%' }
                    }
                }
            }
        });

        function updateChart(metric) {
            if (metric === 'Horas') {
                myChart.data.datasets[0].data = dataHoras;
                myChart.options.scales.y.title.text = 'Duração (Horas)';
                btnHoras.classList.add('active');
                btnMinutos.classList.remove('active');
            } else { // Minutos
                myChart.data.datasets[0].data = dataMinutos;
                myChart.options.scales.y.title.text = 'Duração (Minutos)';
                btnMinutos.classList.add('active');
                btnHoras.classList.remove('active');
            }
            myChart.update();
        }

        btnMinutos.addEventListener('click', () => updateChart('Minutos'));
        btnHoras.addEventListener('click', () => updateChart('Horas'));

        updateChart('Minutos'); // Inicia com a visão padrão
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
?>
