    </main>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Sistema Jtek-PCP. Todos os direitos reservados.</p>
    </footer>

    <!-- OBSERVAÇÃO: Script do Bootstrap Bundle (JS) -->
    <!-- Essencial para funcionalidades como menus dropdown e modais. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Se você tiver um ficheiro JS personalizado, ele deve vir depois do Bootstrap -->
    <!-- <script src="<?php echo BASE_URL; ?>/public/js/main.js"></script> -->

	<!-- Modal de Confirmação de Exclusão -->
    <!-- Este modal é usado para confirmar ações de exclusão antes de prosseguir -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <h3>Confirmar Excluso</h3>
                <p>Você tem certeza que deseja excluir este item? Esta aço não pode ser desfeita.</p>
                <div class="modal-buttons">
                    <!-- Botão para cancelar a exclusão e fechar o modal -->
                    <button class="button cancel" onclick="hideDeleteModal()">Cancelar</button>
                    <!-- Botão para confirmar a exclusão. O JavaScript vai anexar a ação a ele. -->
                    <button class="button delete" id="confirmDeleteButton">Excluir</button>
                </div>
            </div>
        </div>

        <script>
            // Variveis globais para armazenar o módulo e o ID do item a ser excluído
            let deleteModule = '';
            let deleteId = 0;

            // Função para exibir o modal de exclusão
            // Recebe o nome do módulo (ex: 'produtos', 'maquinas', 'ordens_producao', 'operadores', 'materiais', 'bom', 'empenho_manual', 'fornecedores_clientes') e o ID do item
            function showDeleteModal(module, id) {
                // Aplica .trim() aqui para garantir que não haja espaços em branco
                deleteModule = module.trim(); 
                deleteId = id;         
                // Exibe o modal (alterando a propriedade display do CSS)
                document.getElementById('deleteModal').style.display = 'flex';

                console.log("DEBUG: showDeleteModal called. Module:", deleteModule, "ID:", id); // DEBUG Console
            }

            // Funço para ocultar o modal de exclusão
            function hideDeleteModal() {
                // Oculta o modal
                document.getElementById('deleteModal').style.display = 'none';
                // Reseta as variveis
                deleteModule = '';
                deleteId = 0;
            }

            // Adiciona um evento de clique ao botão "Excluir" dentro do modal
            document.getElementById('confirmDeleteButton').onclick = function() {
                // Se o módulo e o ID estiverem definidos, redireciona para a página de exclusão correspondente
                if (deleteModule && deleteId) {
                    let finalUrl = '';
                    const baseUrl = '<?php echo BASE_URL; ?>'; // Obtém a BASE_URL do PHP

                    console.log("DEBUG: deleteModule value in onclick (after trim):", deleteModule, "Type:", typeof deleteModule, "Length:", deleteModule.length); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'apontamentos_producao' === deleteModule?", 'apontamentos_producao' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'ordens_producao' === deleteModule?", 'ordens_producao' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'maquinas' === deleteModule?", 'maquinas' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'produtos' === deleteModule?", 'produtos' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'operadores' === deleteModule?", 'operadores' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'materiais' === deleteModule?", 'materiais' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'bom' === deleteModule?", 'bom' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'empenho_manual' === deleteModule?", 'empenho_manual' === deleteModule); // NOVO DEBUG Console
                    console.log("DEBUG: Is 'fornecedores_clientes' === deleteModule?", 'fornecedores_clientes' === deleteModule); // NOVO DEBUG Console


                    // Lógica para determinar o caminho correto do arquivo de exclusão
                    if (deleteModule === 'apontamentos_producao') {
                        finalUrl = `${baseUrl}/modules/ordens_producao/excluir_apontamento.php?id=${deleteId}`;
                    } else if (deleteModule === 'ordens_producao') {
                        finalUrl = `${baseUrl}/modules/ordens_producao/excluir.php?id=${deleteId}`;
                    } else if (deleteModule === 'maquinas') {
                        finalUrl = `${baseUrl}/modules/maquinas/excluir.php?id=${deleteId}`;
                    } else if (deleteModule === 'produtos') {
                        finalUrl = `${baseUrl}/modules/produtos/excluir.php?id=${deleteId}`;
                    } else if (deleteModule === 'operadores') { 
                        finalUrl = `${baseUrl}/modules/operadores/excluir.php?id=${deleteId}`;
                    } else if (deleteModule === 'materiais') { 
                        finalUrl = `${baseUrl}/modules/materiais/excluir.php?id=${deleteId}`;
                    } else if (deleteModule === 'bom') { 
                        finalUrl = `${baseUrl}/modules/bom/excluir.php?id=${deleteId}`;
                    } else if (deleteModule === 'empenho_manual') { 
                        finalUrl = `${baseUrl}/modules/empenho_manual/excluir.php?id=${deleteId}`;
                    } else if (deleteModule === 'fornecedores_clientes') { // Adicionado para o módulo de Fornecedores/Clientes
                        finalUrl = `${baseUrl}/modules/fornecedores_clientes/excluir.php?id=${deleteId}`;
                    }
                    // Adicione mais `else if` para outros módulos se necessário no futuro

                    console.log("DEBUG: Final URL before redirection check:", finalUrl); // DEBUG Console

                    // Redireciona para a URL construída
                    if (finalUrl) { // Garante que uma URL foi construída
                        window.location.href = finalUrl;
                    } else {
                        console.error("DEBUG: Nenhuma URL de exclusão válida construída para o módulo:", deleteModule);
                        alert("Erro ao determinar a URL de exclusão. Por favor, tente novamente."); // Feedback ao usuário
                    }
                }
                // Oculta o modal após a ação (ou cancelamento)
                hideDeleteModal();
            };

            // Fecha o modal se o usuário clicar fora do conteúdo do modal
            window.onclick = function(event) {
                const modal = document.getElementById('deleteModal');
                if (event.target === modal) {
                    hideDeleteModal();
                }
            };
        </script>
</body>
</html>
