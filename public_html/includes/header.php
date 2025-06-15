<?php
// includes/header.php
// Este arquivo incluirá o cabeçalho HTML e o menu de navegação.

// Garante que o arquivo de configuração, que define BASE_URL, seja incluído
require_once __DIR__ . '/../config/database.php';

// Inicia a sessão (se j não estiver iniciada) - CRÍTICO para autenticação
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Determina o módulo atual para destacar no menu
// Usa a superglobal $_GET para identificar o módulo atual
$current_module = isset($_GET['module']) ? $_GET['module'] : 'home';

// Proteção de Página e Redirecionamento de Login
$public_pages = [
    BASE_URL . '/public/login.php',
    BASE_URL . '/public/index.php', // A página inicial pode ser pblica ou exigir login se houver dashboard sensível
];

$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Se o usuário não est logado E a página atual NÃO é uma página pública (de login ou index)
// Isso é uma proteção básica. Para produção, considere um arquivo de roteamento ou middleware.
if (!isset($_SESSION['user_id']) && !in_array($current_url, $public_pages) && strpos($_SERVER['REQUEST_URI'], '/login.php') === false) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de PCP - <?php echo htmlspecialchars(COMPANY_NAME); ?></title>
    <!-- Inclui a folha de estilos CSS externa usando a BASE_URL -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style.css">
</head>
<body>
    <header>
        <nav>
            <ul id="main-nav-list">
                <?php if (isset($_SESSION['user_id'])): // Mostra links apenas se o usurio estiver logado ?>
                    <li><a href="<?php echo BASE_URL; ?>/public/index.php?module=home" class="<?php echo ($current_module === 'home') ? 'active' : ''; ?>">Início</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/produtos/index.php?module=produtos" class="<?php echo ($current_module === 'produtos') ? 'active' : ''; ?>">Produtos</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/maquinas/index.php?module=maquinas" class="<?php echo ($current_module === 'maquinas') ? 'active' : ''; ?>">Máquinas</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/ordens_producao/index.php?module=ordens_producao" class="<?php echo ($current_module === 'ordens_producao') ? 'active' : ''; ?>">Ordens de Produção</a></li>
                    <?php if (isset($_SESSION['user_cargo']) && $_SESSION['user_cargo'] === 'admin'): // Link Operadores apenas para admin ?>
                    <li><a href="<?php echo BASE_URL; ?>/modules/operadores/index.php?module=operadores" class="<?php echo ($current_module === 'operadores') ? 'active' : ''; ?>">Operadores</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo BASE_URL; ?>/modules/materiais/index.php?module=materiais" class="<?php echo ($current_module === 'materiais') ? 'active' : ''; ?>">Materiais</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/bom/index.php?module=bom" class="<?php echo ($current_module === 'bom') ? 'active' : ''; ?>">Lista de Materiais (BoM)</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/estoque/index.php?module=estoque" class="<?php echo ($current_module === 'estoque') ? 'active' : ''; ?>">Estoque</a></li> 
                    <li><a href="<?php echo BASE_URL; ?>/modules/empenho_manual/index.php?module=empenho_manual" class="<?php echo ($current_module === 'empenho_manual') ? 'active' : ''; ?>">Empenho Manual</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/fornecedores_clientes/index.php?module=fornecedores_clientes" class="<?php echo ($current_module === 'fornecedores_clientes') ? 'active' : ''; ?>">Fornecedores/Clientes</a></li> 
                    <li><a href="<?php echo BASE_URL; ?>/modules/chao_de_fabrica/index.php?module=chao_de_fabrica" class="<?php echo ($current_module === 'chao_de_fabrica') ? 'active' : ''; ?>">Cho de Fábrica</a></li> 
                    <li><a href="<?php echo BASE_URL; ?>/public/logout.php" class="">Sair</a></li>
                <?php else: ?>
                    <!-- Links visíveis para usuários não logados (apenas a tela de login) -->
                    <li><a href="<?php echo BASE_URL; ?>/public/login.php" class="<?php echo (strpos($_SERVER['REQUEST_URI'], 'login.php') !== false) ? 'active' : ''; ?>">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
        <!-- A área principal do conteúdo será fechada em footer.php -->
    <script>
        // Removido o menu-toggle e a lógica de toggleMenu() e closeMenu() daqui
        // pois o menu lateral será desfeito.
    </script>
