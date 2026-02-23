<?php
$pageTitle = 'Conversaciones - WhatsApp Bot';
$currentPage = 'conversations';

ob_start();
?>

<div class="bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden" style="height: calc(100vh - 120px);">
    <div class="grid grid-cols-1 lg:grid-cols-12 h-full">
        <div class="lg:col-span-4 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <div class="flex space-x-2 mb-3">
                    <button onclick="filterConversations('all')" class="flex-1 px-3 py-2 text-sm font-medium rounded-lg transition-all" id="filter-all">
                        Todas
                    </button>
                    <button onclick="filterConversations('active')" class="flex-1 px-3 py-2 text-sm font-medium rounded-lg transition-all" id="filter-active">
                        Activas
                    </button>
                    <button onclick="filterConversations('pending_human')" class="flex-1 px-3 py-2 text-sm font-medium rounded-lg transition-all" id="filter-pending">
                        Pendientes
                    </button>
                </div>
                <div class="relative">
                    <input type="text" id="search-conversations" placeholder="Buscar conversación..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            
            <div id="conversations-list" class="overflow-y-auto" style="height: calc(100% - 140px);">
                <div class="flex items-center justify-center h-full text-gray-500">
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
                        <p>Cargando conversaciones...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="lg:col-span-8 flex flex-col" style="height: 100%;">
            <div id="chat-header" class="hidden p-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-primary dark:bg-primary rounded-full flex items-center justify-center text-white font-semibold">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100" id="chat-contact-name"></h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400" id="chat-contact-phone"></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center space-x-2 px-3 py-1 rounded-lg bg-gray-100 dark:bg-gray-700">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">IA Bot</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="ai-toggle" class="sr-only peer" checked onchange="toggleAI()">
                                <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/20 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <button onclick="closeChat()" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="chat-messages" class="overflow-y-auto p-6 chat-container bg-gray-50 dark:bg-gray-900" style="flex: 1 1 0; min-height: 0;">
                <button id="load-more-btn" class="hidden w-full mb-4 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-all flex items-center justify-center space-x-2" onclick="loadMoreMessages()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                    </svg>
                    <span>Cargar mensajes anteriores</span>
                </button>
                <div id="messages-content">
                    <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
                        <div class="text-center">
                            <svg class="w-20 h-20 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-lg font-medium dark:text-gray-300">Selecciona una conversación</p>
                            <p class="text-sm mt-2 dark:text-gray-400">Elige una conversación de la lista para ver los mensajes</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="chat-input" class="hidden p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800" style="flex-shrink: 0;">
                <div class="flex items-end space-x-2">
                    <textarea id="reply-input" placeholder="Escribe tu respuesta..." rows="1" class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" style="max-height: 120px;"></textarea>
                    <button onclick="sendReply()" class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-xl font-medium transition-all flex items-center space-x-2">
                        <span>Enviar</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

ob_start();
?>

let currentFilter = 'all';
let currentConversationId = null;
let allConversations = [];
let messagesOffset = 0;
let hasMoreMessages = false;
let autoRefreshInterval = null;
let lastCheckTime = new Date().toISOString();

async function loadConversations(status = null) {
    try {
        const url = status ? `/public/api/conversations?status=${status}` : '/public/api/conversations';
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error loading conversations');
        }
        
        allConversations = data.conversations;
        renderConversationsList(allConversations);
        updateFilterButtons();
        
    } catch (error) {
        document.getElementById('conversations-list').innerHTML = `
            <div class="p-4 text-center text-red-600">
                <svg class="w-12 h-12 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <p class="font-medium">Error al cargar</p>
                <p class="text-sm">${error.message}</p>
            </div>
        `;
    }
}

