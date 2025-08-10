<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$mes_selecionado = $_GET['mes'] ?? date('m');
$ano_selecionado = $_GET['ano'] ?? date('Y');

// --- Lógica de Busca de Dados do Relatório ---
$dados_relatorio = [];
$error_message = '';
$dias_no_mes = 0;

if (isset($_GET['filtrar'])) {
    try {
        $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_selecionado, $ano_selecionado);
        $data_inicio = "$ano_selecionado-$mes_selecionado-01 00:00:00";
        $data_fim = "$ano_selecionado-$mes_selecionado-$dias_no_mes 23:59:59";

        // 1. Busca a produção de produtos acabados
        $sql_producao = "SELECT 
                            gm.nome_grupo as linha,
                            DAY(ap.data_apontamento) as dia,
                            SUM(calcularVolume(ap.quantidade_produzida, p.espessura, p.largura, p.comprimento)) as volume_produzido
                         FROM apontamentos_producao ap
                         JOIN ordens_producao op ON ap.ordem_producao_id = op.id
                         JOIN produtos p ON op.produto_id = p.id
                         JOIN grupos_maquinas gm ON op.grupo_id = gm.id
                         WHERE ap.data_apontamento BETWEEN ? AND ? AND p.familia = 'Acabado' AND ap.deleted_at IS NULL
                         GROUP BY linha, dia";
        
        $producao_result = $conn->execute_query($sql_producao, [$data_inicio, $data_fim])->fetch_all(MYSQLI_ASSOC);

        // 2. Busca o consumo de semiacabados
        $sql_consumo = "SELECT
                            gm.nome_grupo as linha,
                            DAY(cp.data_consumo) as dia,
                            SUM(calcularVolume(cp.quantidade_consumida, p.espessura, p.largura, p.comprimento)) as volume_consumido
                        FROM consumo_producao cp
                        JOIN produtos p ON cp.produto_material_id = p.id
                        JOIN ordens_producao op ON cp.ordem_producao_id = op.id
                        JOIN grupos_maquinas gm ON op.grupo_id = gm.id
                        WHERE cp.data_consumo BETWEEN ? AND ? AND EXISTS (SELECT 1 FROM roteiros r WHERE r.produto_id = p.id) AND cp.deleted_at IS NULL
                        GROUP BY linha, dia";

        $consumo_result = $conn->execute_query($sql_consumo, [$data_inicio, $data_fim])->fetch_all(MYSQLI_ASSOC);

        // 3. Processa e junta os dados em uma estrutura única
        $linhas = $conn->query("SELECT nome_grupo FROM grupos_maquinas WHERE deleted_at IS NULL ORDER BY nome_grupo")->fetch_all(MYSQLI_ASSOC);
        foreach($linhas as $linha) {
            $nome_linha = $linha['nome_grupo'];
            for ($i = 1; $i <= $dias_no_mes; $i++) {
                $dados_relatorio[$nome_linha]['producao'][$i] = 0;
                $dados_relatorio[$nome_linha]['consumo'][$i] = 0;
            }
        }

        foreach($producao_result as $row) {
            $dados_relatorio[$row['linha']]['producao'][$row['dia']] = (float)$row['volume_produzido'];
        }
        foreach($consumo_result as $row) {
            $dados_relatorio[$row['linha']]['consumo'][$row['dia']] = (float)$row['volume_consumido'];
        }

    } catch (Exception $e) {
        $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
    }
}
?>

<style>
    .report-table { width: 100%; border-collapse: collapse; }
    .report-table th, .report-table td { border: 1px solid #ccc; padding: 6px; text-align: center; font-size: 12px; }
    .report-table th { background-color: #f2f2f2; }
    .report-table .label-col { text-align: left; font-weight: bold; }
    .linha-separator { border-bottom: 3px solid #333 !important; }
    @media print {
        @page { size: landscape; }
        body { font-size: 10px; }
        .no-print { display: none; }
        .card { border: none; box-shadow: none; }
    }
</style>

<div class="container-fluid mt-4">
    <h2><i class="fas fa-chart-line"></i> Relatório de Desempenho Mensal por Linha</h2>

    <div class="card mb-4 no-print">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_desempenho_mensal.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="mes" class="form-label">Mês</label>
                        <select name="mes" class="form-select">
                            <?php for($i=1; $i<=12; $i++): ?>
                                <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?php echo ($mes_selecionado == $i) ? 'selected' : ''; ?>><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="ano" class="form-label">Ano</label>
                        <select name="ano" class="form-select">
                            <?php for($i=date('Y'); $i>=date('Y')-5; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($ano_selecionado == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_desempenho_mensal.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar']) && !empty($dados_relatorio)): ?>
    <div class="card">
        <div class="card-header">
            Desempenho de <?php echo "$mes_selecionado/$ano_selecionado"; ?>
            <button onclick="window.print()" class="button small no-print" style="float: right;">Imprimir</button>
        </div>
        <div class="card-body">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Linha de Produção</th>
                        <th>Indicador</th>
                        <?php for ($i = 1; $i <= $dias_no_mes; $i++) echo "<th>$i</th>"; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dados_relatorio as $linha => $dados): 
                        $total_producao = array_sum($dados['producao']);
                        $total_consumo = array_sum($dados['consumo']);
                        $rendimento_total = ($total_producao > 0) ? ($total_consumo / $total_producao) * 100 : 0;
                    ?>
                        <tr>
                            <td rowspan="3" class="label-col" style="vertical-align: middle;"><?php echo htmlspecialchars($linha); ?></td>
                            <td class="label-col">Produção (M³)</td>
                            <?php foreach($dados['producao'] as $valor) echo "<td>" . ($valor > 0 ? number_format($valor, 4, ',', '.') : '') . "</td>"; ?>
                            <td><?php echo number_format($total_producao, 4, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td class="label-col">Consumo (M³)</td>
                            <?php foreach($dados['consumo'] as $valor) echo "<td>" . ($valor > 0 ? number_format($valor, 4, ',', '.') : '') . "</td>"; ?>
                            <td><?php echo number_format($total_consumo, 4, ',', '.'); ?></td>
                        </tr>
                        <tr class="linha-separator">
                            <td class="label-col">Rendimento (%)</td>
                            <?php for($i=1; $i<=$dias_no_mes; $i++): 
                                $rendimento_dia = ($dados['producao'][$i] > 0) ? ($dados['consumo'][$i] / $dados['producao'][$i]) * 100 : 0;
                            ?>
                                <td><?php echo ($rendimento_dia > 0) ? number_format($rendimento_dia, 2, ',', '.') . '%' : ''; ?></td>
                            <?php endfor; ?>
                            <td><?php echo number_format($rendimento_total, 2, ',', '.'); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="print-timestamp" style="text-align: right; font-size: 10px; margin-top: 10px;">
                Impresso em: <?php echo date('d/m/Y H:i:s'); ?>
            </div>
        </div>
    </div>
    <?php elseif (isset($_GET['filtrar'])): ?>
        <p class="text-center mt-3">Nenhum dado encontrado para o período selecionado.</p>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4 no-print">Voltar ao Portal de Relatórios</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
