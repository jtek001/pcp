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
$produtos_disponiveis = [];

if (!empty($data_inicial_form) && !empty($data_final_form)) {
    $sql_maquinas = "SELECT DISTINCT m.id, m.nome 
                     FROM maquinas m
                     JOIN apontamentos_producao ap ON m.id = ap.maquina_id
                     WHERE ap.data_apontamento BETWEEN ? AND ?
                     ORDER BY m.nome";
    $maquinas_disponiveis = $conn->execute_query($sql_maquinas, [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);

    $sql_produtos = "SELECT DISTINCT p.id, p.nome, p.codigo
                     FROM produtos p
                     JOIN ordens_producao op ON p.id = op.produto_id
                     JOIN apontamentos_producao ap ON op.id = ap.ordem_producao_id
                     WHERE ap.data_apontamento BETWEEN ? AND ?
                     ORDER BY p.nome";
    $produtos_disponiveis = $conn->execute_query($sql_produtos, [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);
}


// --- Lógica de Filtragem e Busca de Dados do Relatório ---
$dados_relatorio = [];
$dados_grafico_labels = [];
$dados_grafico_pc = [];
$dados_grafico_m3 = [];
$filtros_aplicados = [];
$error_message = '';

if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';

        $sql_base = "SELECT 
                        DATE(ap.data_apontamento) as dia,
                        m.nome as maquina_nome,
                        p.nome as produto_nome,
                        p.codigo as produto_codigo,
                        p.unidade_medida,
                        SUM(CASE WHEN UPPER(p.unidade_medida) = 'PC' THEN ap.quantidade_produzida ELSE 0 END) AS total_quantidade_pc,
                        SUM(CASE WHEN UPPER(p.unidade_medida2) = 'M3' THEN calcularVolume(ap.quantidade_produzida, p.espessura, p.largura, p.comprimento) ELSE 0 END) AS total_volume_m3
                     FROM apontamentos_producao ap
                     JOIN ordens_producao op ON ap.ordem_producao_id = op.id
                     JOIN produtos p ON op.produto_id = p.id
                     JOIN maquinas m ON ap.maquina_id = m.id
                     WHERE (ap.deleted_at IS NULL OR EXISTS (
                                SELECT 1 FROM consumo_producao cp 
                                WHERE cp.apontamento_id = ap.id AND cp.deleted_at IS NULL
                            ))";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND ap.data_apontamento >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
            $filtros_aplicados['Data Inicial'] = date('d/m/Y H:i', strtotime($_GET['data_inicial']));
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND ap.data_apontamento <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
            $filtros_aplicados['Data Final'] = date('d/m/Y H:i', strtotime($_GET['data_final']));
        }
        if (!empty($_GET['maquina_id'])) {
            $sql_base .= " AND ap.maquina_id = ?";
            $params[] = $_GET['maquina_id'];
            $types .= 'i';
            $filtros_aplicados['Máquina'] = $conn->execute_query("SELECT nome FROM maquinas WHERE id = ?", [$_GET['maquina_id']])->fetch_assoc()['nome'];
        }
        if (!empty($_GET['produto_id'])) {
            $sql_base .= " AND op.produto_id = ?";
            $params[] = $_GET['produto_id'];
            $types .= 'i';
            $filtros_aplicados['Produto'] = $conn->execute_query("SELECT nome FROM produtos WHERE id = ?", [$_GET['produto_id']])->fetch_assoc()['nome'];
        }
        
        $sql_base .= " GROUP BY DATE(ap.data_apontamento), m.nome, p.nome, p.codigo, p.unidade_medida ORDER BY dia ASC, maquina_nome, produto_nome";

        $stmt = $conn->prepare($sql_base);
        if ($stmt === false) throw new Exception("Erro ao preparar a consulta SQL: " . $conn->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $dados_relatorio = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Agrega dados diários para o gráfico
        $grafico_agregado = [];
        foreach ($dados_relatorio as $dado) {
            $dia_key = date('Y-m-d', strtotime($dado['dia']));
            if (!isset($grafico_agregado[$dia_key])) {
                $grafico_agregado[$dia_key] = ['PC' => 0, 'M3' => 0];
            }
            $grafico_agregado[$dia_key]['PC'] += $dado['total_quantidade_pc'];
            $grafico_agregado[$dia_key]['M3'] += $dado['total_volume_m3'];
        }
        ksort($grafico_agregado);

        $dados_grafico_labels = array_map(fn($data) => date('d/m', strtotime($data)), array_keys($grafico_agregado));
        $dados_grafico_pc = array_column($grafico_agregado, 'PC');
        $dados_grafico_m3 = array_column($grafico_agregado, 'M3');

    } catch (Exception $e) {
        $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2>Relatório de Produção por Período</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros do Relatório</div>
        <div class="card-body">
            <form action="relatorio_producao_periodo.php" method="GET">
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
                        <label for="produto_id" class="form-label">Produto</label>
                        <select name="produto_id" class="form-select">
                            <option value="">Todos</option>
                             <?php foreach($produtos_disponiveis as $produto): ?>
                                <option value="<?php echo $produto['id']; ?>" <?php echo (($_GET['produto_id'] ?? '') == $produto['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($produto['nome'] . ' (' . $produto['codigo'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_producao_periodo.php" class="button button-clear">Limpar Filtros</a>
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

                <h4>Produção Detalhada</h4>
                <?php if (!empty($dados_relatorio)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Máquina</th>
                                <th>Produto</th>
                                <th>Un. Medida</th>
                                <th class="text-end">Qtde (PC)</th>
                                <th class="text-end">Volume (M³)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_geral_qtd = 0; 
                            $total_geral_vol = 0;
                            foreach ($dados_relatorio as $dado): 
                                $total_geral_qtd += $dado['total_quantidade_pc'];
                                $total_geral_vol += $dado['total_volume_m3'];
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($dado['dia'])); ?></td>
                                <td><?php echo htmlspecialchars($dado['maquina_nome']); ?></td>
                                <td><?php echo htmlspecialchars($dado['produto_nome'] . ' (' . $dado['produto_codigo'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($dado['unidade_medida']); ?></td>
                                <td class="text-end"><?php echo number_format($dado['total_quantidade_pc'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($dado['total_volume_m3'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: bold;">
                                <td colspan="4">TOTAL GERAL</td>
                                <td class="text-end"><?php echo number_format($total_geral_qtd, 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($total_geral_vol, 2, ',', '.'); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <h4 class="mt-4">Gráfico de Produção Diária</h4>
                    <div class="text-center mb-2">
                        <button id="btnVerPC" class="button small">Ver em Qtde (PC)</button>
                        <button id="btnVerM3" class="button small active">Ver em Volume (M³)</button>
                    </div>
                    <canvas id="graficoProducao" style="max-height: 400px;"></canvas>

                <?php else: ?>
                    <div class="alert alert-warning">Nenhum dado encontrado para os filtros selecionados.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Relatórios</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('graficoProducao');
    if (ctx) {
        const labels = <?php echo json_encode($dados_grafico_labels ?? []); ?>;
        const dataPC = <?php echo json_encode($dados_grafico_pc ?? []); ?>;
        const dataM3 = <?php echo json_encode($dados_grafico_m3 ?? []); ?>;
        
        const btnPC = document.getElementById('btnVerPC');
        const btnM3 = document.getElementById('btnVerM3');

        const myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Produção Diária',
                    data: [], // Iniciado vazio, preenchido pela função updateChart
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Total: ${context.parsed.y.toFixed(2)}`;
                            }
                        }
                    }
                }
            }
        });

        function updateChart(metric) {
            if (metric === 'PC') {
                myChart.data.datasets[0].data = dataPC;
                myChart.data.datasets[0].label = 'Produção Diária (PC)';
                myChart.options.plugins.tooltip.callbacks.label = context => ` Total: ${context.parsed.y.toFixed(2)} PC`;
                btnPC.classList.add('active');
                btnM3.classList.remove('active');
            } else { // M3
                myChart.data.datasets[0].data = dataM3;
                myChart.data.datasets[0].label = 'Produção Diária (M³)';
                myChart.options.plugins.tooltip.callbacks.label = context => ` Total: ${context.parsed.y.toFixed(2)} M³`;
                btnM3.classList.add('active');
                btnPC.classList.remove('active');
            }
            myChart.update();
        }

        btnPC.addEventListener('click', () => updateChart('PC'));
        btnM3.addEventListener('click', () => updateChart('M3'));

        // Inicia com a visualização padrão
        updateChart('M3');
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
?>