function renderConversationsList(conversations) {
    const container = document.getElementById('conversations-list');
    
    if (conversations.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <p class="font-medium">No hay conversaciones</p>
                <p class="text-sm mt-1">Las conversaciones aparecerán aquí</p>
            </div>
        `;
        return;
    }
    
    const existingItems = container.querySelectorAll('[data-conv-id]');
    const existingMap = new Map();
    existingItems.forEach(item => {
        existingMap.set(parseInt(item.dataset.convId), item);
    });
    
    const orderedElements = [];
    
    conversations.forEach(conv => {
        const statusColor = conv.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                          conv.status === 'pending_human' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
        const statusText = conv.status === 'active' ? 'Activa' : 
                         conv.status === 'pending_human' ? 'Pendiente' : 'Cerrada';
        
        const lastMessage = conv.recent_messages && conv.recent_messages.length > 0 
            ? conv.recent_messages[conv.recent_messages.length - 1].message_text 
            : 'Sin mensajes';
        
        const timeAgo = formatTimeAgo(new Date(conv.last_message_at));
        const initial = (conv.contact_name || conv.phone_number).charAt(0).toUpperCase();
        
        const existingItem = existingMap.get(conv.id);
        
        // Verificar si necesita actualización
        const needsUpdate = !existingItem || 
            existingItem.dataset.lastUpdate !== conv.last_message_at ||
            existingItem.dataset.status !== conv.status ||
            (currentConversationId === conv.id && !existingItem.classList.contains('bg-gray-100'));
        
        if (existingItem && !needsUpdate) {
            orderedElements.push(existingItem);
            existingMap.delete(conv.id);
        } else {
            const div = document.createElement('div');
            div.dataset.convId = conv.id;
            div.dataset.lastUpdate = conv.last_message_at;
            div.dataset.status = conv.status;
            div.onclick = () => viewConversation(conv.id, conv.contact_name || 'Sin nombre', conv.phone_number);
            div.className = `border-b border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer ${currentConversationId === conv.id ? 'bg-gray-100 dark:bg-gray-700 border-l-4 border-l-primary' : ''}`;
            
            div.innerHTML = `
                <div class="flex items-start space-x-3">
                    <div class="w-12 h-12 bg-primary rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                        ${initial}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100 truncate">${conv.contact_name || 'Sin nombre'}</h4>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">${timeAgo}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 truncate mb-1">${conv.phone_number}</p>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate flex-1">${lastMessage.substring(0, 50)}${lastMessage.length > 50 ? '...' : ''}</p>
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColor}">
                                ${statusText}
                            </span>
                        </div>
                    </div>
                </div>
            `;
            
            fragment.appendChild(div);
            existingMap.delete(conv.id);
        }
    });
    
    // Solo actualizar si hay cambios
    if (fragment.children.length > 0 || existingMap.size > 0) {
        container.innerHTML = '';
        container.appendChild(fragment);
    }
}

function updateFilterButtons() {
    const buttons = {
        'all': document.getElementById('filter-all'),
        'active': document.getElementById('filter-active'),
        'pending_human': document.getElementById('filter-pending')
    };
    
    Object.keys(buttons).forEach(key => {
        if (key === currentFilter) {
            buttons[key].className = 'flex-1 px-3 py-2 text-sm font-medium rounded-lg bg-primary text-white transition-all';
        } else {
            buttons[key].className = 'flex-1 px-3 py-2 text-sm font-medium rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all';
        }
    });
}

function formatTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if (seconds < 60) return 'Ahora';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm';
    if (seconds < 86400) return Math.floor(seconds / 3600) + 'h';
    if (seconds < 604800) return Math.floor(seconds / 86400) + 'd';
    return date.toLocaleDateString();
}

function filterConversations(status) {
    currentFilter = status;
    loadConversations(status === 'all' ? null : status);
}

async function viewConversation(id, name, phone) {
    currentConversationId = id;
    messagesOffset = 0;
    
    const conv = allConversations.find(c => c.id === id);
    if (!conv) return;
    
    document.getElementById('chat-header').classList.remove('hidden');
    document.getElementById('chat-input').classList.remove('hidden');
    document.getElementById('chat-contact-name').textContent = name;
    document.getElementById('chat-contact-phone').textContent = phone;
    
    const aiToggle = document.getElementById('ai-toggle');
    aiToggle.checked = conv.ai_enabled !== false;
    
    await loadMessages(id);
    renderConversationsList(allConversations);
    
    startAutoRefresh();
}

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    
    autoRefreshInterval = setInterval(async () => {
        if (currentConversationId) {
            try {
                const response = await fetch(`/public/api/check-updates?last_check=${encodeURIComponent(lastCheckTime)}&conversation_id=${currentConversationId}`);
                const data = await response.json();
                
                if (data.success && data.has_update) {
                    const chatMessages = document.getElementById('chat-messages');
                    const scrollPos = chatMessages.scrollTop;
                    const scrollHeight = chatMessages.scrollHeight;
                    const isAtBottom = (scrollHeight - scrollPos - chatMessages.clientHeight) < 100;
                    
                    const currentOffset = messagesOffset;
                    messagesOffset = 0;
                    
                    await loadMessages(currentConversationId);
                    
                    messagesOffset = currentOffset;
                    await loadConversations(currentFilter === 'all' ? null : currentFilter);
                    
                    if (isAtBottom) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                    
                    lastCheckTime = new Date().toISOString();
                }
            } catch (error) {
                console.error('Error checking updates:', error);
            }
        }
    }, 2000);
}

async function loadMessages(conversationId, append = false) {
    try {
        const response = await fetch(`/public/api/conversations/${conversationId}/messages?offset=${messagesOffset}&limit=20`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Error loading messages');
        }
        
        hasMoreMessages = data.has_more;
        const loadMoreBtn = document.getElementById('load-more-btn');
        const messagesContent = document.getElementById('messages-content');
        
        if (hasMoreMessages) {
            loadMoreBtn.classList.remove('hidden');
        } else {
            loadMoreBtn.classList.add('hidden');
        }
        
        let messagesHtml = '<div class="space-y-4 pb-4">';
        
        if (!data.messages || data.messages.length === 0) {
            messagesHtml += '<div class="text-center text-gray-500 dark:text-gray-400 py-8">No hay mensajes en esta conversación</div>';
        } else {
            data.messages.forEach(msg => {
                const isUser = msg.sender_type === 'user';
                const isBot = msg.sender_type === 'bot';
                const time = new Date(msg.created_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
                
                messagesHtml += `
                    <div class="flex ${isUser ? 'justify-start' : 'justify-end'} message-bubble">
                        <div class="max-w-xs lg:max-w-md xl:max-w-lg">
                            <div class="${isUser ? 'bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600' : 'bg-primary'} rounded-2xl px-4 py-2 shadow-sm">
                                <p class="${isUser ? 'text-gray-900 dark:text-gray-100' : 'text-white'} text-sm break-words">${msg.message_text}</p>
                            </div>
                            <div class="flex items-center ${isUser ? 'justify-start' : 'justify-end'} mt-1 px-2 space-x-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400">${time}</span>
                                ${msg.confidence_score ? `<span class="text-xs text-gray-400 dark:text-gray-500">(${Math.round(msg.confidence_score * 100)}%)</span>` : ''}
                                ${!isUser ? '<svg class="w-4 h-4 text-blue-500 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path></svg>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        messagesHtml += '</div>';
        
        if (append) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = messagesHtml;
            const newMessages = tempDiv.firstChild;
            const existingMessages = messagesContent.querySelector('.space-y-4');
            
            if (existingMessages && newMessages) {
                Array.from(newMessages.children).reverse().forEach(child => {
                    existingMessages.insertBefore(child, existingMessages.firstChild);
                });
            }
        } else {
            const existingContainer = messagesContent.querySelector('.space-y-4');
            
            const lastMsg = data.messages.length > 0 ? data.messages[data.messages.length - 1] : null;
            const currentHash = lastMsg ? `${lastMsg.id}-${lastMsg.message_text.length}` : 'empty';
            
            if (!existingContainer || 
                existingContainer.dataset.messageHash !== currentHash ||
                existingContainer.children.length !== data.messages.length) {
                
                messagesContent.innerHTML = messagesHtml;
                
                const newContainer = messagesContent.querySelector('.space-y-4');
                if (newContainer) {
                    newContainer.dataset.messageHash = currentHash;
                }
                
                const chatMessages = document.getElementById('chat-messages');
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
    } catch (error) {
        console.error('Error loading messages:', error);
        const messagesContent = document.getElementById('messages-content');
        messagesContent.innerHTML = `
            <div class="text-center text-red-500 dark:text-red-400 py-8">
                Error al cargar mensajes: ${error.message}
            </div>
        `;
    }
}

async function loadMoreMessages() {
    if (!currentConversationId || !hasMoreMessages) return;
    
    const loadMoreBtn = document.getElementById('load-more-btn');
    const originalText = loadMoreBtn.innerHTML;
    
    loadMoreBtn.disabled = true;
    loadMoreBtn.innerHTML = `
        <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        <span>Cargando...</span>
    `;
    
    const chatMessages = document.getElementById('chat-messages');
    const scrollHeightBefore = chatMessages.scrollHeight;
    
    messagesOffset += 20;
    await loadMessages(currentConversationId, true);
    
    const scrollHeightAfter = chatMessages.scrollHeight;
    chatMessages.scrollTop = scrollHeightAfter - scrollHeightBefore;
    
    loadMoreBtn.disabled = false;
    loadMoreBtn.innerHTML = originalText;
}

function closeChat() {
    document.getElementById('chat-header').classList.add('hidden');
    document.getElementById('chat-input').classList.add('hidden');
    document.getElementById('load-more-btn').classList.add('hidden');
    document.getElementById('messages-content').innerHTML = `
        <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-500">
            <div class="text-center">
                <svg class="w-20 h-20 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <p class="text-lg font-medium dark:text-gray-300">Selecciona una conversación</p>
                <p class="text-sm mt-2 dark:text-gray-400">Elige una conversación de la lista para ver los mensajes</p>
            </div>
        </div>
    `;
    currentConversationId = null;
    messagesOffset = 0;
    
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    
    renderConversationsList(allConversations);
}

async function sendReply() {
    const textarea = document.getElementById('reply-input');
    const message = textarea.value.trim();
    
    if (!message) {
        return;
    }
    
    const sendButton = event.target;
    sendButton.disabled = true;
    sendButton.innerHTML = '<span class="inline-block animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>';
    
    try {
        const response = await fetch(`/public/api/conversations/${currentConversationId}/reply`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({message})
        });
        
        const data = await response.json();
        
        if (data.success) {
            textarea.value = '';
            textarea.style.height = 'auto';
            
            await loadMessages(currentConversationId);
            await loadConversations(currentFilter === 'all' ? null : currentFilter);
            
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } else {
            throw new Error(data.error);
        }
        
    } catch (error) {
        alert('Error al enviar respuesta: ' + error.message);
    } finally {
        sendButton.disabled = false;
        sendButton.innerHTML = '<span>Enviar</span><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>';
    }
}

