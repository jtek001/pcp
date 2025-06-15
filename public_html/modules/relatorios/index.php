<?php
// /modules/relatorios/index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Lista de relatórios disponíveis. Podemos adicionar mais aqui no futuro.
$relatorios_disponiveis = [
    [
        'titulo' => 'Produção por Período',
        'descricao' => 'Gera um relatório detalhado de todos os apontamentos de produção realizados num intervalo de datas específico.',
        'link' => 'relatorio_producao_periodo.php',
        'icone' => 'fa-calendar-alt' // Ícone do Font Awesome
    ],
    // Futuramente, outros relatórios podem ser adicionados aqui
    // [
    //     'titulo' => 'Consumo de Insumos por OP',
    //     'descricao' => 'Detalha todos os insumos consumidos para uma Ordem de Produção específica.',
    //     'link' => 'relatorio_consumo_op.php',
    //     'icone' => 'fa-cogs'
    // ],
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
