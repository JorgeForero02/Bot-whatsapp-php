let allNodes = [];
let nodeKeywords = [];

async function loadNodes() {
    try {
        const res  = await fetch(BASE_PATH + '/api/get-flows.php');
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        allNodes = data.nodes;
        renderNodes(allNodes);
    } catch (e) {
        document.getElementById('nodes-container').innerHTML =
            `<div class="text-center py-12 text-red-500">Error al cargar flujos: ${e.message}</div>`;
    }
}

function renderNodes(nodes) {
    const container = document.getElementById('nodes-container');
    if (!nodes.length) {
        container.innerHTML = `
            <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <p class="text-gray-500 dark:text-gray-400 text-lg">No hay nodos creados</p>
                <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Crea tu primer nodo para comenzar</p>
            </div>`;
        return;
    }

    container.innerHTML = nodes.map(node => {
        const keywords = Array.isArray(node.trigger_keywords) ? node.trigger_keywords : [];
        const nextNode = allNodes.find(n => n.id == node.next_node_id);
        const optionCount = (node.options || []).length;

        return `
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border ${node.is_active ? 'border-gray-200 dark:border-gray-700' : 'border-dashed border-gray-300 dark:border-gray-600 opacity-60'} p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center space-x-2">
                    ${node.is_root ? '<span class="px-2 py-0.5 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 text-xs rounded-full font-medium">Raíz</span>' : ''}
                    ${node.requires_calendar ? '<span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs rounded-full font-medium">Calendario</span>' : ''}
                    ${node.match_any_input ? '<span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 text-xs rounded-full font-medium">Cualquier msg</span>' : ''}
                    ${!node.is_active ? '<span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 text-xs rounded-full">Inactivo</span>' : ''}
                    <h3 class="font-bold text-gray-900 dark:text-gray-100">${escapeHtml(node.name)}</h3>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="openNodeModal(${node.id})" class="p-1.5 text-gray-400 hover:text-primary transition-all rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" title="Editar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button onclick="deleteNode(${node.id}, '${escapeHtml(node.name).replace(/'/g, '\\&#39;')}')" class="p-1.5 text-gray-400 hover:text-red-500 transition-all rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30" title="Eliminar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>

            ${keywords.length ? `<div class="flex flex-wrap gap-1 mb-3">${keywords.map(k => `<span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs rounded-full">${escapeHtml(k)}</span>`).join('')}</div>` : ''}

            <p class="text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 rounded-lg p-3 whitespace-pre-wrap line-clamp-3 mb-3">${escapeHtml(node.message_text)}</p>

            <div class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                <span>${optionCount} opción${optionCount !== 1 ? 'es' : ''}</span>
                ${nextNode ? `<span>→ <span class="text-primary">${escapeHtml(nextNode.name)}</span></span>` : '<span class="text-gray-300 dark:text-gray-600">Sin nodo siguiente</span>'}
            </div>

            ${optionCount ? `
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 space-y-1">
                ${(node.options || []).map(opt => {
                    const dest = allNodes.find(n => n.id == opt.next_node_id);
                    const kws  = (JSON.parse(opt.option_keywords || '[]') || []).join(', ');
                    return `<div class="flex items-center justify-between text-xs px-2 py-1 bg-gray-50 dark:bg-gray-900 rounded">
                        <span class="text-gray-700 dark:text-gray-300 font-medium">${escapeHtml(opt.option_text)}</span>
                        <span class="text-gray-400">[${escapeHtml(kws)}] → ${dest ? escapeHtml(dest.name) : '—'}</span>
                    </div>`;
                }).join('')}
            </div>` : ''}
        </div>`;
    }).join('');
}