async function toggleAI() {
    if (!currentConversationId) return;
    
    const aiToggle = document.getElementById('ai-toggle');
    const newState = aiToggle.checked;
    
    try {
        const response = await fetch(`/public/api/conversations/${currentConversationId}/ai-toggle`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ai_enabled: newState})
        });
        
        const data = await response.json();
        
        if (data.success) {
            const conv = allConversations.find(c => c.id === currentConversationId);
            if (conv) {
                conv.ai_enabled = newState;
            }
            
            const notification = document.createElement('div');
            notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            notification.innerHTML = `IA Bot ${newState ? 'activado' : 'desactivado'}`;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 2000);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        aiToggle.checked = !newState;
        alert('Error al cambiar estado de IA: ' + error.message);
    }
}

document.getElementById('reply-input')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

document.getElementById('reply-input')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendReply();
    }
});

document.getElementById('search-conversations')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const filtered = allConversations.filter(conv => 
        (conv.contact_name && conv.contact_name.toLowerCase().includes(search)) ||
        conv.phone_number.includes(search)
    );
    renderConversationsList(filtered);
});

loadConversations();
setInterval(() => {
    if (currentConversationId) {
        const oldConv = allConversations.find(c => c.id === currentConversationId);
        loadConversations(currentFilter === 'all' ? null : currentFilter).then(() => {
            const conv = allConversations.find(c => c.id === currentConversationId);
            if (conv && JSON.stringify(conv.recent_messages) !== JSON.stringify(oldConv?.recent_messages)) {
                viewConversation(conv.id, conv.contact_name || 'Sin nombre', conv.phone_number);
            }
        });
    } else {
        loadConversations(currentFilter === 'all' ? null : currentFilter);
    }
}, 15000);

<?php
$scripts = ob_get_clean();

require __DIR__ . '/layout.php';
?>
