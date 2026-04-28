document.addEventListener('DOMContentLoaded', function() {

    const chatForm = document.getElementById('chat-form');
    if (!chatForm) return;

    const chatInput = document.getElementById('chat-input');
    const chatWindow = document.getElementById('chat-window');
    const courseId = document.getElementById('course-id').value;
    const sectionId = document.getElementById('section-id').value;
    const sesskey = document.getElementById('sesskey').value;
    const chatScope = document.getElementById('chat-scope');
    const runRagButton = document.getElementById('run-rag');
    const apiUrl = M.cfg.wwwroot + '/local/ai_assistant/chatbot_api.php';

    function formatText(text) {
        if (!text) return '';
        let formatted = text.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
        formatted = formatted.replace(/\*(.*?)\*/g, '<i>$1</i>');
        formatted = formatted.replace(/\n/g, '<br>');
        formatted = formatted.replace(/^- /gm, '• ');
        return formatted;
    }

    function appendMessage(sender, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chat-message ' + sender;
        if (sender === 'bot') {
            messageDiv.innerHTML = formatText(text);
        } else {
            messageDiv.textContent = text; 
        }
        chatWindow.appendChild(messageDiv);
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    function removeLastBotMessage() {
        const messages = chatWindow.querySelectorAll('.chat-message.bot');
        if (messages.length > 0) {
            const lastMessage = messages[messages.length - 1];
            if (lastMessage.innerHTML.includes('Mengetik...')) {
                lastMessage.remove();
            }
        }
    }

    function parseJsonSafe(response) {
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                return { success: false, error: 'Invalid JSON from server', raw: text };
            }
        });
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const message = chatInput.value.trim();
        if (message === '') return;

        appendMessage('user', message);
        chatInput.value = '';
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'chat-message bot';
        loadingDiv.innerHTML = '<em>Mengetik...</em>';
        chatWindow.appendChild(loadingDiv);
        chatWindow.scrollTop = chatWindow.scrollHeight;

        const currentScope = chatScope ? chatScope.value : 'topic';
        
        const formData = new FormData();
        formData.append('message', message);
        formData.append('sectionid', sectionId);
        formData.append('courseid', courseId);
        formData.append('sesskey', sesskey);
        formData.append('scope', currentScope);

        fetch(apiUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Server error: ' + response.status);
            return parseJsonSafe(response);
        })
        .then(data => {
            removeLastBotMessage();

            if (data.success) {
                const reply = data.response || ' Bot memberikan respons (kosong).';
                appendMessage('bot', reply);
            } else {
                const err = data.error || data.raw || 'Unknown error';
                appendMessage('bot', 'Maaf, terjadi kesalahan: ' + err);
            }
        })
        .catch(error => {
            removeLastBotMessage();
            appendMessage('bot', 'Maaf, terjadi kesalahan koneksi.');
        });
    });

    if (runRagButton) {
        runRagButton.addEventListener('click', function() {
            const scope = chatScope ? chatScope.value : 'topic';
            
            if(!confirm("Proses ini akan membaca ulang materi Moodle untuk scope: " + scope + ". Lanjutkan?")) return;

            runRagButton.disabled = true;
            runRagButton.textContent = ' Memproses...';

            const formData = new FormData();
            formData.append('courseid', courseId);
            formData.append('sectionid', sectionId);
            formData.append('sesskey', sesskey);
            formData.append('scope', scope);

            fetch(M.cfg.wwwroot + '/local/ai_assistant/run_rag.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                if (!response.ok) throw new Error('Server error: ' + response.status);
                return parseJsonSafe(response);
            })
            .then(data => {
                if (data.success) {
                    const msgDiv = document.createElement('div');
                    msgDiv.className = 'chat-message bot';
                    msgDiv.innerHTML = '<b>Materi berhasil diproses ulang!</b><br>Scope: ' + scope + '.<br>Silakan ajukan pertanyaan baru.';
                    chatWindow.appendChild(msgDiv);
                } else {
                    const err = data.error || data.raw || 'Unknown error';
                    appendMessage('bot', ' Gagal memproses materi: ' + err);
                }
                chatWindow.scrollTop = chatWindow.scrollHeight;
            })
            .catch(error => {
                appendMessage('bot', ' Terjadi kesalahan saat memproses materi.');
            })
            .finally(() => {
                runRagButton.disabled = false;
                runRagButton.textContent = 'Update Materi';
            });
        });
    }

    if (chatScope) {
        chatScope.addEventListener('change', function() {
            const scope = this.value;
            let message = '';

            switch(scope) {
                case 'topic':
                    message = '<i>Mode: Fokus pada topik pertemuan ini saja.</i>';
                    break;
                case 'midterm':
                    message = '<i>Mode: Fokus pada materi UTS (Topik 1-7).</i>';
                    break;
                case 'final':
                    message = '<i>Mode: Fokus pada materi UAS (Topik 9-15).</i>';
                    break;
                default:
                    message = '<i>Mode pencarian diubah.</i>';
            }

            const div = document.createElement('div');
            div.className = 'chat-message bot';
            div.style.fontSize = '0.9em';
            div.style.color = '#666';
            div.innerHTML = message;
            chatWindow.appendChild(div);
            chatWindow.scrollTop = chatWindow.scrollHeight;
        });
    }
});