function openNodeModal(nodeId) {
    nodeKeywords = [];
    document.getElementById('node-id').value = '';
    document.getElementById('node-name').value = '';
    document.getElementById('node-message').value = '';
    document.getElementById('node-is-root').checked = false;
    document.getElementById('node-requires-calendar').checked = false;
    document.getElementById('node-match-any-input').checked = false;
    document.getElementById('node-order').value = 0;
    document.getElementById('node-is-active').checked = true;
    document.getElementById('node-next').value = '';
    document.getElementById('options-container').innerHTML = '';
    document.getElementById('modal-title').textContent = nodeId ? 'Editar Nodo' : 'Nuevo Nodo';
    document.getElementById('message-preview').classList.add('hidden');
    document.getElementById('match-any-input-row').classList.add('hidden');
    document.getElementById('match-any-notice').classList.add('hidden');
    document.getElementById('keywords-input').disabled = false;
    document.getElementById('keywords-tags').style.opacity = '';

    populateNextNodeSelect(nodeId || null);

    if (nodeId) {
        const node = allNodes.find(n => n.id == nodeId);
        if (node) {
            document.getElementById('node-id').value = node.id;
            document.getElementById('node-name').value = node.name;
            document.getElementById('node-message').value = node.message_text;
            document.getElementById('node-is-root').checked = !!node.is_root;
            document.getElementById('node-requires-calendar').checked = !!node.requires_calendar;
            document.getElementById('node-match-any-input').checked = !!node.match_any_input;
            document.getElementById('node-order').value = node.position_order;
            onIsRootChange();
            if (node.match_any_input) onMatchAnyInputChange();
            document.getElementById('node-is-active').checked = !!node.is_active;
            document.getElementById('node-next').value = node.next_node_id || '';

            nodeKeywords = Array.isArray(node.trigger_keywords) ? [...node.trigger_keywords] : [];
            renderKeywordTags();

            (node.options || []).forEach(opt => {
                const kws = JSON.parse(opt.option_keywords || '[]') || [];
                addOption(opt.option_text, kws, opt.next_node_id || '');
            });

            updateMessagePreview();
        }
    }

    document.getElementById('node-modal').classList.remove('hidden');
    document.getElementById('node-modal').classList.add('flex');
}

function closeNodeModal() {
    document.getElementById('node-modal').classList.add('hidden');
    document.getElementById('node-modal').classList.remove('flex');
}

function populateNextNodeSelect(excludeId) {
    const sel = document.getElementById('node-next');
    sel.innerHTML = '<option value="">— Ninguno —</option>';
    allNodes.forEach(n => {
        if (n.id == excludeId) return;
        const opt = document.createElement('option');
        opt.value = n.id;
        opt.textContent = n.name;
        sel.appendChild(opt);
    });
}

function handleKeywordInput(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = e.target.value.trim().replace(/,$/, '');
        if (val && !nodeKeywords.includes(val)) {
            nodeKeywords.push(val);
            renderKeywordTags();
        }
        e.target.value = '';
    }
}

function renderKeywordTags() {
    const container = document.getElementById('keywords-tags');
    container.innerHTML = nodeKeywords.map((kw, i) =>
        `<span class="inline-flex items-center px-2 py-0.5 bg-primary text-white text-xs rounded-full space-x-1">
            <span>${escapeHtml(kw)}</span>
            <button type="button" onclick="removeKeyword(${i})" class="hover:text-red-300 transition-all">&times;</button>
        </span>`
    ).join('');
}

function removeKeyword(index) {
    nodeKeywords.splice(index, 1);
    renderKeywordTags();
}

