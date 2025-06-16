<?php
// /modules/relatorios/index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Lista de relatórios disponíveis.
$relatorios_disponiveis = [
    [
        'titulo' => 'Produção por Período',
        'descricao' => 'Gera um relatório detalhado de todos os apontamentos de produção realizados num intervalo de datas específico.',
        'link' => 'relatorio_producao_periodo.php',
        'icone' => 'fa-calendar-alt'
    ],
    [
        'titulo' => 'Entrada de Matéria-Prima',
        'descricao' => 'Relatório de todas as matérias-primas que deram entrada no estoque, filtrado por período e produto.',
        'link' => 'relatorio_entradas_materiais.php',
        'icone' => 'fa-truck-loading'
    ],
    [
        'titulo' => 'Posição de Estoque',
        'descricao' => 'Exibe o saldo atual de todos os produtos, com filtros por categoria e indicadores de estoque baixo.',
        'link' => 'relatorio_estoque.php',
        'icone' => 'fa-boxes-stacked'
    ],
    [
        'titulo' => 'Relatório de Paradas',
        'descricao' => 'Analisa o tempo total e os principais motivos das paradas de máquina em um período.',
        'link' => '../manutencao/relatorio_paradas.php', // Link para o relatório no módulo de manutenção
        'icone' => 'fa-tools'
    ],
    [
        'titulo' => 'Inventário de Produção',
        'descricao' => 'Lista os lotes produzidos que ainda não foram consumidos e estão disponíveis em estoque.',
        'link' => 'relatorio_inventario.php',
        'icone' => 'fa-clipboard-list'
    ]
];

?>

<div class="container mt-4">
    <h2><i class="fas fa-chart-bar"></i> Módulo de Relatórios</h2>
    <p class="lead">Selecione um dos relatórios abaixo para visualizar os dados de produção e controle.</p>

    <div class="row mt-4">
        <?php foreach ($relatorios_disponiveis as $relatorio): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="text-center mb-3">
                            <i class="fas <?php echo $relatorio['icone']; ?> fa-3x text-primary"></i>
                        </div>
                        <h5 class="card-title text-center"><?php echo htmlspecialchars($relatorio['titulo']); ?></h5>
                        <p class="card-text text-muted">
                            <?php echo htmlspecialchars($relatorio['descricao']); ?>
                        </p>
                        <div class="mt-auto text-center">
                            <a href="<?php echo htmlspecialchars($relatorio['link']); ?>" class="button add">Gerar Relatório</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
