# pcp
JtekPCP
Desenvolvimento de sistema para controle de produção e estoques "extremamente básico" :D

Sistema de Planejamento e Controle da Produção (PCP)

Documento de Apresentação e Visão Geral

Introdução
Este documento apresenta uma visão detalhada do sistema de Planejamento e Controle da Produção (PCP), desenvolvido para otimizar e gerenciar as operações de fabricação. O sistema foi construído com uma arquitetura modular, utilizando PHP, MySQL para o backend, e HTML, CSS e JavaScript para o frontend, buscando ser intuitivo, flexível e escalável para as necessidades de uma linha de produção.
Arquitetura Geral
O sistema segue um padrão de arquitetura web cliente-servidor:
•	Backend (PHP & MySQL): Gerencia a lógica de negócios, a interação com o banco de dados e a segurança.
•	Frontend (HTML, CSS, JavaScript): Responsável pela interface do usuário e interação no navegador.
•	Banco de Dados (MySQL): Armazena todas as informações do sistema, desde cadastros básicos até dados de produção e estoque.
•	Conectividade: Utiliza AJAX para buscas em tempo real e atualizações assíncronas, proporcionando uma experiência mais fluida.
________________________________________
Módulos e Funcionalidades Detalhadas
1. Sistema de Login e Gerenciamento de Operadores
Este módulo controla o acesso ao sistema e gerencia os usuários que operam as funções de PCP.
•	Como é feito: 
o	Login (public/login.php): Operaores acessam o sistema com username e password. A senha é armazenada de forma segura (hashada com password_hash). Apenas operadores ativos conseguem logar. Mensagens de sucesso (após logout) e erro (falha de login) são exibidas de forma clara.
o	Gerenciamento de Operadores (modules/operadores/): 
	Cadastro (adicionar.php): Permite adicionar novos operadores, definindo nome, matricula, username (único), cargo, password (hashada), e status ativo.
	Edição (editar.php): Permite alterar todos os dados do operador, incluindo username (com verificação de unicidade) e password (apenas se uma nova for fornecida, hashando-a). O campo cargo e o status ativo são editáveis.
	Listagem (index.php): Exibe todos os operadores cadastrados com paginação e opções de busca/filtro por nome, matrícula, usuário e cargo.
	Exclusão (excluir.php): Implementa soft delete (o registro não é apagado fisicamente, apenas marcado como deleted_at).
o	Logout (public/logout.php): Encerra a sessão do usuário e o redireciona para a tela de login.
o	Controle de Acesso (Header): O link "Operadores" no menu de navegação é exibido apenas para usuários com cargo = 'admin', garantindo que apenas administradores possam gerenciar os operadores.
•	Benefícios: 
o	Segurança: Acesso restrito por usuário e senha hashada.
o	Controle de Acesso: Definição de perfis (como 'admin') para controlar a visibilidade de módulos sensíveis.
o	Rastreabilidade: Cada ação no sistema pode ser associada a um operador logado.
________________________________________
2. Página Inicial (Dashboard)
Apresenta um resumo executivo e indicadores chave de desempenho (KPIs) para o dia e as operações atuais.
•	Como é feito: 
o	Indicadores Atuais: Exibe contadores em tempo real para: OPs Ativas (pendentes/em produção), Quantidade Programada e Apontada para essas OPs, Produtos Abaixo do Estoque Mínimo (com alerta visual) e Máquinas Operacionais e Paradas (com alerta).
o	Gráfico de Produção Diária: Um gráfico de barras interativo (utilizando d3.js) mostra a quantidade produzida no dia atual, segmentada por máquina e produto. A lógica de fuso horário é configurada para garantir a precisão da data.
o	Resumo das Ordens de Produção: Uma tabela detalhada lista as Ordens de Produção mais recentes (ordenadas por data de emissão decrescente), incluindo Número da OP, Produto, Quantidade Programada, Quantidade Apontada, Data de Emissão e Status. Não possui paginação para uma visão mais concisa.
•	Benefícios: 
o	Visão Rápida: Permite aos gestores e operadores uma compreensão instantânea do status da produção.
o	Tomada de Decisão Ágil: Alertas visuais destacam áreas que precisam de atenção imediata (estoque crítico, máquinas paradas).
o	Acompanhamento de KPIs: Monitoramento diário da performance da produção.
________________________________________
3. Módulo de Produtos
Gerencia o cadastro de todos os itens utilizados e produzidos na fábrica.
•	Como é feito: 
o	Cadastro (adicionar.php): Adiciona produtos com código, nome, descrição, unidade de medida, tipo (Matéria-Prima, Semi-Acabado, Acabado), estoque mínimo, local de armazenamento.
o	Listagem (index.php): Tabela paginada com busca e filtro por nome, código, grupo, subgrupo.
o	Edição (editar.php): Permite alterar os detalhes do produto.
o	Exclusão (excluir.php): Soft delete.
•	Benefícios: 
o	Organização: Catálogo completo de itens da empresa.
o	Controle de Inventário: Base para o gerenciamento de estoque, definindo mínimos e localização.
________________________________________
4. Módulo de Máquinas
Gerencia o parque de máquinas e equipamentos da produção.
•	Como é feito: 
o	Cadastro (adicionar.php): Adiciona máquinas com nome, descrição, status (operacional, manutenção, parada).
o	Listagem (index.php): Tabela paginada com busca e filtro.
o	Edição (editar.php): Permite alterar os detalhes da máquina.
o	Exclusão (excluir.php): Soft delete.
•	Benefícios: 
o	Gerenciamento de Recursos: Visibilidade sobre a disponibilidade e o status das máquinas.
o	Planejamento da Produção: Ajuda a alocar OPs para máquinas disponíveis.
________________________________________
5. Módulo de Ordens de Produção (OPs)
O coração do sistema de PCP, gerenciando o ciclo de vida das ordens de fabricação.
•	Como é feito: 
o	Criação (adicionar.php): 
	Gera Número da OP automaticamente (formato AAMMHHMMSS).
	Gera Número do Pedido automaticamente (se não informado).
	Permite selecionar Produto (Acabado/Montado) via busca em tempo real.
	Associa a OP a uma Máquina Ideal.
	Define Quantidade a Produzir, Data de Emissão e Data Prevista de Conclusão.
	Empenho Automático de Materiais: Ao criar a OP, o sistema consulta a BoM do produto e empenha automaticamente os materiais necessários, registrando na tabela empenho_materiais com quantidade_inicial e quantidade_empenhada iguais, e ajustando o estoque_empenhado na tabela produtos.
