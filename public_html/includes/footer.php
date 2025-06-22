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
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <h3>Confirmar Exclusão</h3>
                <p>Você tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.</p>
                <div class="modal-buttons">
                    <!-- Botão para cancelar a exclusão e fechar o modal -->
                    <button class="button cancel" onclick="hideDeleteModal()">Cancelar</button>
                    <!-- Botão para confirmar a exclusão. O JavaScript vai anexar a ação a ele. -->
                    <button class="button delete" id="confirmDeleteButton">Excluir</button>
                </div>
            </div>
        </div>

        <script>
            // Variáveis globais para armazenar o módulo e o ID do item a ser excluído
            let deleteModule = '';
            let deleteId = 0;

            // Função para exibir o modal de exclusão
            function showDeleteModal(module, id) {
                deleteModule = module.trim(); 
                deleteId = id;        
                document.getElementById('deleteModal').style.display = 'flex';
            }

            // Função para ocultar o modal de exclusão
            function hideDeleteModal() {
                document.getElementById('deleteModal').style.display = 'none';
                deleteModule = '';
                deleteId = 0;
            }

            // Adiciona um evento de clique ao botão "Excluir" dentro do modal
            document.getElementById('confirmDeleteButton').onclick = function() {
                if (deleteModule && deleteId) {
                    let finalUrl = '';
                    const baseUrl = '<?php echo BASE_URL; ?>'; // Obtém a BASE_URL do PHP

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
                    } else if (deleteModule === 'fornecedores_clientes') {
                        finalUrl = `${baseUrl}/modules/fornecedores_clientes/excluir.php?id=${deleteId}`;
                    }

                    if (finalUrl) {
                        window.location.href = finalUrl;
                    } else {
                        alert("Erro ao determinar a URL de exclusão.");
                    }
                }
                hideDeleteModal();
            };

            // Fecha o modal se o usuário clicar fora do conteúdo do modal
            window.onclick = function(event) {
                const modal = document.getElementById('deleteModal');
                if (event.target === modal) {
                    hideDeleteModal();
                }
            };

            // --- LÓGICA DE LOGOUT POR INATIVIDADE ---
            let inactivityTimer;

            function resetInactivityTimer() {
                clearTimeout(inactivityTimer);
                // Define o tempo em milissegundos (ex: 15 minutos = 15 * 60 * 1000 = 900000)
                inactivityTimer = setTimeout(logoutDueToInactivity, 900000);
            }

            function logoutDueToInactivity() {
                window.location.href = '<?php echo BASE_URL; ?>/public/logout.php?motivo=inatividade';
            }

            document.addEventListener('mousemove', resetInactivityTimer);
            document.addEventListener('keypress', resetInactivityTimer);
            document.addEventListener('click', resetInactivityTimer);
            document.addEventListener('scroll', resetInactivityTimer);

            resetInactivityTimer();
            // --- FIM DA LÓGICA DE LOGOUT POR INATIVIDADE ---

        </script>

<?php
// --- INÍCIO DO CÓDIGO DE RASTREAMENTO AVANÇADO (VERSÃO CORRIGIDA) ---
// Esta função irá criar a sua própria conexão para garantir que a visita seja sempre registada.
function log_visitor() {
    // Só executa se não for uma chamada AJAX (para não poluir o log) e se tivermos as constantes de DB
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return;
    }
    if (!defined('DB_HOST')) return;

    // OBSERVAÇÃO: Cria uma conexão totalmente nova e independente para o log.
    $log_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($log_conn->connect_error) {
        error_log("Erro de conexão no log de visitas: " . $log_conn->connect_error);
        return;
    }

    $usuario_id = $_SESSION['user_id'] ?? null;
    $usuario_logado = $_SESSION['user_name'] ?? 'Anônimo';
    $ip_acesso = $_SERVER['REMOTE_ADDR'];
    $pagina_visitada = $_SERVER['REQUEST_URI'];
    $pagina_referencia = $_SERVER['HTTP_REFERER'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    try {
        $sql = "INSERT INTO visitas (usuario_id, usuario_logado, ip_acesso, pagina_visitada, pagina_referencia, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $log_conn->prepare($sql);
        $stmt->bind_param("isssss", $usuario_id, $usuario_logado, $ip_acesso, $pagina_visitada, $pagina_referencia, $user_agent);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Erro ao registrar visita avançada: " . $e->getMessage());
    }
    
    // Fecha a conexão do log.
    $log_conn->close();
}

// Chama a função para registar a visita
log_visitor();

// --- FIM DO CÓDIGO DE RASTREAMENTO ---
?>
</body>
</html>
