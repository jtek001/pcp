<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_inicial_form = $_GET['data_inicial'] ?? date('Y-m-01\T00:00');
$data_final_form = $_GET['data_final'] ?? date('Y-m-t\T23:59');

// --- Lógica para buscar dados para os filtros dinâmicos ---
$linhas_disponiveis = $conn->execute_query("SELECT DISTINCT gm.id, gm.nome_grupo FROM grupos_maquinas gm JOIN ordens_producao op ON gm.id = op.grupo_id JOIN consumo_producao cp ON op.id = cp.ordem_producao_id WHERE cp.data_consumo BETWEEN ? AND ? ORDER BY gm.nome_grupo", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);
$maquinas_disponiveis = $conn->execute_query("SELECT DISTINCT m.id, m.nome FROM maquinas m JOIN consumo_producao cp ON m.id = cp.maquina_id WHERE cp.data_consumo BETWEEN ? AND ? ORDER BY m.nome", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);
$pedidos_disponiveis = $conn->execute_query("SELECT DISTINCT op.numero_pedido FROM ordens_producao op JOIN consumo_producao cp ON op.id = cp.ordem_producao_id WHERE op.numero_pedido IS NOT NULL AND op.numero_pedido != '' AND cp.data_consumo BETWEEN ? AND ? ORDER BY op.numero_pedido DESC", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);
$produtos_disponiveis = $conn->execute_query("SELECT DISTINCT p.id, p.nome, p.codigo FROM produtos p JOIN consumo_producao cp ON p.id = cp.produto_material_id WHERE EXISTS (SELECT 1 FROM roteiros r WHERE r.produto_id = p.id) AND cp.data_consumo BETWEEN ? AND ? ORDER BY p.nome", [$data_inicial_form, $data_final_form])->fetch_all(MYSQLI_ASSOC);

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
                        DATE(cp.data_consumo) as dia,
                        gm.nome_grupo as linha_nome,
                        p.nome as produto_nome,
                        p.codigo as produto_codigo,
                        p.unidade_medida,
                        p.unidade_medida2,
                        SUM(cp.quantidade_consumida) as total_consumido,
                        SUM(calcularVolume(cp.quantidade_consumida, p.espessura, p.largura, p.comprimento)) AS volume_total_m3
                     FROM consumo_producao cp
                     JOIN produtos p ON cp.produto_material_id = p.id
                     LEFT JOIN ordens_producao op ON cp.ordem_producao_id = op.id
                     LEFT JOIN grupos_maquinas gm ON op.grupo_id = gm.id
                     WHERE cp.deleted_at IS NULL AND EXISTS (SELECT 1 FROM roteiros r WHERE r.produto_id = p.id)";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND cp.data_consumo >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
            $filtros_aplicados['Data Inicial'] = date('d/m/Y H:i', strtotime($_GET['data_inicial']));
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND cp.data_consumo <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
            $filtros_aplicados['Data Final'] = date('d/m/Y H:i', strtotime($_GET['data_final']));
        }
        if (!empty($_GET['linha_id'])) $sql_base .= " AND op.grupo_id = " . (int)$_GET['linha_id'];
        if (!empty($_GET['maquina_id'])) $sql_base .= " AND cp.maquina_id = " . (int)$_GET['maquina_id'];
        if (!empty($_GET['numero_pedido'])) $sql_base .= " AND op.numero_pedido = '" . $conn->real_escape_string($_GET['numero_pedido']) . "'";
        if (!empty($_GET['produto_id'])) $sql_base .= " AND cp.produto_material_id = " . (int)$_GET['produto_id'];
        
        $sql_base .= " GROUP BY dia, linha_nome, p.id ORDER BY dia DESC, linha_nome, produto_nome";
        
        $stmt = $conn->prepare($sql_base);
        if ($stmt === false) throw new Exception("Erro ao preparar a consulta: " . $conn->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $dados_relatorio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Agrega dados para o gráfico de consumo diário
        $grafico_agregado = [];
        foreach($dados_relatorio as $dado) {
            $dia_key = date('Y-m-d', strtotime($dado['dia']));
            if (!isset($grafico_agregado[$dia_key])) {
                $grafico_agregado[$dia_key] = ['qtd' => 0, 'vol' => 0];
            }
            $grafico_agregado[$dia_key]['qtd'] += $dado['total_consumido'];
            $grafico_agregado[$dia_key]['vol'] += $dado['volume_total_m3'];
        }
        ksort($grafico_agregado);

        $dados_grafico['labels'] = array_map(fn($data) => date('d/m/Y', strtotime($data)), array_keys($grafico_agregado));
        $dados_grafico['data_qtd'] = array_column($grafico_agregado, 'qtd');
        $dados_grafico['data_m3'] = array_column($grafico_agregado, 'vol');


    } catch (Exception $e) {
        $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-puzzle-piece"></i> Relatório de Consumo de Semiacabados</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_consumo_semiacabados.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3"><label>Data/Hora Inicial</label><input type="datetime-local" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_form); ?>"></div>
                    <div class="col-md-6 mb-3"><label>Data/Hora Final</label><input type="datetime-local" class="form-control" name="data_final" value="<?php echo htmlspecialchars($data_final_form); ?>"></div>
                    <div class="col-md-6 mb-3"><label>Linha de Produção</label><select name="linha_id" class="form-select"><option value="">Todas</option><?php foreach($linhas_disponiveis as $linha): ?><option value="<?php echo $linha['id']; ?>" <?php echo (($_GET['linha_id'] ?? '') == $linha['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($linha['nome_grupo']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Máquina</label><select name="maquina_id" class="form-select"><option value="">Todas</option><?php foreach($maquinas_disponiveis as $maquina): ?><option value="<?php echo $maquina['id']; ?>" <?php echo (($_GET['maquina_id'] ?? '') == $maquina['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($maquina['nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Pedido</label><select name="numero_pedido" class="form-select"><option value="">Todos</option><?php foreach($pedidos_disponiveis as $pedido): ?><option value="<?php echo htmlspecialchars($pedido['numero_pedido']); ?>" <?php echo (($_GET['numero_pedido'] ?? '') == $pedido['numero_pedido']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pedido['numero_pedido']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 mb-3"><label>Produto Consumido</label><select name="produto_id" class="form-select"><option value="">Todos</option><?php foreach($produtos_disponiveis as $produto): ?><option value="<?php echo $produto['id']; ?>" <?php echo (($_GET['produto_id'] ?? '') == $produto['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($produto['nome'] . ' (' . $produto['codigo'] . ')'); ?></option><?php endforeach; ?></select></div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_consumo_semiacabados.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar'])): ?>
    <div class="card">
        <div class="card-header">Resultados do Consumo</div>
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
                            <th>Produto Consumido</th>
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
                            $total_geral_qtd += $item['total_consumido'];
                            $total_geral_vol += $item['volume_total_m3'];
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($item['dia'])); ?></td>
                            <td><?php echo htmlspecialchars($item['linha_nome'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['produto_nome'] . ' (' . $item['produto_codigo'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($item['unidade_medida']); ?></td>
                            <td class="text-end"><?php echo number_format($item['total_consumido'], 2, ',', '.'); ?></td>
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

                <h4 class="mt-4">Gráfico de Consumo Diário</h4>
                <div class="text-center mb-2">
                    <button id="btnVerQtd" class="button small">Ver em Quantidade</button>
                    <button id="btnVerM3" class="button small active">Ver em Volume (M³)</button>
                </div>
                <canvas id="graficoConsumo" style="max-height: 400px;"></canvas>
            <?php else: ?>
                <p class="text-center mt-3">Nenhum consumo encontrado para os filtros selecionados.</p>
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
    const ctx = document.getElementById('graficoConsumo');
    if (ctx) {
        const labels = <?php echo json_encode($dados_grafico['labels'] ?? []); ?>;
        const dataQtd = <?php echo json_encode($dados_grafico['data_qtd'] ?? []); ?>;
        const dataM3 = <?php echo json_encode($dados_grafico['data_m3'] ?? []); ?>;
        
        const btnQtd = document.getElementById('btnVerQtd');
        const btnM3 = document.getElementById('btnVerM3');

        const myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Consumo Diário',
                    data: [], // Iniciado vazio
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });

        function updateChart(metric) {
            if (metric === 'QTD') {
                myChart.data.datasets[0].data = dataQtd;
                myChart.data.datasets[0].label = 'Consumo Diário (Qtd)';
                myChart.options.scales.y.ticks.callback = value => value.toFixed(2);
                btnQtd.classList.add('active');
                btnM3.classList.remove('active');
            } else { // M3
                myChart.data.datasets[0].data = dataM3;
                myChart.data.datasets[0].label = 'Consumo Diário (M³)';
                myChart.options.scales.y.ticks.callback = value => value.toFixed(4) + ' M³';
                btnM3.classList.add('active');
                btnQtd.classList.remove('active');
            }
            myChart.update();
        }

        btnQtd.addEventListener('click', () => updateChart('QTD'));
        btnM3.addEventListener('click', () => updateChart('M3'));

        updateChart('M3'); // Inicia com a visão padrão
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
