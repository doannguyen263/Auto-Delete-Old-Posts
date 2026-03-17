jQuery(document).ready(function($) {
    // Lưu cấu hình bằng AJAX
    $('#adop-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var data = $form.serializeArray();
        data.push({name: 'action', value: 'adop_save_settings'});
        data.push({name: 'nonce', value: adopAjax.nonce_settings});
        $form.find('button[type=submit]').prop('disabled', true);
        $('#adop-settings-message').remove();
        $.post(adopAjax.ajax_url, data, function(res) {
            $form.find('button[type=submit]').prop('disabled', false);
            var msg = $('<div id="adop-settings-message" class="notice"></div>');
            if(res.success) {
                msg.addClass('notice-success').text(res.data.message);
            } else {
                msg.addClass('notice-error').text(res.data && res.data.message ? res.data.message : 'Lỗi không xác định!');
            }
            $form.prepend(msg);
            setTimeout(function(){ msg.fadeOut(); }, 3000);
        }).fail(function() {
            $form.find('button[type=submit]').prop('disabled', false);
            $form.prepend($('<div id="adop-settings-message" class="notice notice-error">Lỗi kết nối. Thử lại.</div>'));
        });
    });

    // Xóa thủ công bằng AJAX (batch)
    let stopped = false;
    let totalDeleted = 0;

    $('#adop-manual-delete-btn').on('click', function(e) {
        e.preventDefault();
        stopped = false;
        totalDeleted = 0;
        var $form = $('#adop-manual-delete-form');
        var totalToDelete = Math.max(1, parseInt($form.find('#adop-manual-total-limit').val(), 10) || 1);
        var batchLimit = Math.max(1, parseInt($form.find('#adop-manual-batch-limit').val(), 10) || 1);
        var $btn = $(this);
        var $stopBtn = $('#adop-stop-btn');
        $btn.prop('disabled', true);
        $stopBtn.removeAttr('disabled').prop('disabled', false);
        $('#adop-delete-output').show();
        var $processEl = $('#adop-delete-list-box-process');
        var $titleList = $('#adop-delete-titles').empty();
        $processEl.css({ background: '#f0f6fc', borderLeftColor: '#72aee6' }).html('Đang bắt đầu…');
        function runBatch() {
            if (stopped || totalDeleted >= totalToDelete) {
                $btn.prop('disabled', false);
                $('#adop-stop-btn').prop('disabled', true).attr('disabled', 'disabled');
                $processEl.html('Đã dừng hoặc đã xóa đủ số lượng!');
                return;
            }
            $.post(adopAjax.ajax_url, {
                action: 'adop_manual_delete_batch',
                nonce: adopAjax.nonce_delete,
                batch_limit: batchLimit
            }, function(res) {
                if(res.success) {
                    totalDeleted += res.data.deleted;
                    // Show progress: never exceed target (e.g. target 4, batch 20 -> show "4/4" not "20/4")
                    var shown = totalDeleted > totalToDelete ? totalToDelete : totalDeleted;
                    var progress = 'Đã xóa ' + shown + '/' + totalToDelete + ' bài (mục tiêu).';
                    if (res.data.done) {
                        $processEl.html(progress + ' Không còn bài nào phù hợp để xóa.');
                    } else {
                        $processEl.html(progress + ' Lượt vừa rồi: ' + res.data.deleted + ' bài.');
                    }
                    // Hiển thị tiêu đề bài đã xóa
                    if(res.data.titles && res.data.titles.length) {
                        res.data.titles.forEach(function(title) {
                            $titleList.append('<li>' + $('<div>').text(title).html() + '</li>');
                        });
                    }
                    if(!res.data.done && totalDeleted < totalToDelete) {
                        setTimeout(runBatch, 500);
                    } else {
                        $btn.prop('disabled', false);
                        $('#adop-stop-btn').prop('disabled', true).attr('disabled', 'disabled');
                    }
                } else {
                    $processEl.css({ background: '#fcf0f1', borderLeftColor: '#d63638' }).html(res.data && res.data.message ? res.data.message : 'Lỗi không xác định!');
                    $btn.prop('disabled', false);
                    $('#adop-stop-btn').prop('disabled', true).attr('disabled', 'disabled');
                }
            }).fail(function() {
                $processEl.css({ background: '#fcf0f1', borderLeftColor: '#d63638' }).html('Lỗi kết nối. Thử lại.');
                $btn.prop('disabled', false);
                $('#adop-stop-btn').prop('disabled', true).attr('disabled', 'disabled');
            });
        }
        runBatch();
    });

    $('#adop-stop-btn').on('click', function() {
        stopped = true;
        $(this).prop('disabled', true);
    });
}); 