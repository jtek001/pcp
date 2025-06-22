<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_inicial_form = $_GET['data_inicial'] ?? date('Y-m-01\T00:00');
$data_final_form = $_GET['data_final'] ?? date('Y-m-t\T23:59');

// --- Lógica para buscar dados para os filtros ---
$sql_materias_primas = "SELECT id, nome, codigo FROM produtos WHERE UPPER(familia) = 'MATERIA-PRIMA' AND deleted_at IS NULL ORDER BY nome";
$materias_primas = $conn->query($sql_materias_primas)->fetch_all(MYSQLI_ASSOC);

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
                        DATE(mov.data_hora_movimentacao) as dia,
                        p.nome as produto_nome,
                        p.codigo as produto_codigo,
                        p.unidade_medida,
                        SUM(mov.quantidade) AS total_quantidade
                     FROM movimentacoes_estoque mov
                     JOIN produtos p ON mov.produto_id = p.id
                     WHERE mov.tipo_movimentacao = 'entrada' AND UPPER(p.familia) = 'MATERIA-PRIMA'";

        if (!empty($_GET['data_inicial'])) {
            $sql_base .= " AND mov.data_hora_movimentacao >= ?";
            $params[] = $_GET['data_inicial'];
            $types .= 's';
            $filtros_aplicados['Data Inicial'] = date('d/m/Y H:i', strtotime($_GET['data_inicial']));
        }
        if (!empty($_GET['data_final'])) {
            $sql_base .= " AND mov.data_hora_movimentacao <= ?";
            $params[] = $_GET['data_final'];
            $types .= 's';
            $filtros_aplicados['Data Final'] = date('d/m/Y H:i', strtotime($_GET['data_final']));
        }
        if (!empty($_GET['produto_id'])) {
            $sql_base .= " AND mov.produto_id = ?";
            $params[] = $_GET['produto_id'];
            $types .= 'i';
            $prod_res = $conn->execute_query("SELECT nome FROM produtos WHERE id = ?", [$_GET['produto_id']]);
            if ($prod_row = $prod_res->fetch_assoc()) $filtros_aplicados['Matéria-Prima'] = $prod_row['nome'];
        }

        $sql_base .= " GROUP BY DATE(mov.data_hora_movimentacao), p.nome, p.codigo, p.unidade_medida ORDER BY dia ASC, produto_nome";

        $stmt = $conn->prepare($sql_base);
        if ($stmt === false) throw new Exception("Erro ao preparar a consulta SQL: " . $conn->error);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        $dados_relatorio = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Agrega dados para o gráfico (somatrio diário)
        $grafico_agregado = [];
        foreach ($dados_relatorio as $dado) {
            $dia = date('d/m/Y', strtotime($dado['dia']));
            if (!isset($grafico_agregado[$dia])) {
                $grafico_agregado[$dia] = 0;
            }
            $grafico_agregado[$dia] += $dado['total_quantidade'];
        }
        $dados_grafico['labels'] = array_keys($grafico_agregado);
        $dados_grafico['data'] = array_values($grafico_agregado);

    } catch (Exception $e) {
        $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-chart-bar"></i>Relatório de Entradas de Matéria-Prima</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros do Relatório</div>
        <div class="card-body">
            <form action="relatorio_entradas_materiais.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="data_inicial" class="form-label">Data Inicial</label>
                        <input type="datetime-local" class="form-control" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_form); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="data_final" class="form-label">Data Final</label>
                        <input type="datetime-local" class="form-control" name="data_final" value="<?php echo htmlspecialchars($data_final_form); ?>">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="produto_id" class="form-label">Matéria-Prima</label>
                        <select name="produto_id" class="form-select">
                            <option value="">Todas</option>
                             <?php foreach($materias_primas as $produto): ?>
                                <option value="<?php echo $produto['id']; ?>" <?php echo (($_GET['produto_id'] ?? '') == $produto['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($produto['nome'] . ' (' . $produto['codigo'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_entradas_materiais.php" class="button button-clear">Limpar Filtros</a>
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
                <h4>Entradas Detalhadas</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Matéria-Prima</th>
                            <th>Unidade</th>
                            <th class="text-end">Quantidade Recebida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $total_geral = 0; foreach ($dados_relatorio as $dado): $total_geral += $dado['total_quantidade']; ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($dado['dia'])); ?></td>
                            <td><?php echo htmlspecialchars($dado['produto_nome'] . ' (' . $dado['produto_codigo'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($dado['unidade_medida']); ?></td>
                            <td class="text-end"><?php echo number_format($dado['total_quantidade'], 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td colspan="3">TOTAL GERAL</td>
                            <td class="text-end"><?php echo number_format($total_geral, 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
                <h4 class="mt-4">Gráfico de Entradas Diárias</h4>
                <canvas id="graficoEntradas" style="max-height: 400px;"></canvas>
            </div>
        </div>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Relatórios</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('graficoEntradas');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dados_grafico['labels'] ?? []); ?>,
                datasets: [{
                    label: 'Quantidade Recebida',
                    data: <?php echo json_encode($dados_grafico['data'] ?? []); ?>,
                    backgroundColor: 'rgba(39, 174, 96, 0.7)'
                }]
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
$conn->close();
?>
