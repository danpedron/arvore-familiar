/**
 * Liga um campo de texto a uma busca de locais via Nominatim/OpenStreetMap,
 * preenchendo campos ocultos de latitude/longitude quando uma sugestão é escolhida.
 *
 * Uso: buscaLocalIniciar('nascimento') liga os elementos com ids
 * local_nascimento (texto visível, também é o campo enviado no form),
 * local_nascimento_lat, local_nascimento_lng (ocultos) e
 * local_nascimento_sugestoes (lista de resultados) e local_nascimento_status (aviso).
 */
function buscaLocalIniciar(prefixo) {
    const campoTexto = document.getElementById('local_' + prefixo);
    const campoLat = document.getElementById('local_' + prefixo + '_lat');
    const campoLng = document.getElementById('local_' + prefixo + '_lng');
    const listaSugestoes = document.getElementById('local_' + prefixo + '_sugestoes');
    const status = document.getElementById('local_' + prefixo + '_status');

    if (!campoTexto) return;

    let timerBusca = null;
    let ultimaBuscaSelecionada = campoTexto.value;

    function atualizarStatus() {
        if (campoLat.value && campoLng.value && campoTexto.value === ultimaBuscaSelecionada) {
            status.textContent = '✓ local verificado no OpenStreetMap';
            status.style.color = '#2a6b2a';
        } else if (campoTexto.value.trim() !== '') {
            status.textContent = 'Local não verificado — selecione uma sugestão da lista, se possível';
            status.style.color = '#a37a1f';
        } else {
            status.textContent = '';
        }
    }

    function limparSugestoes() {
        listaSugestoes.innerHTML = '';
        listaSugestoes.style.display = 'none';
    }

    function selecionar(resultado) {
        campoTexto.value = resultado.nome;
        campoLat.value = resultado.lat;
        campoLng.value = resultado.lng;
        ultimaBuscaSelecionada = resultado.nome;
        limparSugestoes();
        atualizarStatus();
    }

    async function buscar(termo) {
        try {
            const resp = await fetch('geocodificar.php?q=' + encodeURIComponent(termo));
            const resultados = await resp.json();

            if (!Array.isArray(resultados) || resultados.length === 0) {
                limparSugestoes();
                return;
            }

            listaSugestoes.innerHTML = '';
            resultados.forEach(r => {
                const item = document.createElement('div');
                item.className = 'sugestao-item';
                item.textContent = r.nome;
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selecionar(r);
                });
                listaSugestoes.appendChild(item);
            });
            listaSugestoes.style.display = 'block';
        } catch (e) {
            limparSugestoes();
        }
    }

    campoTexto.addEventListener('input', () => {
        // Ao editar manualmente, o local deixa de estar "verificado" até selecionar de novo
        clearTimeout(timerBusca);
        campoLat.value = '';
        campoLng.value = '';
        atualizarStatus();

        const termo = campoTexto.value.trim();
        if (termo.length < 3) {
            limparSugestoes();
            return;
        }
        timerBusca = setTimeout(() => buscar(termo), 450);
    });

    campoTexto.addEventListener('blur', () => {
        setTimeout(limparSugestoes, 150);
    });

    campoTexto.addEventListener('focus', () => {
        if (listaSugestoes.children.length > 0) {
            listaSugestoes.style.display = 'block';
        }
    });

    atualizarStatus();
}
