<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Lógica para buscar dados para os filtros ---
$maquinas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
$produtos = $conn->query("SELECT id, nome, codigo FROM produtos WHERE deleted_at IS NULL ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

// --- Lógica de Filtragem e Busca de Dados ---
$dados_relatorio = [];
$dados_grafico = [];
$filtros_aplicados = [];
$error_message = '';
$unidade_selecionada = $_GET['unidade_medida'] ?? 'M3';
$unidade_grafico = $unidade_selecionada;

if (isset($_GET['filtrar'])) {
    try {
        $params = [];
        $types = '';

        // ALTERAÇÃO: A cláusula WHERE agora inclui apontamentos deletados que foram consumidos.
        $sql_base = "SELECT 
                        DATE(ap.data_apontamento) as dia,
                        m.nome as maquina_nome,
                        p.nome as produto_nome,
                        p.codigo as produto_codigo,
                        p.unidade_medida,
                        SUM(CASE WHEN UPPER(p.unidade_medida) = 'PC' THEN ap.quantidade_produzida ELSE 0 END) AS total_quantidade_pc,
                        SUM(CASE WHEN UPPER(p.unidade_medida) = 'M3' OR UPPER(p.unidade_medida2) = 'M3' THEN (ap.quantidade_produzida * p.espessura * p.largura * p.comprimento) / 1000000000 ELSE 0 END) AS total_volume_m3
                     FROM apontamentos_producao ap
                     JOIN ordens_producao op ON ap.ordem_producao_id = op.id
                     JOIN produtos p ON op.produto_id = p.id
                     JOIN maquinas m ON ap.maquina_id = m.id
                     WHERE (ap.deleted_at IS NULL OR EXISTS (
                                SELECT 1 FROM consumo_producao cp 
                                WHERE cp.apontamento_id = ap.id AND cp.deleted_at IS NULL
                            ))";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND DATE(ap.data_apontamento) >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
            $filtros_aplicados['Data Inicial'] = date('d/m/Y', strtotime($_GET['data_inicial']));
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND DATE(ap.data_apontamento) <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
            $filtros_aplicados['Data Final'] = date('d/m/Y', strtotime($_GET['data_final']));
        }
        if (!empty($_GET['maquina_id'])) {
            $sql_base .= " AND ap.maquina_id = ?";
            $params[] = $_GET['maquina_id'];
            $types .= 'i';
            $maq_res = $conn->execute_query("SELECT nome FROM maquinas WHERE id = ?", [$_GET['maquina_id']]);
            if ($maq_row = $maq_res->fetch_assoc()) $filtros_aplicados['Máquina'] = $maq_row['nome'];
        }
        if (!empty($_GET['produto_id'])) {
            $sql_base .= " AND op.produto_id = ?";
            $params[] = $_GET['produto_id'];
            $types .= 'i';
            $prod_res = $conn->execute_query("SELECT nome FROM produtos WHERE id = ?", [$_GET['produto_id']]);
            if ($prod_row = $prod_res->fetch_assoc()) $filtros_aplicados['Produto'] = $prod_row['nome'];
        }
        
        if (!empty($unidade_selecionada)) {
            $sql_base .= " AND UPPER(p.unidade_medida) = ?";
            $params[] = strtoupper($unidade_selecionada);
            $types .= 's';
            $filtros_aplicados['Unidade'] = $unidade_selecionada;
        }

        $sql_base .= " GROUP BY DATE(ap.data_apontamento), m.nome, p.nome, p.codigo, p.unidade_medida ORDER BY dia ASC, maquina_nome, produto_nome";

        $stmt = $conn->prepare($sql_base);
        if ($stmt === false) throw new Exception("Erro ao preparar a consulta SQL: " . $conn->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $dados_relatorio = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Agrega dados para o gráfico (somatório diário)
        $grafico_agregado = [];
        foreach ($dados_relatorio as $dado) {
            $dia = date('d/m/Y', strtotime($dado['dia']));
            if (!isset($grafico_agregado[$dia])) {
                $grafico_agregado[$dia] = 0;
            }
            $grafico_agregado[$dia] += ($unidade_grafico === 'M3') ? $dado['total_volume_m3'] : $dado['total_quantidade_pc'];
        }
        $dados_grafico['labels'] = array_keys($grafico_agregado);
        $dados_grafico['data'] = array_values($grafico_agregado);

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
                    <div class="col-md-3 mb-3">
                        <label for="data_inicial" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($_GET['data_inicial'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="data_final" class="form-label">Data Final</label>
                        <input type="date" class="form-control" name="data_final" value="<?php echo htmlspecialchars($_GET['data_final'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="maquina_id" class="form-label">Máquina</label>
                        <select name="maquina_id" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach($maquinas as $maquina): ?>
                                <option value="<?php echo $maquina['id']; ?>" <?php echo (($_GET['maquina_id'] ?? '') == $maquina['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($maquina['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="produto_id" class="form-label">Produto</label>
                        <select name="produto_id" class="form-select">
                            <option value="">Todos</option>
                             <?php foreach($produtos as $produto): ?>
                                <option value="<?php echo $produto['id']; ?>" <?php echo (($_GET['produto_id'] ?? '') == $produto['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($produto['nome'] . ' (' . $produto['codigo'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="unidade_medida" class="form-label">Filtrar por Unidade:</label>
                         <select name="unidade_medida" id="unidade_medida" class="form-select">
                            <option value="M3" <?php echo ($unidade_selecionada == 'M3') ? 'selected' : ''; ?>>Volume (M³)</option>
                            <option value="PC" <?php echo ($unidade_selecionada == 'PC') ? 'selected' : ''; ?>>Quantidade (PC)</option>
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
        const labels = <?php echo json_encode($dados_grafico['labels'] ?? []); ?>;
        const data = <?php echo json_encode($dados_grafico['data'] ?? []); ?>;
        const unitLabel = '<?php echo $unidade_grafico === 'M3' ? 'M³' : 'PC'; ?>';
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: `Produção Diária (${unitLabel})`,
                    data: data,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: value => value.toFixed(2) + ` ${unitLabel}` }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: context => ` Total: ${context.parsed.y.toFixed(2)} ${unitLabel}`
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
?>