function addOption(text = '', keywords = [], nextNodeId = '') {
    const container = document.getElementById('options-container');
    const idx = container.children.length;

    const div = document.createElement('div');
    div.className = 'border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2 bg-gray-50 dark:bg-gray-900';
    div.dataset.optionIndex = idx;

    const nodeOptions = allNodes.map(n => `<option value="${n.id}" ${n.id == nextNodeId ? 'selected' : ''}>${escapeHtml(n.name)}</option>`).join('');

    div.innerHTML = `
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Opción ${idx + 1}</p>
            <button type="button" onclick="this.closest('[data-option-index]').remove(); renumberOptions()" class="text-red-400 hover:text-red-600 text-xs">Eliminar</button>
        </div>
        <input type="text" placeholder="Texto de la opción (ej: 1. Agendar cita)" value="${escapeHtml(text)}" class="opt-text w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Keywords que activan esta opción</p>
            <div class="opt-kw-tags flex flex-wrap gap-1 p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 min-h-[36px]">
                ${keywords.map((k, ki) => `<span class="inline-flex items-center px-2 py-0.5 bg-secondary text-white text-xs rounded-full space-x-1"><span>${escapeHtml(k)}</span><button type="button" onclick="removeOptionKeyword(this, ${ki})" class="hover:text-red-300">&times;</button></span>`).join('')}
            </div>
            <input type="text" placeholder="Escribe y presiona Enter..." class="opt-kw-input mt-1 w-full px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs focus:ring-2 focus:ring-primary focus:border-transparent" onkeydown="handleOptionKeywordInput(event, this)">
        </div>
        <select class="opt-next w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
            <option value="">— Ningún nodo destino —</option>
            ${nodeOptions}
        </select>
    `;
    div._optionKeywords = [...keywords];
    container.appendChild(div);
}

function handleOptionKeywordInput(e, input) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = input.value.trim().replace(/,$/, '');
        if (!val) return;
        const optDiv = input.closest('[data-option-index]');
        if (!optDiv._optionKeywords) optDiv._optionKeywords = [];
        if (!optDiv._optionKeywords.includes(val)) {
            optDiv._optionKeywords.push(val);
            const tagsDiv = optDiv.querySelector('.opt-kw-tags');
            const ki = optDiv._optionKeywords.length - 1;
            const span = document.createElement('span');
            span.className = 'inline-flex items-center px-2 py-0.5 bg-secondary text-white text-xs rounded-full space-x-1';
            span.innerHTML = `<span>${escapeHtml(val)}</span><button type="button" onclick="removeOptionKeyword(this, ${ki})" class="hover:text-red-300">&times;</button>`;
            tagsDiv.appendChild(span);
        }
        input.value = '';
    }
}

function removeOptionKeyword(btn, ki) {
    const optDiv = btn.closest('[data-option-index]');
    if (optDiv._optionKeywords) optDiv._optionKeywords.splice(ki, 1);
    btn.closest('span').remove();
}

function renumberOptions() {
    document.querySelectorAll('#options-container [data-option-index]').forEach((div, i) => {
        div.dataset.optionIndex = i;
        const label = div.querySelector('p.text-xs');
        if (label) label.textContent = `Opción ${i + 1}`;
    });
}

function updateMessagePreview() {
    const msg = document.getElementById('node-message').value;
    const preview = document.getElementById('message-preview');
    if (msg.trim()) {
        preview.classList.remove('hidden');
        preview.querySelector('div').textContent = msg;
    } else {
        preview.classList.add('hidden');
    }
}
document.getElementById('node-message').addEventListener('input', updateMessagePreview);

