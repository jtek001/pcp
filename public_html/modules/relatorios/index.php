<?php
// /modules/relatorios/index.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// Lista de relatórios disponíveis.
$relatorios_disponiveis = [
  [
        'titulo' => 'Entrada de Matéria-Prima',
        'descricao' => 'Relatório de todas as matérias-primas que deram entrada no estoque.',
        'link' => 'relatorio_entradas_materiais.php',
        'icone' => 'fa-truck-loading'
    ],
    [
        'titulo' => 'Relatório de Produção',
        'descricao' => 'Analise a produção com filtros avançados por linha, máquina, produto e pedido.',
        'link' => 'relatorio_producao_detalhado.php',
        'icone' => 'fa-search'
    ],
        [
        'titulo' => 'Consumo de Semiacabados',
        'descricao' => 'Analisa o consumo de componentes semiacabados no processo produtivo.',
        'link' => 'relatorio_consumo_semiacabados.php',
        'icone' => 'fa-puzzle-piece'
    ],
   [
        'titulo' => 'Consumo de Matéria-Prima',
        'descricao' => 'Analisa o consumo de matérias-primas e componentes no processo produtivo.',
        'link' => 'relatorio_consumo_materia_prima.php',
        'icone' => 'fa-industry'
    ],
    [
        'titulo' => 'Posição de Estoque',
        'descricao' => 'Exibe o saldo atual de todos os produtos, com filtros por categoria.',
        'link' => 'relatorio_estoque.php',
        'icone' => 'fa-boxes-stacked'
    ],
    [
        'titulo' => 'Relatório de Paradas',
        'descricao' => 'Analisa o tempo total e os principais motivos das paradas de máquina.',
        'link' => '../manutencao/relatorio_paradas.php',
        'icone' => 'fa-tools'
    ],
    [
        'titulo' => 'Inventário de Produção',
        'descricao' => 'Lista os lotes produzidos que ainda não foram consumidos e estão em estoque.',
        'link' => 'relatorio_inventario.php',
        'icone' => 'fa-clipboard-list'
    ],
    [
        'titulo' => 'Inventário da Expedição',
        'descricao' => 'Lista os lotes de produtos acabados que estão na área de expedição.',
        'link' => 'relatorio_inventario_expedicao.php',
        'icone' => 'fa-warehouse'
    ],
    [
        'titulo' => 'Produtos Expedidos',
        'descricao' => 'Analisa todos os produtos que j saíram para os clientes.',
        'link' => 'relatorio_produtos_expedidos.php',
        'icone' => 'fa-shipping-fast'
    ],
  [
        'titulo' => 'Linha do Tempo da Máquina',
        'descricao' => 'Visualize todos os eventos de uma máquina (jornadas, produções, paradas) em ordem cronológica.',
        'link' => 'relatorio_linha_do_tempo.php',
        'icone' => 'fa-history'
    ],
    [
        'titulo' => 'Pedidos Finalizados',
        'descricao' => 'Lista todos os pedidos de venda que já foram concluídos e entregues.',
        'link' => 'relatorio_pedidos_finalizados.php',
        'icone' => 'fa-check-double'
    ]
];

?>

<div class="container mt-4">
    <h2><i class="fas fa-chart-bar"></i> Módulo de Relatórios</h2>
    <p class="lead">Selecione um dos relatórios abaixo para visualizar os dados de produão e controle.</p>

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
