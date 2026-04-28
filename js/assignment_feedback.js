(function() {
    function getUserIdFromUrl() {
        const url = window.location.href;
        const match = url.match(/[?&]userid=(\d+)/);
        if (match) return parseInt(match[1]);
        const matchU = url.match(/[?&]u=(\d+)/);
        if (matchU) return parseInt(matchU[1]);
        return 0;
    }

    function setEditorContent(text) {
        const htmlText = text.replace(/\n/g, "<br>");
        let success = false;

        if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor) {
            try {
                window.tinyMCE.activeEditor.setContent(htmlText);
                window.tinyMCE.triggerSave(); 
                success = true;
            } catch(e) {}
        }

        const atto = document.querySelector(".editor_atto_content");
        if (atto) {
            atto.innerHTML = htmlText;
            atto.dispatchEvent(new Event('input', { bubbles: true }));
            success = true;
        }

        const textarea = document.querySelector('textarea[name*="assignfeedbackcomments"]');
        if (textarea) {
            textarea.value = htmlText; 
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
            success = true;
        }
        
        return success;
    }

    function createButton() {
        if (document.getElementById("ai-feedback-btn-teacher")) return;

        let textarea = document.querySelector('textarea[name*="assignfeedbackcomments"]');
        let container = null;

        if (textarea) {
            container = textarea.closest('.fitem') || textarea.parentNode;
        } else {
            container = document.querySelector('[data-region="grade-panel"] .gradingform');
        }

        if (!container) return;

        let wrap = document.createElement("div");
        wrap.className = "ai-btn-wrapper";
        wrap.style.marginBottom = "15px";
        wrap.style.marginTop = "10px";
        wrap.innerHTML = `
            <button id="ai-feedback-btn-teacher" type="button" class="btn btn-primary" 
                style="width:100%; background:#0f6cbf; padding:10px; font-weight:bold; color:white; border:none; border-radius:4px;">
                Generate AI Feedback
            </button>
            <div id="ai-status" style="display:none; margin-top:5px; color:#0f6cbf; font-weight:bold;">
                sedang membaca jawaban & menilai...
            </div>
        `;

        if (textarea && container.parentNode) {
            container.parentNode.insertBefore(wrap, container);
        } else {
            container.prepend(wrap);
        }

        document.getElementById("ai-feedback-btn-teacher").addEventListener("click", runFeedback);
    }

    function runFeedback() {
        let btn = document.getElementById("ai-feedback-btn-teacher");
        let status = document.getElementById("ai-status");
        let currentUserId = getUserIdFromUrl();

        if (currentUserId === 0 && window.AIS_CONFIG && window.AIS_CONFIG.userId) {
            currentUserId = window.AIS_CONFIG.userId;
        }

        if (currentUserId === 0) {
            alert("Error: Tidak dapat mendeteksi ID Mahasiswa dari URL.\nPastikan URL mengandung '&userid=ANGKA'.");
            return;
        }

        btn.disabled = true;
        status.style.display = "block";

        require(["jquery", "core/ajax", "core/notification"], function($, ajax, notification) {
            ajax.call([{
                methodname: "local_ai_assistant_generate_feedback",
                args: {
                    submissionid: 0,
                    assignmentid: window.AIS_CONFIG ? window.AIS_CONFIG.assignmentId : 0,
                    userid: currentUserId
                }
            }])[0].done(function(response) {
                if (!response.success) {
                    notification.alert("Gagal", response.error, "OK");
                    return;
                }

                const data = response.feedback;
                setEditorContent(data.feedback);

                if (data.grade) {
                    let gradeInput = $('input[name*="grade"]').filter('[type="text"]');
                    if(gradeInput.length) {
                        gradeInput.val(data.grade).trigger("change");
                        gradeInput.css('background-color', '#d4edda');
                    }
                }

                notification.alert("Berhasil", "Feedback & Nilai berhasil dibuat!", "OK");

            }).fail(notification.exception)
              .always(function() {
                btn.disabled = false;
                status.style.display = "none";
            });
        });
    }

    setInterval(createButton, 1000);
})();