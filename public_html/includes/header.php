<?php
// Garante que a sessão seja iniciada em todas as páginas que incluem o header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário est logado. Se não, redireciona para a pgina de login.
$current_page_name = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION["user_id"]) && $current_page_name != 'login.php') {
    header("location: " . (defined('BASE_URL') ? BASE_URL : '') . "/public/login.php");
    exit;
}

// Determina se um link do menu ou dropdown deve ser marcado como 'ativo'
function is_active($modules) {
    if (!is_array($modules)) {
        $modules = [$modules];
    }
    
    $current_uri = $_SERVER['REQUEST_URI'];

    foreach ($modules as $module) {
        // Condição especial para o Dashboard
        if ($module === 'dashboard' && strpos($current_uri, '/public/index.php') !== false) {
            return 'active';
        }
        // Para os outros módulos, verifica se a pasta do módulo está na URL
        if ($module !== 'dashboard' && strpos($current_uri, '/modules/' . $module . '/') !== false) {
            return 'active';
        }
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCP System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Seu CSS personalizado -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style.css">
    
    <link rel="icon" type="image/x-icon" href="/public/img/pcp-sys.png">
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #2c3e50;">
            <div class="container-fluid">
            
                <a class="navbar-brand" href="<?php echo BASE_URL; ?>/public/index.php">
                    <img src="<?php echo BASE_URL; ?>/public/img/pcp-system1.png" height="35" title="Inicio" />
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_active('dashboard'); ?>" href="<?php echo BASE_URL; ?>/public/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                        </li>
                        
                        <!-- Menu Cadastros -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo is_active(['produtos', 'maquinas', 'bom', 'fornecedores_clientes', 'operadores', 'roteiros']); ?>" href="#" id="navbarDropdownCadastros" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-edit"></i> Cadastros
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownCadastros">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/produtos/index.php">Produtos</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/maquinas/index.php">Máquinas</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/bom/index.php">Lista de Materiais (BoM)</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/roteiros/index.php">Roteiros de Produção</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/fornecedores_clientes/index.php">Clientes & Fornecedores</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/operadores/index.php">Operadores</a></li>
                            </ul>
                        </li>

                        <!-- Menu Vendas -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo is_active(['pedidos_venda']); ?>" href="#" id="navbarDropdownVendas" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-dollar-sign"></i> Vendas
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownVendas">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/pedidos_venda/index.php">Pedidos de Venda</a></li>
                            </ul>
                        </li>

                        <!-- Menu Produço -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo is_active(['ordens_producao', 'chao_de_fabrica']); ?>" href="#" id="navbarDropdownProducao" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-industry"></i> Produção
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownProducao">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/ordens_producao/index.php">Ordens de Produão</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/chao_de_fabrica/index.php">Chão de Fábrica</a></li>
                            </ul>
                        </li>
                        
                        <!-- Menu Manutenção -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo is_active(['manutencao']); ?>" href="#" id="navbarDropdownManutencao" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-tools"></i> Manutenço
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownManutencao">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/manutencao/grupos/index.php">Grupos de Máquinas</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/manutencao/index.php">Controle de Paradas</a></li>
                            </ul>
                        </li>

                        <!-- Menu Estoque -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo is_active(['estoque', 'entradas_materiais', 'empenho_manual']); ?>" href="#" id="navbarDropdownEstoque" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-boxes-stacked"></i> Estoque
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownEstoque">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/estoque/index.php">Visão Geral</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/entradas_materiais/index.php">Entrada de Materiais</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/empenho_manual/index.php">Empenho Manual</a></li>
                            </ul>
                        </li>

                        <!-- Menu Expedião -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo is_active(['expedicao']); ?>" href="#" id="navbarDropdownExpedicao" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-shipping-fast"></i> Expedião
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownExpedicao">
                              <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/expedicao/index.php">Visão Geral</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/expedicao/entrada.php">Entrada na Expedição</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/expedicao/saida.php">Saída da Expediço</a></li>
                                
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?php echo is_active('relatorios'); ?>" href="<?php echo BASE_URL; ?>/modules/relatorios/index.php"><i class="fas fa-chart-pie"></i> Relatórios</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                         <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/public/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main>
