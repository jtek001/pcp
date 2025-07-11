<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-shipping-fast"></i> Módulo de Expedição</h2>
    <p class="lead">Gestão de entrada e saída de produtos acabados.</p>

    <div class="row mt-4">
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column">
                    <i class="fas fa-sign-in-alt fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Entrada na Expedição</h5>
                    <p class="card-text text-muted">Registe a entrada de lotes de produção no estoque da expedição.</p>
                    <div class="mt-auto">
                        <a href="entrada.php" class="button add">Registrar Entrada</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column">
                    <i class="fas fa-sign-out-alt fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Saída da Expedição</h5>
                    <p class="card-text text-muted">Registe a saída de produtos para clientes, com base na nota fiscal.</p>
                    <div class="mt-auto">
                        <a href="saida.php" class="button">Registrar Saída</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column">
                    <i class="fas fa-history fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Histórico de Movimentações</h5>
                    <p class="card-text text-muted">Consulte e estorne as entradas e saídas da expedição.</p>
                    <div class="mt-auto">
                        <a href="historico.php" class="button">Ver Histórico</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column">
                    <i class="fas fa-warehouse fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Inventário da Expedição</h5>
                    <p class="card-text text-muted">Consulte o saldo de todos os lotes disponíveis na área de expedição.</p>
                    <div class="mt-auto">
                        <a href="../relatorios/relatorio_inventario_expedicao.php" class="button">Consultar Inventário</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column">
                    <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Relatório de Expedidos</h5>
                    <p class="card-text text-muted">Analise todos os produtos que já saíram para os clientes.</p>
                    <div class="mt-auto">
                        <a href="../relatorios/relatorio_produtos_expedidos.php" class="button">Ver Relatório</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
