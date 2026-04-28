require(['jquery', 'core/notification'], function($, notification) {

    $(document).ready(function() {

        var $form = $('#generate-form');
        if (!$form.length) return;

        var $loadingArea    = $('#loading-area');
        var $previewArea    = $('#preview-area');
        var $previewContent = $('#preview-content');
        var $inputQuizId    = $('#input-quiz-id');
        var $btnRegenerate  = $('#btn-regenerate');
        var $resultArea     = $('#result-area');
        var $submitBtn      = $('#generate-btn');

        var config = {
            wwwroot: $form.data('wwwroot'),
            courseid: $form.data('courseid'),
            sesskey: $form.data('sesskey')
        };

        $form.on('submit', function(e) {
            e.preventDefault();

            $resultArea.empty();
            $previewArea.hide();
            $loadingArea.show();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sedang Membuat Soal...');

            var formData = {
                courseid: config.courseid,
                sesskey: config.sesskey,
                sectionid: $('#sectionid').val(),
                count: $('#question-count').val(),
                difficulty: $('#difficulty').val(),
                option_count: $('#option_count').val(), 
                qtype: $('#qtype').val()
            };

            if (!formData.sectionid) {
                notification.alert('Peringatan', 'Silakan pilih topik materi terlebih dahulu.', 'OK');
                $submitBtn.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate Soal');
                $loadingArea.hide();
                return;
            }

            $.ajax({
                url: config.wwwroot + '/local/ai_assistant/quiz_generator.php',
                type: 'POST',
                data: formData,
                dataType: 'json'
            })
            .done(function(response) {
                $loadingArea.hide();

                if (response.success) {
                    $previewArea.fadeIn();
                    var giftText = response.gift_text_preview || response.quiz_data || "(Preview tidak tersedia)";
                    $previewContent.text(giftText);
                    $inputQuizId.val(response.quiz_id);

                    $('html, body').animate({
                        scrollTop: $previewArea.offset().top - 100
                    }, 500);

                    $resultArea.html('<div class="alert alert-info">Soal berhasil dibuat! Silakan review di bawah.</div>');

                } else {
                    var errorMsg = response.error || 'Unknown error from server';
                    notification.alert('Gagal', errorMsg, 'OK');
                    $resultArea.html('<div class="alert alert-danger"> ' + errorMsg + '</div>');
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                $loadingArea.hide();
                var msg = errorThrown;
                if(jqXHR.responseJSON && jqXHR.responseJSON.error) {
                    msg = jqXHR.responseJSON.error;
                } else if (jqXHR.responseText) {
                    msg = "Terjadi kesalahan server (Lihat Console). Status: " + textStatus;
                }

                notification.alert('Kesalahan Koneksi', 'Gagal menghubungi server: ' + msg, 'OK');
                $resultArea.html('<div class="alert alert-danger"> Gagal: ' + msg + '</div>');
            })
            .always(function() {
                $submitBtn.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate Soal');
            });
        });

        $btnRegenerate.on('click', function() {
            $form.trigger('submit');
        });

    }); 
});