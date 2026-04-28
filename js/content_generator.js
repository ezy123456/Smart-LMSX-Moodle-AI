(function() {
    document.addEventListener("DOMContentLoaded", function() {
        
        const DOM = {
            modeSelector: 'ai-mode-selector',
            chatbotMode: 'chatbot-mode',
            coauthorMode: 'coauthor-mode',
            selectMoodleTopic: 'ai-select-moodle-topic',
            inputDescription: 'ai-input-description',
            inputSubtopicsSelect: 'ai-input-subtopics-select', 
            subtopicPreview: 'ai-subtopic-preview',
            subtopicText: 'ai-subtopic-text',
            inputInstructions: 'ai-input-instructions',
            inputLevel: 'ai-input-level',
            btnGenerate: 'ai-btn-generate-content',
            resultArea: 'ai-content-result',
            loadingIcon: 'ai-loading-spinner'
        };

        const modeSelector = document.getElementById(DOM.modeSelector);
        if (modeSelector) {
            modeSelector.addEventListener('change', function() {
                const chatbotDiv = document.getElementById(DOM.chatbotMode);
                const coauthorDiv = document.getElementById(DOM.coauthorMode);
                if (this.value === 'coauthor') {
                    chatbotDiv.style.display = 'none';
                    coauthorDiv.style.display = 'block';
                } else {
                    chatbotDiv.style.display = 'block';
                    coauthorDiv.style.display = 'none';
                }
            });
            modeSelector.value = 'coauthor';
            modeSelector.dispatchEvent(new Event('change'));
        }

        const topicSelect = document.getElementById(DOM.selectMoodleTopic);
        if (topicSelect && window.AI_COURSE_TOPICS) {
            topicSelect.addEventListener('change', function() {
                const selectedTopicId = this.value;
                const descInput = document.getElementById(DOM.inputDescription);
                const subtopicSelect = document.getElementById(DOM.inputSubtopicsSelect);
                const previewDiv = document.getElementById(DOM.subtopicPreview);
                
                descInput.value = '';
                subtopicSelect.innerHTML = '<option value="" disabled selected>-- Pilih Sub-Topik --</option>';
                if(previewDiv) previewDiv.style.display = 'none';
                
                if (!selectedTopicId) {
                    subtopicSelect.innerHTML = '<option value="" disabled selected>-- Pilih Topik Moodle di atas terlebih dahulu --</option>';
                    return;
                }

                const topicData = window.AI_COURSE_TOPICS[selectedTopicId];
                if (topicData) {
                    descInput.value = topicData.description || "(Tidak ada deskripsi)";
                    
                    if (topicData.subtopics && topicData.subtopics.length > 0) {
                        topicData.subtopics.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub;
                            option.text = sub.length > 80 ? sub.substring(0, 80) + '...' : sub; 
                            subtopicSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.text = "Gunakan Deskripsi Utama saja";
                        option.value = topicData.description;
                        subtopicSelect.appendChild(option);
                    }
                }
            });
        }

        const subSelect = document.getElementById(DOM.inputSubtopicsSelect);
        if (subSelect) {
            subSelect.addEventListener('change', function() {
                const selectedText = this.value;
                const previewDiv = document.getElementById(DOM.subtopicPreview);
                const previewText = document.getElementById(DOM.subtopicText);

                if (selectedText) {
                    previewText.innerText = selectedText;
                    previewDiv.style.display = 'block';
                    previewDiv.style.opacity = 0;
                    setTimeout(() => previewDiv.style.opacity = 1, 50);
                    previewDiv.style.transition = 'opacity 0.3s ease-in';
                } else {
                    previewDiv.style.display = 'none';
                }
            });
        }

        const btn = document.getElementById(DOM.btnGenerate);
        if (btn) {
            btn.addEventListener('click', async function() {
                const descInput = document.getElementById(DOM.inputDescription);
                const subtopicSelect = document.getElementById(DOM.inputSubtopicsSelect);
                const instructionsInput = document.getElementById(DOM.inputInstructions);
                const levelInput = document.getElementById(DOM.inputLevel);
                const resultContainer = document.getElementById(DOM.resultArea);
                const loading = document.getElementById(DOM.loadingIcon);

                const description = descInput.value.trim();
                const subtopic = subtopicSelect.value;
                const instructions = instructionsInput.value.trim();
                
                if (!description) {
                    alert("Silakan pilih Topik Moodle terlebih dahulu!");
                    return;
                }
                if (!subtopic) {
                    alert("Silakan pilih salah satu Sub-Topik yang ingin dibuatkan materinya!");
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = "Sedang Menulis...";
                if(loading) loading.style.display = 'inline-block';
                resultContainer.innerHTML = '';

                try {
                    const formData = new FormData();
                    formData.append('description', description); 
                    formData.append('subtopics', subtopic); 
                    formData.append('instructions', instructions);
                    formData.append('level', levelInput ? levelInput.value : 'Mahasiswa S1');
                    
                    const apiUrl = M.cfg.wwwroot + '/local/ai_assistant/content_generator.php';
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if(loading) loading.style.display = 'none';
                    btn.disabled = false;
                    btn.innerHTML = "Buat Materi";

                    if (data.success) {
                        let content = data.content
                            .replace(/## (.*?)\n/g, '<h4>$1</h4>')
                            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.*?)\*/g, '<em>$1</em>')
                            .replace(/\n/g, '<br>');

                        resultContainer.innerHTML = `
                            <div style="background:#fff; border:1px solid #ddd; padding:20px; border-radius:8px; margin-top:15px; text-align:left;">
                                <h4 style="margin-top:0; color:#0f6cbf;">Draft Materi: ${subtopic.substring(0, 50)}...</h4>
                                <hr>
                                <div style="line-height:1.6; color:#333;">${content}</div>
                                <div style="margin-top:15px; text-align:right;">
                                    <button onclick="navigator.clipboard.writeText(this.parentElement.previousElementSibling.innerText); alert('Teks disalin!');" 
                                            class="btn btn-secondary btn-sm">
                                         Salin Teks
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        alert("Gagal: " + data.error);
                    }
                } catch (error) {
                    btn.disabled = false;
                    btn.innerHTML = "Buat Materi";
                    alert("Kesalahan koneksi.");
                }
            });
        }
    });
})();