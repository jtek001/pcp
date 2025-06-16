Documentação Técnica: Sistema de PCP
1. Visão Geral da Arquitetura
O sistema foi desenvolvido utilizando a pilha LAMP (Linux, Apache, MySQL, PHP), uma das arquiteturas mais consolidadas e robustas para aplicações web. A abordagem segue um modelo MVC (Model-View-Controller) simplificado, onde a lógica de negócio, a apresentação e o acesso aos dados são mantidos em camadas distintas.

Backend: PHP 8 (com a extensão MySQLi) é responsável por toda a lógica de negócio, processamento de formulários, interações com o banco de dados e gestão de sessões.

Frontend: HTML5, CSS3, e JavaScript (ES6+) são utilizados para criar a interface do utilizador. A estilização base e a responsividade são auxiliadas pelo framework Bootstrap 5, enquanto a interatividade dinâmica é construída com JavaScript puro (Vanilla JS).

Banco de Dados: MySQL (MariaDB) armazena todos os dados da aplicação, desde cadastros simples até registos complexos de produção e estoque. A integridade dos dados é reforçada por funções e gatilhos (triggers) a nível do banco.

Comunicação Cliente-Servidor: A comunicação assíncrona é realizada através da Fetch API do JavaScript, que faz requisições a endpoints PHP específicos para obter dados (ex: buscar produtos, clientes) sem a necessidade de recarregar a página.

2. Detalhes do Backend (PHP e MySQL)
2.1. Estrutura de Ficheiros
O projeto é organizado numa estrutura modular para facilitar a manutenção e a escalabilidade:

/config/database.php: Ficheiro central que define as constantes de conexão com o banco de dados (DB_HOST, DB_USER, etc.) e a função connectDB() para estabelecer a conexão via MySQLi. Também define a BASE_URL para a construção de links absolutos.

/includes/: Contém ficheiros de template reutilizáveis, como o header.php e o footer.php, que são incluídos em todas as páginas para manter a consistência visual.

/public/: É a raiz acessível pela web, contendo a index.php (Dashboard) e a página de login.php.

/modules/: O coração do sistema, onde cada funcionalidade (ex: produtos, ordens_producao, manutencao) reside na sua própria pasta, seguindo um padrão de ficheiros (ex: index.php para listar, adicionar.php para criar, editar.php para atualizar).

2.2. Interação com o Banco de Dados
Conexão (MySQLi): A conexão é gerida pela função connectDB() em database.php, que utiliza a extensão MySQLi Orientada a Objetos. Esta abordagem é moderna e segura.

Segurança contra SQL Injection: Todas as consultas que envolvem dados do utilizador são executadas com Prepared Statements através do método $conn->execute_query(). Isto separa a consulta SQL dos dados, tornando a aplicação imune a ataques de injeção de SQL.

Funções e Gatilhos (Triggers) MySQL:

calcularVolume(): Uma função customizada no MySQL foi criada para centralizar a lógica de cálculo de volume (M³). Isto simplifica as consultas no PHP e garante que o cálculo seja sempre consistente.

Triggers (AFTER UPDATE): Foram implementados gatilhos na tabela ordens_producao para automatizar o reempenho de materiais e garantir a integridade do estoque quando o status de uma OP é alterado. Esta lógica no lado do banco de dados é robusta e é executada independentemente da origem da alteração.

2.3. Segurança e Gestão de Sessão
Autenticação: O controlo de acesso é feito através de sessões PHP ($_SESSION). Quando um utilizador faz login, o seu user_id e user_cargo são armazenados na sessão.

Controlo de Acesso: No topo de páginas restritas (como o módulo de operadores), uma verificação if ($_SESSION['user_cargo'] !== 'admin') redireciona utilizadores não autorizados, garantindo a segurança das funcionalidades.

Prevenção de XSS: A função sanitizeInput() é utilizada para limpar os dados que vêm de formulários, utilizando htmlspecialchars() para converter caracteres especiais em entidades HTML, prevenindo ataques de Cross-Site Scripting.

3. Detalhes do Frontend (HTML, CSS e JavaScript)
3.1. Estrutura e Estilização
Templates (header.php, footer.php): A estrutura de todas as páginas é padronizada através destes ficheiros. O header.php inclui as folhas de estilo, o menu de navegação e abre a tag <main>, enquanto o footer.php inclui os scripts JavaScript e fecha as tags HTML.

Estilização (CSS): O sistema utiliza um ficheiro public/css/style.css central para definir a identidade visual (cores, fontes, etc.). O Bootstrap 5 é usado principalmente para o seu sistema de grid (row, col-md- etc.) e para componentes base, garantindo a responsividade.

3.2. Interatividade (JavaScript)
Vanilla JS: Toda a interatividade do lado do cliente é construída com JavaScript puro (padrão ES6+), sem a dependência de frameworks como jQuery.

Buscas Assíncronas (AJAX): A Fetch API é usada para fazer chamadas HTTP a pequenos ficheiros PHP de suporte (ex: /ajax_get_lotes.php). Estes ficheiros retornam dados em formato JSON, que o JavaScript utiliza para atualizar dinamicamente partes da página, como preencher listas de sugestões ou carregar detalhes de um produto.

Visualização de Dados: A biblioteca Chart.js é utilizada para renderizar os gráficos de barras e de Pareto nos relatórios e no dashboard, oferecendo uma visualização de dados rica e interativa.

Interface do Utilizador: O JavaScript é usado para melhorar a experiência do utilizador, por exemplo, com o modal de confirmação de exclusão (showDeleteModal()) e com a lógica dos botões interativos para alternar a visualização dos gráficos.
