<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_inicial_form = $_GET['data_inicial'] ?? date('Y-m-01\T00:00');
$data_final_form = $_GET['data_final'] ?? date('Y-m-t\T23:59');

// --- Lógica para buscar dados para os filtros dinâmicos ---
$linhas_disponiveis = $conn->execute_query("SELECT DISTINCT gm.id, gm.nome_grupo FROM grupos_maquinas gm JOIN ordens_producao op ON gm.id = op.grupo_id JOIN apontamentos_producao ap ON op.id = ap.ordem_producao_id WHERE ap.data_apontamento BETWEEN ? AND ? ORDER BY gm.nome_grupo", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);
$maquinas_disponiveis = $conn->execute_query("SELECT DISTINCT m.id, m.nome FROM maquinas m JOIN apontamentos_producao ap ON m.id = ap.maquina_id WHERE ap.data_apontamento BETWEEN ? AND ? ORDER BY m.nome", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);
$turnos_disponiveis = $conn->execute_query("SELECT DISTINCT t.id, t.nome_turno FROM turnos t JOIN apontamentos_producao ap ON t.id = ap.turno_id WHERE ap.data_apontamento BETWEEN ? AND ? ORDER BY t.nome_turno", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);
$produtos_disponiveis = $conn->execute_query("SELECT DISTINCT p.id, p.nome, p.codigo FROM produtos p JOIN ordens_producao op ON p.id = op.produto_id JOIN apontamentos_producao ap ON op.id = ap.ordem_producao_id WHERE ap.data_apontamento BETWEEN ? AND ? ORDER BY p.nome", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);

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
                        gm.nome_grupo as linha_nome,
                        p.nome as produto_nome,
                        p.codigo as produto_codigo,
                        p.unidade_medida,
                        p.unidade_medida2,
                        SUM(ap.quantidade_produzida) as total_quantidade,
                        SUM(calcularVolume(ap.quantidade_produzida, p.espessura, p.largura, p.comprimento)) AS volume_total_m3
                     FROM apontamentos_producao ap
                     JOIN ordens_producao op ON ap.ordem_producao_id = op.id
                     JOIN produtos p ON op.produto_id = p.id
                     LEFT JOIN grupos_maquinas gm ON op.grupo_id = gm.id
                     LEFT JOIN maquinas m ON ap.maquina_id = m.id
                     WHERE ap.lote_numero NOT LIKE '%DEV%' and ap.deleted_at IS NULL";

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
        if (!empty($_GET['linha_id'])) {
            $sql_base .= " AND op.grupo_id = ?";
            $params[] = $_GET['linha_id'];
            $types .= 'i';
            $filtros_aplicados['Linha'] = $conn->execute_query("SELECT nome_grupo FROM grupos_maquinas WHERE id = ?", [$_GET['linha_id']])->fetch_assoc()['nome_grupo'];
        }
        if (!empty($_GET['maquina_id'])) {
            $sql_base .= " AND ap.maquina_id = ?";
            $params[] = $_GET['maquina_id'];
            $types .= 'i';
            $filtros_aplicados['Máquina'] = $conn->execute_query("SELECT nome FROM maquinas WHERE id = ?", [$_GET['maquina_id']])->fetch_assoc()['nome'];
        }
        if (!empty($_GET['turno_id'])) {
            $sql_base .= " AND ap.turno_id = ?";
            $params[] = $_GET['turno_id'];
            $types .= 'i';
            $filtros_aplicados['Turno'] = $conn->execute_query("SELECT nome_turno FROM turnos WHERE id = ?", [$_GET['turno_id']])->fetch_assoc()['nome_turno'];
        }
        if (!empty($_GET['produto_id'])) {
            $sql_base .= " AND op.produto_id = ?";
            $params[] = $_GET['produto_id'];
            $types .= 'i';
            $filtros_aplicados['Produto'] = $conn->execute_query("SELECT nome FROM produtos WHERE id = ?", [$_GET['produto_id']])->fetch_assoc()['nome'];
        }
        
        $sql_base .= " GROUP BY dia, linha_nome, p.id, p.nome, p.codigo, p.unidade_medida, p.unidade_medida2, p.espessura, p.largura, p.comprimento ORDER BY dia DESC, linha_nome, produto_nome";
        
        $stmt = $conn->prepare($sql_base);
        if ($stmt === false) throw new Exception("Erro ao preparar a consulta: " . $conn->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $dados_relatorio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $grafico_agregado = [];
        $periodo_dias = new DatePeriod(
            new DateTime($data_inicial_form),
            new DateInterval('P1D'),
            (new DateTime($data_final_form))->modify('+1 day')
        );

        foreach ($periodo_dias as $dia) {
            $grafico_agregado[$dia->format('Y-m-d')] = ['PC' => 0, 'M3' => 0];
        }

        foreach ($dados_relatorio as $dado) {
            $dia_key = date('Y-m-d', strtotime($dado['dia']));
            if (isset($grafico_agregado[$dia_key])) {
                if(strtoupper($dado['unidade_medida']) === 'PC') {
                    $grafico_agregado[$dia_key]['PC'] += $dado['total_quantidade'];
                }
                if(strtoupper($dado['unidade_medida2']) === 'M3') {
                    $grafico_agregado[$dia_key]['M3'] += $dado['volume_total_m3'];
                }
            }
        }

        $dados_grafico_labels = array_map(fn($data) => date('d/m', strtotime($data)), array_keys($grafico_agregado));
        $dados_grafico_pc = array_column($grafico_agregado, 'PC');
        $dados_grafico_m3 = array_column($grafico_agregado, 'M3');

    } catch (Exception $e) {
        $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-search"></i> Relatório de Produção Detalhado</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_producao_detalhado.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3"><label>Data/Hora Inicial</label><input type="datetime-local" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_form); ?>"></div>
                    <div class="col-md-6 mb-3"><label>Data/Hora Final</label><input type="datetime-local" class="form-control" name="data_final" value="<?php echo htmlspecialchars($data_final_form); ?>"></div>
                    <div class="col-md-6 mb-3"><label>Linha de Produção</label><select name="linha_id" class="form-select"><option value="">Todas</option><?php foreach($linhas_disponiveis as $linha): ?><option value="<?php echo $linha['id']; ?>" <?php echo (($_GET['linha_id'] ?? '') == $linha['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($linha['nome_grupo']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Máquina</label><select name="maquina_id" class="form-select"><option value="">Todas</option><?php foreach($maquinas_disponiveis as $maquina): ?><option value="<?php echo $maquina['id']; ?>" <?php echo (($_GET['maquina_id'] ?? '') == $maquina['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($maquina['nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Turno</label><select name="turno_id" class="form-select"><option value="">Todos</option><?php foreach($turnos_disponiveis as $turno): ?><option value="<?php echo $turno['id']; ?>" <?php echo (($_GET['turno_id'] ?? '') == $turno['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($turno['nome_turno']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Produto</label><select name="produto_id" class="form-select"><option value="">Todos</option><?php foreach($produtos_disponiveis as $produto): ?><option value="<?php echo $produto['id']; ?>" <?php echo (($_GET['produto_id'] ?? '') == $produto['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($produto['nome'] . ' (' . $produto['codigo'] . ')'); ?></option><?php endforeach; ?></select></div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_producao_detalhado.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar'])): ?>
    <div class="card">
        <div class="card-header">Resultados da Produção</div>
        <div class="card-body">
            <?php if(!empty($filtros_aplicados)): ?>
                <p><strong>Filtros aplicados:</strong> <?php echo implode('; ', array_map(fn($k, $v) => "<strong>$k:</strong> " . htmlspecialchars($v), array_keys($filtros_aplicados), $filtros_aplicados)); ?></p><hr>
            <?php endif; ?>
            <?php if (!empty($dados_relatorio)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Linha</th>
                            <th>Produto</th>
                            <th>Un. Medida</th>
                            <th class="text-end">Quantidade</th>
                            <th class="text-end">Volume (M³)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_geral_qtd = 0; 
                        $total_geral_vol = 0;
                        foreach ($dados_relatorio as $item): 
                            $total_geral_qtd += $item['total_quantidade'];
                            $total_geral_vol += $item['volume_total_m3'];
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($item['dia'])); ?></td>
                            <td><?php echo htmlspecialchars($item['linha_nome'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['produto_nome'] . ' (' . $item['produto_codigo'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($item['unidade_medida']); ?></td>
                            <td class="text-end"><?php echo number_format($item['total_quantidade'], 2, ',', '.'); ?></td>
                            <td class="text-end">
                                <?php if (strtoupper($item['unidade_medida2']) === 'M3'): ?>
                                    <?php echo number_format($item['volume_total_m3'], 4, ',', '.'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td colspan="4">TOTAL GERAL</td>
                            <td class="text-end"><?php echo number_format($total_geral_qtd, 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($total_geral_vol, 4, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- GRÁFICO ADICIONADO -->
                <h4 class="mt-4">Gráfico de Produção Diária</h4>
                <div class="text-center mb-2">
                    <button id="btnVerPC" class="button small">Ver em Qtde (PC)</button>
                    <button id="btnVerM3" class="button small active">Ver em Volume (M³)</button>
                </div>
                <canvas id="graficoProducaoDetalhado" style="max-height: 400px;"></canvas>

            <?php else: ?>
                <p class="text-center mt-3">Nenhuma produção encontrada para os filtros selecionados.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Selecione os filtros desejados e clique em "Gerar Relatório" para exibir os dados.</div>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Relatórios</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('graficoProducaoDetalhado');
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
                    data: [], // Iniciado vazio
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });

        function updateChart(metric) {
            if (metric === 'PC') {
                myChart.data.datasets[0].data = dataPC;
                myChart.data.datasets[0].label = 'Produção Diária (PC)';
                myChart.options.scales.y.ticks.callback = value => value.toFixed(2);
                btnPC.classList.add('active');
                btnM3.classList.remove('active');
            } else { // M3
                myChart.data.datasets[0].data = dataM3;
                myChart.data.datasets[0].label = 'Produção Diária (M³)';
                myChart.options.scales.y.ticks.callback = value => value.toFixed(4) + ' M³';
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
?>