o	Listagem (index.php): 
	Exibe OPs com Número da OP, Produto, Qtd. Programada, Qtd. Apontada, Data de Emissão e Status.
	Opções de busca/filtro e paginação.
	Controle de Exclusão: O botão "Excluir" é desativado para OPs com status = 'concluida', status = 'em_producao' ou se a OP já tiver qualquer apontamento de produção registrado, prevenindo exclusão de histórico.
o	Edição (editar.php): 
	Permite atualizar todos os detalhes da OP.
	Validação de Quantidade: A Quantidade a Produzir não pode ser menor que a Quantidade Produzida (total já apontado).
	Conclusão Automática por Quantidade: Se a Quantidade a Produzir for alterada para ser igual à Quantidade Produzida (total já apontado) e a Quantidade Produzida for maior que zero, o status da OP é automaticamente alterado para concluida.
	Ajuste de Empenho por Trigger: Um gatilho MySQL (before_ordens_producao_status_update) ajusta quantidade_produzir para a soma dos apontamentos e zera os empenhos relacionados (marcando-os como deleted_at e ajustando estoque_empenhado nos produtos) sempre que o status da OP muda para concluida ou cancelada. Isso centraliza a regra no banco de dados.
	Ajuste de Empenho na Alteração de Qtd (PHP): Quando a quantidade_produzir da OP é alterada (e o status não está concluindo/cancelando a OP), o PHP ajusta o estoque_empenhado do produto material, e um gatilho se encarrega de ajustar a quantidade_empenhada no registro de empenho (empenho_materiais) com base na nova quantidade_inicial.
o	Apontamento (apontar.php): 
	Registra a quantidade_produzida para uma OP em um dado data_apontamento por um operador e máquina.
	Gera um lote_numero (OP_NUMERO-ID_APONTAMENTO) e salva na tabela apontamentos_producao.
	Adiciona a quantidade produzida ao estoque_atual do produto acabado e registra uma entrada na movimentacoes_estoque.
	Consumo de Materiais da BoM e Baixa de Empenho: Para cada material da BoM, o sistema calcula a quantidade consumida, diminui o estoque_atual do material, registra uma saída em movimentacoes_estoque, diminui a quantidade_empenhada do registro de empenho (empenho_materiais) e do estoque_empenhado total do material na tabela produtos. Se o empenho restante for zero ou menos, ele é soft-deletado.
