    </main>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> PCP System. Todos os direitos reservados.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
	<!-- Modal de Confirmação de Exclusão -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Confirmar Exclusão</h3>
            <p>Você tem certeza que deseja excluir este item? Esta ação no pode ser desfeita.</p>
            <div class="modal-buttons">
                <button class="button cancel" onclick="hideDeleteModal()">Cancelar</button>
                <button class="button delete" id="confirmDeleteButton">Excluir</button>
            </div>
        </div>
    </div>

    <!-- Modal para a Etiqueta -->
    <div id="labelModal" class="modal">
        <div class="modal-content" style="width: 120mm; height: 150mm; padding: 0; border-radius: 15px; overflow: hidden;">
          
          <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Visualizar Etiqueta</h3>
                <!-- OBSERVAÇÃO: Ícone de fechar adicionado -->
                <button class="close-button" onclick="closeLabelModal()">
                    <i class="fas fa-times" style="font-size: 30px;"></i>
                </button>
            </div>
          
            <iframe id="labelFrame" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>

    <!-- Novo Modal para a Ordem de Produção -->
    <div id="opModal" class="modal">
        <div class="modal-content" style="width: 90%; max-width: 900px; height: 90vh; padding: 0; border-radius: 15px; overflow: hidden;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Visualizar Ordem de Produção</h3>
                <!-- OBSERVAÇÃO: Ícone de fechar adicionado -->
                <button class="close-button" onclick="closeOpModal()">
                    <i class="fas fa-times" style="font-size: 30px;"></i>
                </button>
            </div>
            <iframe id="opFrame" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe><br>
        </div>
    </div>

    <script>
        // ... (código do deleteModal existente) ...
        let deleteModule = '';
        let deleteId = 0;

        function showDeleteModal(module, id) {
            deleteModule = module.trim(); 
            deleteId = id;        
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteModule = '';
            deleteId = 0;
        }

        document.getElementById('confirmDeleteButton').onclick = function() {
            if (deleteModule && deleteId) {
                let finalUrl = '';
                const baseUrl = '<?php echo BASE_URL; ?>';

                if (deleteModule === 'apontamentos_producao_op') {
                    finalUrl = `${baseUrl}/modules/ordens_producao/excluir_apontamento.php?id=${deleteId}`;
                } else if (deleteModule === 'apontamentos_producao') {
                    finalUrl = `${baseUrl}/modules/chao_de_fabrica/excluir_apontamento.php?id=${deleteId}`;
                } else if (deleteModule === 'consumo_producao') {
                    finalUrl = `${baseUrl}/modules/chao_de_fabrica/excluir_consumo.php?id=${deleteId}`;
                } else if (deleteModule === 'consumo_insumo') {
                    finalUrl = `${baseUrl}/modules/chao_de_fabrica/excluir_insumo.php?id=${deleteId}`;
                } else if (deleteModule === 'ordens_producao') {
                    finalUrl = `${baseUrl}/modules/ordens_producao/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'maquinas') {
                    finalUrl = `${baseUrl}/modules/maquinas/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'grupos_maquinas') {
                    finalUrl = `${baseUrl}/modules/manutencao/grupos/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'maquina_grupo_associacao') {
                    finalUrl = `${baseUrl}/modules/manutencao/grupos/excluir_associacao.php?id=${deleteId}`;
                } else if (deleteModule === 'maquina_jornada_log') {
                    finalUrl = `${baseUrl}/modules/manutencao/jornada/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'paradas_maquina') {
                    finalUrl = `${baseUrl}/modules/manutencao/paradas/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'motivos_parada') {
                    finalUrl = `${baseUrl}/modules/manutencao/motivos/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'entradas_materiais') {
                    finalUrl = `${baseUrl}/modules/entradas_materiais/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'expedicao_log') {
                    finalUrl = `${baseUrl}/modules/expedicao/estornar.php?id=${deleteId}`;
                } else if (deleteModule === 'produtos') {
                    finalUrl = `${baseUrl}/modules/produtos/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'operadores') { 
                    finalUrl = `${baseUrl}/modules/operadores/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'roteiros') {
                    finalUrl = `${baseUrl}/modules/roteiros/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'roteiro_etapas') {
                    finalUrl = `${baseUrl}/modules/roteiros/excluir_etapa.php?id=${deleteId}`;
                } else if (deleteModule === 'turnos') {
                    finalUrl = `${baseUrl}/modules/turnos/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'bom') { 
                    finalUrl = `${baseUrl}/modules/bom/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'pedidos_venda') {
                    finalUrl = `${baseUrl}/modules/pedidos_venda/excluir.php?id=${deleteId}`;
                } else if (deleteModule === 'pedidos_venda_itens') {
                    finalUrl = `${baseUrl}/modules/pedidos_venda/excluir_item.php?id=${deleteId}`;
                } else if (deleteModule === 'fornecedores_clientes') {
                    finalUrl = `${baseUrl}/modules/fornecedores_clientes/excluir.php?id=${deleteId}`;
                }

                if (finalUrl) {
                    window.location.href = finalUrl;
                } else {
                    alert("Erro ao determinar a URL de exclusão para o módulo: " + deleteModule);
                }
            }
            hideDeleteModal();
        };

        // --- LGICA PARA O MODAL DA ETIQUETA ---
        const labelModal = document.getElementById('labelModal');
        const labelFrame = document.getElementById('labelFrame');

        function openLabelModal(url) {
            labelFrame.src = url;
            labelModal.style.display = 'flex';
        }

        function closeLabelModal() {
            labelModal.style.display = 'none';
            labelFrame.src = 'about:blank';
        }

        // --- LÓGICA PARA O MODAL DA OP ---
        const opModal = document.getElementById('opModal');
        const opFrame = document.getElementById('opFrame');

        function openOpModal(url) {
            opFrame.src = url;
            opModal.style.display = 'flex';
        }

        function closeOpModal() {
            opModal.style.display = 'none';
            opFrame.src = 'about:blank';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const labelToPrint = urlParams.get('print_label');
            if (labelToPrint) {
                const labelUrl = `<?php echo BASE_URL; ?>/modules/ordens_producao/gerar_etiqueta.php?id=${labelToPrint}`;
                openLabelModal(labelUrl);
            }
        });

        window.onclick = function(event) {
            if (event.target == document.getElementById('deleteModal')) {
                hideDeleteModal();
            }
            if (event.target == labelModal) {
                closeLabelModal();
            }
            if (event.target == opModal) {
                closeOpModal();
            }
        };

        // ... (código de logout por inatividade) ...
        let inactivityTimer;
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
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
    </script>

<?php
// ... (código de log de visitas) ...
?>
</body>
</html>
