<?php
// /modules/manutencao/index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Lista de funcionalidades de manutenão.
$funcionalidades_manutencao = [
    [
        'titulo' => 'Jornada de Máquina',
        'descricao' => 'Registe o início e o fim da jornada de trabalho em cada máquina para controlo de tempo de uso.',
        'link' => 'jornada/index.php',
        'icone' => 'fa-calendar-check'
    ],
    [
        'titulo' => 'Paradas de Máquina',
        'descricao' => 'Registe e acompanhe os motivos e a duração das paradas de cada máquina para análise de eficiência.',
        'link' => 'paradas/index.php',
        'icone' => 'fa-stopwatch-20' 
    ],
    [
        'titulo' => 'Motivos de Parada',
        'descricao' => 'Crie e gerencie os motivos padronizados para as paradas de máquina.',
        'link' => 'motivos/index.php',
        'icone' => 'fa-tags'
    ],
     [
        'titulo' => 'Grupos de Máquinas',
        'descricao' => 'Crie e gerencie os grupos de máquinas.',
        'link' => 'grupos/index.php',
        'icone' => 'fa-layer-group'
    ],
    [
        'titulo' => 'Linha do Tempo',
        'descricao' => 'Visualize todos os eventos de uma máquina (jornadas, produções, paradas) em ordem cronológica.',
        'link' => '../relatorios/relatorio_linha_do_tempo.php',
        'icone' => 'fa-history'
    ],
  [
        'titulo' => 'Relatório de Paradas',
        'descricao' => 'Analise o tempo total de parada por máquina e motivo em um determinado período.',
        'link' => 'relatorio_paradas.php',
        'icone' => 'fa-chart-line'
    ]
];

?>

<div class="container mt-4">
    <h2><i class="fas fa-tools"></i> Módulo de Manutenção</h2>
    <p class="lead">Ferramentas para gestão e controlo da manutenão de equipamentos.</p>

    <div class="row mt-4">
        <?php foreach ($funcionalidades_manutencao as $funcionalidade): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="text-center mb-3">
                            <i class="fas <?php echo $funcionalidade['icone']; ?> fa-3x text-primary"></i>
                        </div>
                        <h5 class="card-title text-center"><?php echo htmlspecialchars($funcionalidade['titulo']); ?></h5>
                        <p class="card-text text-muted">
                            <?php echo htmlspecialchars($funcionalidade['descricao']); ?>
                        </p>
                        <div class="mt-auto text-center">
                            <a href="<?php echo htmlspecialchars($funcionalidade['link']); ?>" class="button add">Acessar</a>
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