async function saveNode() {
    const name    = document.getElementById('node-name').value.trim();
    const message = document.getElementById('node-message').value.trim();

    if (!name || !message) {
        alert('El nombre y el mensaje son obligatorios.');
        return;
    }

    const options = [];
    document.querySelectorAll('#options-container [data-option-index]').forEach(div => {
        options.push({
            option_text:     div.querySelector('.opt-text').value.trim(),
            option_keywords: div._optionKeywords || [],
            next_node_id:    div.querySelector('.opt-next').value || null,
            position_order:  parseInt(div.dataset.optionIndex),
        });
    });

    const payload = {
        name:              name,
        trigger_keywords:  nodeKeywords,
        message_text:      message,
        next_node_id:      document.getElementById('node-next').value || null,
        is_root:           document.getElementById('node-is-root').checked,
        requires_calendar: document.getElementById('node-requires-calendar').checked,
        match_any_input:   document.getElementById('node-match-any-input').checked,
        position_order:    parseInt(document.getElementById('node-order').value),
        is_active:         document.getElementById('node-is-active').checked,
        options:           options,
    };

    const nodeId = document.getElementById('node-id').value;
    if (nodeId) payload.id = parseInt(nodeId);

    try {
        const res  = await fetch(BASE_PATH + '/api/save-flow.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        closeNodeModal();
        showToast('Nodo guardado correctamente', 'green');
        await loadNodes();
    } catch (e) {
        alert('Error al guardar: ' + e.message);
    }
}

async function deleteNode(id, name) {
    if (!confirm(`¿Eliminar el nodo "${name}"? Se quitarán todas sus opciones y referencias desde otros nodos.`)) return;
    try {
        const res  = await fetch(BASE_PATH + '/api/delete-flow.php?id=' + id, {method: 'DELETE'});
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        showToast('Nodo eliminado', 'red');
        await loadNodes();
    } catch (e) {
        alert('Error al eliminar: ' + e.message);
    }
}

async function exportFlow() {
    const res  = await fetch(BASE_PATH + '/api/get-flows.php');
    const data = await res.json();
    const blob = new Blob([JSON.stringify({version:'1.0', exported_at: new Date().toISOString(), nodes: data.nodes}, null, 2)], {type: 'application/json'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'flow_export_' + new Date().toISOString().slice(0,10) + '.json';
    a.click();
    URL.revokeObjectURL(url);
}

async function importFlow(event) {
    const file = event.target.files[0];
    if (!file) return;
    if (!confirm('Importar este flujo REEMPLAZARÁ todos los nodos existentes. ¿Continuar?')) return;
    const text = await file.text();
    try {
        const res  = await fetch(BASE_PATH + '/api/save-flow.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({_import: true, json: text})
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        showToast(`Importados ${data.imported_nodes || '?'} nodos`, 'green');
        await loadNodes();
    } catch (e) {
        alert('Error al importar: ' + e.message);
    }
    event.target.value = '';
}

async function sendSimMessage() {
    const input = document.getElementById('sim-input');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';

    appendSimMessage(msg, 'user');

    try {
        const res  = await fetch(BASE_PATH + '/api/simulate-flow.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({message: msg})
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        const prefix = data.type === 'calendar' ? '[Calendario] ' : '';
        appendSimMessage(prefix + data.response, 'bot');
    } catch (e) {
        appendSimMessage('Error: ' + e.message, 'bot');
    }
}

function appendSimMessage(text, sender) {
    const chat = document.getElementById('sim-chat');
    if (chat.querySelector('p.text-gray-400')) chat.innerHTML = '';

    const div = document.createElement('div');
    div.className = sender === 'user'
        ? 'flex justify-end'
        : 'flex justify-start';
    div.innerHTML = `<span class="${sender === 'user' ? 'bg-primary text-white' : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700'} px-3 py-2 rounded-lg text-xs max-w-[85%] whitespace-pre-wrap">${escapeHtml(text)}</span>`;
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

async function resetSimulator() {
    await fetch(BASE_PATH + '/api/simulate-flow.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({message: '', reset: true})
    });
    const chat = document.getElementById('sim-chat');
    chat.innerHTML = '<p class="text-gray-400 text-center text-xs pt-8">Conversación reiniciada</p>';
}

function showToast(msg, color) {
    const div = document.createElement('div');
    div.className = `fixed top-20 right-4 bg-${color}-500 text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3000);
}

function onIsRootChange() {
    const isRoot = document.getElementById('node-is-root').checked;
    const row = document.getElementById('match-any-input-row');
    if (isRoot) {
        row.classList.remove('hidden');
    } else {
        row.classList.add('hidden');
        document.getElementById('node-match-any-input').checked = false;
        document.getElementById('match-any-notice').classList.add('hidden');
        document.getElementById('keywords-input').disabled = false;
        document.getElementById('keywords-tags').style.opacity = '';
    }
}

function onMatchAnyInputChange() {
    const isChecked = document.getElementById('node-match-any-input').checked;
    const notice = document.getElementById('match-any-notice');
    const kwInput = document.getElementById('keywords-input');
    const kwTags = document.getElementById('keywords-tags');
    if (isChecked) {
        notice.classList.remove('hidden');
        kwInput.disabled = true;
        kwTags.style.opacity = '0.4';
    } else {
        notice.classList.add('hidden');
        kwInput.disabled = false;
        kwTags.style.opacity = '';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadNodes();
