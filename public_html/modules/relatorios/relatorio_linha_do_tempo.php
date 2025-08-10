<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$conn = connectDB();

// --- Define as datas padrão para o formulário ---
$data_filtro = $_GET['data'] ?? date('Y-m-d');

// --- Lógica para buscar dados para os filtros ---
$maquinas = $conn->query("SELECT id, nome FROM maquinas WHERE deleted_at IS NULL ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);

// --- Lógica de Busca de Dados do Relatório ---
$dados_relatorio = [];
$filtros_aplicados = [];
$error_message = '';

if (isset($_GET['filtrar'])) {
    $maquina_id = filter_input(INPUT_GET, 'maquina_id', FILTER_VALIDATE_INT);
    
    if ($maquina_id) {
        try {
            $filtros_aplicados['Data'] = date('d/m/Y', strtotime($data_filtro));
            $filtros_aplicados['Máquina'] = $conn->execute_query("SELECT nome FROM maquinas WHERE id = ?", [$maquina_id])->fetch_assoc()['nome'];

            // OBSERVAÇÃO: A consulta foi atualizada para filtrar registros com deleted_at IS NULL em todas as subconsultas.
            $sql = "
                (SELECT j.data_hora_inicio as data_evento, 'Jornada' as tipo_evento, CONCAT('Início - Operador: ', o.nome) as descricao, t.nome_turno FROM maquina_jornada_log j JOIN operadores o ON j.operador_id = o.id JOIN turnos t ON j.turno_id = t.id WHERE j.maquina_id = ? AND DATE(j.data_hora_inicio) = ?)
                UNION ALL
                (SELECT j.data_hora_fim as data_evento, 'Jornada' as tipo_evento, 'Fim da Jornada' as descricao, t.nome_turno FROM maquina_jornada_log j JOIN turnos t ON j.turno_id = t.id WHERE j.maquina_id = ? AND DATE(j.data_hora_fim) = ? AND j.data_hora_fim IS NOT NULL)
                UNION ALL
                (SELECT ap.data_apontamento as data_evento, 'Produção' as tipo_evento, CONCAT('Produzido: ', ap.quantidade_produzida, ' de ', p.nome, ' (Lote: ', ap.lote_numero, ')') as descricao, t.nome_turno FROM apontamentos_producao ap JOIN ordens_producao op ON ap.ordem_producao_id = op.id JOIN produtos p ON op.produto_id = p.id JOIN turnos t ON ap.turno_id = t.id WHERE ap.maquina_id = ? AND DATE(ap.data_apontamento) = ? AND ap.deleted_at IS NULL)
                UNION ALL
                (SELECT cp.data_consumo as data_evento, 'Consumo Semiacabado' as tipo_evento, CONCAT('Consumido: ', cp.quantidade_consumida, ' de ', p.nome, ' (Lote: ', ap.lote_numero, ')') as descricao, t.nome_turno FROM consumo_producao cp JOIN produtos p ON cp.produto_material_id = p.id LEFT JOIN apontamentos_producao ap ON cp.apontamento_id = ap.id JOIN turnos t ON cp.turno_id = t.id WHERE cp.maquina_id = ? AND DATE(cp.data_consumo) = ? AND cp.deleted_at IS NULL)
                UNION ALL
                (SELECT cp.data_consumo as data_evento, 'Consumo Insumo' as tipo_evento, CONCAT('Consumo de Insumo: ', cp.quantidade_consumida, ' de ', p.nome, ' para a OP ', op.numero_op) as descricao, t.nome_turno FROM consumo_producao cp JOIN produtos p ON cp.produto_material_id = p.id JOIN ordens_producao op ON cp.ordem_producao_id = op.id JOIN turnos t ON cp.turno_id = t.id WHERE cp.maquina_id IS NULL AND cp.apontamento_id IS NULL AND DATE(cp.data_consumo) = ? AND op.id IN (SELECT DISTINCT ap.ordem_producao_id FROM apontamentos_producao ap WHERE ap.maquina_id = ? AND DATE(ap.data_apontamento) = ?) AND cp.deleted_at IS NULL)
                UNION ALL
                (SELECT pm.data_hora_inicio as data_evento, 'Parada' as tipo_evento, CONCAT('Início da Parada - Motivo: ', mp.nome) as descricao, t.nome_turno FROM paradas_maquina pm JOIN motivos_parada mp ON pm.motivo_id = mp.id JOIN turnos t ON pm.turno_id = t.id WHERE pm.maquina_id = ? AND DATE(pm.data_hora_inicio) = ? AND pm.deleted_at IS NULL)
                UNION ALL
                (SELECT pm.data_hora_fim as data_evento, 'Parada' as tipo_evento, 'Fim da Parada' as descricao, t.nome_turno FROM paradas_maquina pm JOIN turnos t ON pm.turno_id = t.id WHERE pm.maquina_id = ? AND DATE(pm.data_hora_fim) = ? AND pm.data_hora_fim IS NOT NULL AND pm.deleted_at IS NULL)
                UNION ALL
                (SELECT jpl.data_hora_inicio_pausa as data_evento, 'Pausa' as tipo_evento, CONCAT('Início da Pausa - Duração: ', jpl.duracao_minutos, ' min') as descricao, t.nome_turno FROM jornada_pausas_log jpl JOIN maquina_jornada_log mjl ON jpl.jornada_log_id = mjl.id JOIN turnos t ON mjl.turno_id = t.id WHERE mjl.maquina_id = ? AND DATE(jpl.data_hora_inicio_pausa) = ?)
                ORDER BY data_evento ASC
            ";

            $params = [
                $maquina_id, $data_filtro, // Jornada Início
                $maquina_id, $data_filtro, // Jornada Fim
                $maquina_id, $data_filtro, // Produção
                $maquina_id, $data_filtro, // Consumo Semiacabado
                $data_filtro, $maquina_id, $data_filtro, // Consumo Insumo
                $maquina_id, $data_filtro, // Parada Início
                $maquina_id, $data_filtro, // Parada Fim
                $maquina_id, $data_filtro  // Pausa
            ];
            
            $dados_relatorio = $conn->execute_query($sql, $params)->fetch_all(MYSQLI_ASSOC);

        } catch (Exception $e) {
            $error_message = "Ocorreu um erro ao gerar o relatório: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <h2><i class="fas fa-history"></i> Linha do Tempo da Máquina</h2>

    <div class="card mb-4">
        <div class="card-header">Filtros</div>
        <div class="card-body">
            <form action="relatorio_linha_do_tempo.php" method="GET">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" class="form-control" name="data" value="<?php echo htmlspecialchars($data_filtro); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="maquina_id" class="form-label">Máquina</label>
                        <select name="maquina_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach($maquinas as $maquina): ?>
                                <option value="<?php echo $maquina['id']; ?>" <?php echo (($_GET['maquina_id'] ?? '') == $maquina['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($maquina['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="filtrar" class="button add">Gerar Relatório</button>
                <a href="relatorio_linha_do_tempo.php" class="button button-clear">Limpar Filtros</a>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['filtrar'])): ?>
    <div class="card">
        <div class="card-header">Linha do Tempo</div>
        <div class="card-body">
            <?php if(!empty($filtros_aplicados)): ?>
                <p><strong>Filtros aplicados:</strong> <?php echo implode('; ', array_map(fn($k, $v) => "<strong>$k:</strong> " . htmlspecialchars($v), array_keys($filtros_aplicados), $filtros_aplicados)); ?></p><hr>
            <?php endif; ?>
            <?php if (!empty($dados_relatorio)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Tipo de Evento</th>
                            <th>Turno</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados_relatorio as $item): ?>
                        <tr>
                            <td><?php echo date('H:i:s', strtotime($item['data_evento'])); ?></td>
                            <td><span class="status-<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($item['tipo_evento']))); ?>"><?php echo htmlspecialchars($item['tipo_evento']); ?></span></td>
                            <td><?php echo htmlspecialchars($item['nome_turno'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['descricao']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center mt-3">Nenhum evento encontrado para esta máquina nesta data.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">Selecione uma data e uma máquina para gerar a linha do tempo.</div>
    <?php endif; ?>
    
    <a href="index.php" class="back-link mt-4">Voltar ao Portal de Relatórios</a>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