o	Impressão da Ordem (imprimir_ordem.php): Gera um PDF (visualização para impressão) da OP, incluindo a lista de materiais calculada com base na quantidade_inicial empenhada para cada item da BoM, facilitando a rastreabilidade da intenção original de consumo.
•	Benefícios: 
o	Planejamento Detalhado: Criação de OPs com todos os dados relevantes.
o	Controle de Execução: Apontamento de produção real, acompanhando a evolução da OP.
o	Rastreabilidade de Lotes: Cada apontamento gera um lote único.
o	Gestão de Estoque Dinâmica: Empenho e consumo de materiais atualizam o estoque em tempo real.
o	Integridade dos Dados: Validações e gatilhos garantem que as quantidades e status estejam sempre consistentes.
________________________________________
6. Módulo de Materiais
Gerencia o fluxo de entrada de matérias-primas e componentes no estoque.
•	Como é feito: 
o	Registro de Entrada (adicionar.php): Permite registrar entradas de materiais no estoque_atual, especificando fornecedor, local de armazenamento, quantidade, data e observações. Registra a movimentação na movimentacoes_estoque.
o	Listagem (index.php): Tabela paginada com busca e filtro.
o	Edição (editar.php): Permite corrigir ou ajustar entradas.
o	Exclusão (excluir.php): Soft delete.
•	Benefícios: 
o	Controle de Inventário: Rastreamento preciso da entrada de insumos.
o	Rastreabilidade: Histórico completo de movimentações de entrada.
________________________________________
7. Módulo de Lista de Materiais (BoM)
Define a composição de produtos acabados ou semi-acabados.
•	Como é feito: 
o	Cadastro (adicionar.php): Permite associar um Produto Pai (pode ser qualquer tipo de produto, não apenas acabado) a um Produto Filho (componente/material) com uma quantidade necessária por unidade do pai.
o	Listagem (index.php): Tabela paginada com busca e filtro, mostrando as relações de BoM.
o	Edição (editar.php): Permite ajustar as quantidades necessárias.
o	Exclusão (excluir.php): Soft delete.
•	Benefícios: 
o	Padronização: Define a estrutura exata de cada produto.
o	Base para Cálculo: Essencial para o cálculo de necessidades de material na produção.
________________________________________
8. Módulo de Estoque
Oferece uma visão consolidada do inventário de todos os produtos.
•	Como é feito: 
o	Visão Geral (index.php): Exibe estoque_atual, estoque_empenhado e estoque_livre (calculado) para cada produto.
o	Status de Estoque: Alerta visualmente produtos com estoque abaixo do estoque_mínimo.
o	Movimentação Manual (movimentar.php): Permite ajustar o estoque_atual com entrada ou saída para qualquer produto, registrando na movimentacoes_estoque.
•	Benefícios: 
o	Visibilidade de Inventário: Conhecimento preciso da disponibilidade de cada item.
o	Prevenção de Faltas: Identificação rápida de itens críticos.
________________________________________
9. Módulo de Empenho Manual
Permite ajustar manualmente as reservas de materiais para OPs específicas.
•	Como é feito: 
o	Registro (adicionar.php): Permite empenhar ou desempenhar (liberar) manualmente quantidades de produtos para uma OP específica. Isso ajusta estoque_empenhado na tabela produtos e o registro na empenho_materiais. A quantidade_inicial é gravada no empenho inicial.
o	Exclusão (excluir.php): Ao "excluir" (soft delete) um registro de empenho manual, a quantidade_empenhada é subtraída do estoque_atual do produto e também do estoque_empenhado, liberando-o do empenho. Uma movimentação de desempenho é registrada.
•	Benefícios: 
o	Flexibilidade: Ajustes de empenho fora do fluxo automático da OP.
o	Correção de Erros: Permite corrigir reservas indevidas ou excessivas.
________________________________________
10. Módulo de Fornecedores/Clientes
Gerencia os parceiros comerciais da empresa.
•	Como é feito: 
o	Cadastro (adicionar.php): Permite adicionar fornecedores ou clientes com nome, tipo (fornecedor, cliente, ambos), CNPJ, contato, email, telefone e endereço.
o	Listagem (index.php): Tabela paginada com busca e filtro por nome, CNPJ ou tipo.
o	Edição (editar.php): Permite alterar todos os dados do cadastro.
o	Exclusão (excluir.php): Soft delete.
•	Benefícios: 
o	Organização de Dados: Centraliza informações de contato e relação comercial.
o	Rastreabilidade: Vincula operações de compra/venda a entidades específicas.
________________________________________
11. Responsividade e Usabilidade (Global)
O sistema foi projetado com foco na experiência do usuário e adaptabilidade.
•	Como é feito: 
o	Cabeçalho Responsivo: Em telas menores, o menu de navegação se transforma em um menu hambúrguer (dropdown), otimizado para toque.
o	Tabelas Responsivas: Tabelas muito largas para telas menores agora permitem rolagem horizontal (overflow-x: auto), em vez de quebrar o layout.
o	Formulários Adaptativos: Campos de formulário e botões se adaptam ao tamanho da tela, mantendo a legibilidade e sendo fáceis de tocar em smartphones (com padding e font-size adequados).
o	Módulo Chão de Fábrica (UI/UX Focada): A tela de apontamento (apontar_consumir.php) possui estilos específicos incorporados para ter fontes maiores, espaçamento generoso e um layout vertical mais intuitivo, ideal para uso direto na linha de produção via tablets ou celulares.
•	Benefícios: 
o	Usabilidade em Múltiplos Dispositivos: O sistema pode ser acessado e operado de forma eficaz em desktops, laptops, tablets e smartphones.
o	Experiência de Usuário Aprimorada: Interface clara, fácil de navegar e interagir, mesmo para usuários com pouca experiência técnica.
o	Eficiência na Fábrica: Operadores podem registrar apontamentos e consultar informações diretamente no local de trabalho com facilidade.

