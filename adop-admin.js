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
        });
    });

    // Xóa thủ công bằng AJAX (batch)
    let stopped = false;
    let totalDeleted = 0;
    let totalToDelete = 4; // hoặc lấy từ input cấu hình

    $('#adop-manual-delete-btn').on('click', function(e) {
        e.preventDefault();
        stopped = false;
        totalDeleted = 0;
        var $btn = $(this);
        $btn.prop('disabled', true);
        var $msg = $('#adop-delete-message');
        if ($msg.length === 0) {
            $msg = $('<div id="adop-delete-message" class="notice"></div>').insertBefore($btn);
        }
        var $titleList = $('#adop-delete-titles');
        if ($titleList.length === 0) {
            $titleList = $('<ul id="adop-delete-titles" style="margin-top:10px;"></ul>').insertAfter($msg);
        } else {
            $titleList.empty();
        }
        var batchLimit = parseInt($('#adop-manual-batch-limit').val()) || 1;
        function runBatch() {
            if (stopped || totalDeleted >= totalToDelete) {
                $btn.prop('disabled', false);
                $('#adop-stop-btn').prop('disabled', true);
                $msg.text('Đã dừng hoặc đã xóa đủ số lượng!');
                return;
            }
            $.post(adopAjax.ajax_url, {
                action: 'adop_manual_delete_batch',
                nonce: adopAjax.nonce_delete,
                batch_limit: batchLimit
            }, function(res) {
                if(res.success) {
                    totalDeleted += res.data.deleted;
                    $msg.text(`Đã xóa ${totalDeleted}/${totalToDelete} bài. ${res.data.message}`);
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
                        $('#adop-stop-btn').prop('disabled', true);
                    }
                } else {
                    $msg.removeClass('notice-success').addClass('notice-error').text(res.data && res.data.message ? res.data.message : 'Lỗi không xác định!');
                    $btn.prop('disabled', false);
                }
            });
        }
        runBatch();
    });

    $('#adop-stop-btn').on('click', function() {
        stopped = true;
        $(this).prop('disabled', true);
    });
}); 