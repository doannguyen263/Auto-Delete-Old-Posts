<?php
/*
Plugin Name: Auto Delete Old Posts
Description: Xóa bài viết cũ hơn 3 tháng theo từng batch. Có trang tùy chỉnh theo dõi và cài đặt.
Version: 1.4
Author: Đoàn Nguyễn
*/

define('ADOP_LOG_OPTION', 'adop_delete_log');
define('ADOP_BATCH_OPTION', 'adop_batch_limit');
define('ADOP_POST_TYPE_OPTION', 'adop_post_type');
define('ADOP_ENABLE_CRON_OPTION', 'adop_enable_cron');
define('ADOP_MONTHS_OPTION', 'adop_months_ago');
define('ADOP_DELETE_TYPE_OPTION', 'adop_delete_type');
define('ADOP_CRON_HOUR_OPTION', 'adop_cron_hour');
define('ADOP_MANUAL_BATCH_OPTION', 'adop_manual_batch_limit');

define('ADOP_DEFAULT_BATCH', 50);
define('ADOP_DEFAULT_POST_TYPE', 'post');
define('ADOP_DEFAULT_MONTHS', 3);
define('ADOP_DEFAULT_DELETE_TYPE', 'trash'); // 'trash' hoặc 'delete'
define('ADOP_DEFAULT_CRON_HOUR', 0); // 0 = 12h đêm
define('ADOP_DEFAULT_MANUAL_BATCH', 2);

// Đăng ký kích hoạt plugin
register_activation_hook(__FILE__, function () {
    if (get_option(ADOP_BATCH_OPTION) === false) {
        update_option(ADOP_BATCH_OPTION, ADOP_DEFAULT_BATCH);
    }
    if (get_option(ADOP_POST_TYPE_OPTION) === false) {
        update_option(ADOP_POST_TYPE_OPTION, ADOP_DEFAULT_POST_TYPE);
    }
    if (get_option(ADOP_ENABLE_CRON_OPTION) === false) {
        update_option(ADOP_ENABLE_CRON_OPTION, 1); // bật cron mặc định
    }
    if (get_option(ADOP_MONTHS_OPTION) === false) {
        update_option(ADOP_MONTHS_OPTION, ADOP_DEFAULT_MONTHS);
    }
    if (get_option(ADOP_DELETE_TYPE_OPTION) === false) {
        update_option(ADOP_DELETE_TYPE_OPTION, ADOP_DEFAULT_DELETE_TYPE);
    }
    if (get_option(ADOP_CRON_HOUR_OPTION) === false) {
        update_option(ADOP_CRON_HOUR_OPTION, ADOP_DEFAULT_CRON_HOUR);
    }
    if (get_option(ADOP_MANUAL_BATCH_OPTION) === false) {
        update_option(ADOP_MANUAL_BATCH_OPTION, ADOP_DEFAULT_MANUAL_BATCH);
    }

    adop_schedule_cron();
});

// Gỡ bỏ cron khi tắt plugin
register_deactivation_hook(__FILE__, function () {
    adop_unschedule_cron();
});

// Tạo cron nếu được bật
function adop_schedule_cron() {
    if (get_option(ADOP_ENABLE_CRON_OPTION)) {
        adop_unschedule_cron();
        $cron_hour = intval(get_option(ADOP_CRON_HOUR_OPTION, ADOP_DEFAULT_CRON_HOUR));
        $hook = 'adop_cron_event';
        // Tính timestamp cho lần chạy tiếp theo
        $now = current_time('timestamp');
        $next = mktime($cron_hour, 0, 0, date('n', $now), date('j', $now), date('Y', $now));
        if ($next <= $now) {
            $next = strtotime('+1 day', $next);
        }
        wp_schedule_event($next, 'daily', $hook);
    }
}

// Gỡ cron (clear all occurrences)
function adop_unschedule_cron() {
    wp_clear_scheduled_hook('adop_cron_event');
}

// Xử lý cron
add_action('adop_cron_event', 'adop_delete_old_posts');

// Admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Auto Delete Old Posts',
        'Delete Old Posts',
        'manage_options',
        'adop-delete-posts',
        'adop_render_admin_page',
        'dashicons-trash',
        80
    );
});

// Xử lý nút "Xóa thủ công"
add_action('admin_post_adop_manual_delete', function () {
    if (current_user_can('manage_options') && check_admin_referer('adop_manual_delete')) {
        adop_delete_old_posts();
        wp_redirect(admin_url('admin.php?page=adop-delete-posts&run=1'));
        exit;
    }
});

// Xử lý lưu cài đặt
add_action('admin_post_adop_save_settings', function () {
    if (current_user_can('manage_options') && check_admin_referer('adop_save_settings')) {
        $limit = isset($_POST['batch_limit']) ? intval($_POST['batch_limit']) : ADOP_DEFAULT_BATCH;
        $manual_batch = isset($_POST['manual_batch_limit']) ? max(1, intval($_POST['manual_batch_limit'])) : ADOP_DEFAULT_MANUAL_BATCH;
        $post_type = sanitize_text_field($_POST['post_type'] ?? ADOP_DEFAULT_POST_TYPE);
        $delete_type = isset($_POST['delete_type']) && $_POST['delete_type'] === 'delete' ? 'delete' : 'trash';
        $enable_cron = isset($_POST['enable_cron']) ? 1 : 0;
        $months_ago = isset($_POST['months_ago']) ? max(1, intval($_POST['months_ago'])) : ADOP_DEFAULT_MONTHS;
        $cron_hour = isset($_POST['cron_hour']) ? max(0, min(23, intval($_POST['cron_hour']))) : ADOP_DEFAULT_CRON_HOUR;

        update_option(ADOP_BATCH_OPTION, max(1, $limit));
        update_option(ADOP_MANUAL_BATCH_OPTION, $manual_batch);
        update_option(ADOP_POST_TYPE_OPTION, $post_type);
        update_option(ADOP_DELETE_TYPE_OPTION, $delete_type);
        update_option(ADOP_ENABLE_CRON_OPTION, $enable_cron);
        update_option(ADOP_MONTHS_OPTION, $months_ago);
        update_option(ADOP_CRON_HOUR_OPTION, $cron_hour);

        adop_unschedule_cron(); // gỡ cron cũ
        adop_schedule_cron();   // tạo lại nếu bật

        wp_redirect(admin_url('admin.php?page=adop-delete-posts&settings=1'));
        exit;
    }
});

// Hàm xóa bài
function adop_delete_old_posts() {
    $batch_limit = intval(get_option(ADOP_BATCH_OPTION, ADOP_DEFAULT_BATCH));
    $post_type = get_option(ADOP_POST_TYPE_OPTION, ADOP_DEFAULT_POST_TYPE);
    $months_ago = intval(get_option(ADOP_MONTHS_OPTION, ADOP_DEFAULT_MONTHS));
    $delete_type = get_option(ADOP_DELETE_TYPE_OPTION, ADOP_DEFAULT_DELETE_TYPE);
    $before_str = $months_ago . ' months ago';

    $args = array(
        'post_type'      => $post_type,
        'post_status'    => array('draft', 'publish'),
        'posts_per_page' => $batch_limit,
        'date_query'     => array(
            array(
                'column' => 'post_date',
                'before' => $before_str,
            ),
        ),
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'ASC',
    );

    $posts = get_posts($args);
    $count = 0;

    foreach ($posts as $post_id) {
        if ($delete_type === 'delete') {
            wp_delete_post($post_id, true); // Xóa vĩnh viễn
        } else {
            wp_trash_post($post_id); // Chuyển vào thùng rác
        }
        $count++;
    }

    // Ghi log nếu có bài bị xóa
    if ($count > 0) {
        $log = get_option(ADOP_LOG_OPTION, []);
        $log[] = [
            'time'  => current_time('mysql'),
            'count' => $count,
            'type'  => $post_type,
            'months'=> $months_ago,
        ];
        if (count($log) > 20) {
            $log = array_slice($log, -20);
        }
        update_option(ADOP_LOG_OPTION, $log);
    }
}

// Giao diện admin
function adop_render_admin_page() {
    $log = get_option(ADOP_LOG_OPTION, []);
    $batch = get_option(ADOP_BATCH_OPTION, ADOP_DEFAULT_BATCH);
    $manual_batch = get_option(ADOP_MANUAL_BATCH_OPTION, ADOP_DEFAULT_MANUAL_BATCH);
    $post_type = get_option(ADOP_POST_TYPE_OPTION, ADOP_DEFAULT_POST_TYPE);
    $delete_type = get_option(ADOP_DELETE_TYPE_OPTION, ADOP_DEFAULT_DELETE_TYPE);
    $enable_cron = get_option(ADOP_ENABLE_CRON_OPTION, 1);
    $months_ago = get_option(ADOP_MONTHS_OPTION, ADOP_DEFAULT_MONTHS);
    $cron_hour = get_option(ADOP_CRON_HOUR_OPTION, ADOP_DEFAULT_CRON_HOUR);
    $post_types = get_post_types(['public' => true], 'objects');
    ?>
    <div class="wrap">
        <h1>Auto Delete Old Posts</h1>

        <?php if (isset($_GET['run'])): ?>
            <div class="notice notice-success"><p>Đã chạy xóa bài thủ công.</p></div>
        <?php elseif (isset($_GET['settings'])): ?>
            <div class="notice notice-success"><p>Đã lưu cài đặt thành công.</p></div>
        <?php endif; ?>

        <form id="adop-settings-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-bottom: 20px;">
            <?php wp_nonce_field('adop_save_settings'); ?>
            <input type="hidden" name="action" value="adop_save_settings">
            <h2>Cấu hình tự động</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="batch_limit">Tổng số bài cần xóa mỗi lần chạy tự động</label></th>
                    <td><input type="number" name="batch_limit" id="batch_limit" value="<?php echo esc_attr($batch); ?>" min="1" class="small-text" /> bài</td>
                </tr>
                <tr>
                    <th scope="row"><label for="manual_batch_limit">Số bài xóa mỗi lượt (batch nhỏ)</label></th>
                    <td><input type="number" name="manual_batch_limit" id="manual_batch_limit" value="<?php echo esc_attr($manual_batch); ?>" min="1" class="small-text" /> bài</td>
                </tr>
                <tr>
                    <th scope="row"><label for="months_ago">Chỉ xóa bài cũ hơn (tháng)</label></th>
                    <td><input type="number" name="months_ago" id="months_ago" value="<?php echo esc_attr($months_ago); ?>" min="1" class="small-text" /> tháng</td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_type">Loại bài viết</label></th>
                    <td>
                        <select name="post_type" id="post_type">
                            <?php foreach ($post_types as $type): ?>
                                <option value="<?php echo esc_attr($type->name); ?>" <?php selected($post_type, $type->name); ?>>
                                    <?php echo esc_html($type->labels->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Hành động xóa</th>
                    <td>
                        <label><input type="radio" name="delete_type" value="trash" <?php checked($delete_type, 'trash'); ?>> Chuyển vào thùng rác</label><br>
                        <label><input type="radio" name="delete_type" value="delete" <?php checked($delete_type, 'delete'); ?>> Xóa vĩnh viễn</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="enable_cron">Tự động xóa theo lịch</label></th>
                    <td><input type="checkbox" name="enable_cron" id="enable_cron" value="1" <?php checked($enable_cron, 1); ?> /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cron_hour">Giờ thực hiện tự động</label></th>
                    <td>
                        <select name="cron_hour" id="cron_hour">
                            <?php for ($h = 0; $h < 24; $h++): ?>
                                <option value="<?php echo esc_attr($h); ?>" <?php selected($cron_hour, $h); ?>><?php printf('%02d:00', $h); ?></option>
                            <?php endfor; ?>
                        </select> (mặc định 00:00)
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-secondary">Lưu cấu hình</button></p>
        </form>

        <form id="adop-manual-delete-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('adop_manual_delete'); ?>
            <input type="hidden" name="action" value="adop_manual_delete">
            <h2>Xóa thủ công</h2>
            <p>
                <label for="adop-manual-total-limit">Tổng số bài cần xóa (thủ công): </label>
                <input type="number" id="adop-manual-total-limit" name="manual_total_limit" min="1" value="<?php echo esc_attr($batch); ?>" style="width:100px;" />
            </p>
            <p>
                <label for="adop-manual-batch-limit">Số bài xóa mỗi lượt (batch nhỏ): </label>
                <input type="number" id="adop-manual-batch-limit" name="manual_batch_limit" min="1" value="<?php echo esc_attr($manual_batch); ?>" style="width:100px;" />
                <button type="button" class="button button-primary" id="adop-manual-delete-btn">Chạy xóa bài cũ thủ công</button>
                <button type="button" class="button" id="adop-stop-btn" disabled>Dừng lại</button>
            </p>
            <div id="adop-delete-output" style="margin-top:10px; display:none;">
                <div id="adop-delete-list-box-process" style="margin:0 0 8px 0; padding:10px 12px; min-height:24px; background:#f0f6fc; border-left:4px solid #72aee6; color:#1d2327; font-size:13px; line-height:1.5;"></div>
                <div id="adop-delete-list-box" style="max-height:260px; overflow-y:auto; border:1px solid #c3c4c7; padding:10px; background:#fff;">
                    <ul id="adop-delete-titles" style="margin:0; padding-left:20px;"></ul>
                </div>
            </div>
        </form>

        <h2>Lịch sử xóa gần đây</h2>
        <?php if (!empty($log)) : ?>
            <div style="max-width: 800px; max-height: 320px; overflow-y: auto; border: 1px solid #c3c4c7;">
                <table class="widefat striped" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Số bài đã xóa</th>
                            <th>Loại bài viết</th>
                            <th>Chỉ xóa bài cũ hơn (tháng)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($log) as $item) : ?>
                            <tr>
                                <td><?php echo esc_html($item['time'] ?? ''); ?></td>
                                <td><?php echo isset($item['count']) ? intval($item['count']) : 0; ?></td>
                                <td><?php echo esc_html($item['type'] ?? ''); ?></td>
                                <td><?php echo isset($item['months']) ? intval($item['months']) : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <p>Chưa có log xóa bài nào.</p>
        <?php endif; ?>
    </div>
    <?php
}

// AJAX: Xóa thủ công theo batch
add_action('wp_ajax_adop_manual_delete_batch', function () {
    if (!current_user_can('manage_options') || !check_ajax_referer('adop_manual_delete', 'nonce', false)) {
        wp_send_json_error(['message' => 'Không có quyền hoặc lỗi bảo mật!']);
    }
    $batch_limit = isset($_POST['batch_limit']) ? max(1, intval($_POST['batch_limit'])) : intval(get_option(ADOP_BATCH_OPTION, ADOP_DEFAULT_BATCH));
    $post_type = get_option(ADOP_POST_TYPE_OPTION, ADOP_DEFAULT_POST_TYPE);
    $months_ago = intval(get_option(ADOP_MONTHS_OPTION, ADOP_DEFAULT_MONTHS));
    $delete_type = get_option(ADOP_DELETE_TYPE_OPTION, ADOP_DEFAULT_DELETE_TYPE);
    $before_str = $months_ago . ' months ago';
    $args = array(
        'post_type'      => $post_type,
        'post_status'    => array('draft', 'publish'),
        'posts_per_page' => $batch_limit,
        'date_query'     => array([
            'column' => 'post_date',
            'before' => $before_str,
        ]),
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'ASC',
    );
    $posts = get_posts($args);
    $count = 0;
    $titles = [];
    foreach ($posts as $post_id) {
        $titles[] = get_the_title($post_id);
        if ($delete_type === 'delete') {
            wp_delete_post($post_id, true);
        } else {
            wp_trash_post($post_id);
        }
        $count++;
    }
    // Count remaining old posts (efficient: use found_posts)
    $count_query = new \WP_Query(array_merge($args, ['posts_per_page' => 1, 'no_found_rows' => false]));
    $remaining = (int) $count_query->found_posts;
    wp_send_json_success([
        'deleted' => $count,
        'remaining' => $remaining,
        'titles' => $titles,
        'message' => $count > 0 ? "Đã xóa $count bài." : 'Không còn bài nào để xóa.',
        'done' => $remaining === 0
    ]);
});

// AJAX: Lưu cấu hình
add_action('wp_ajax_adop_save_settings', function () {
    if (!current_user_can('manage_options') || !check_ajax_referer('adop_save_settings', 'nonce', false)) {
        wp_send_json_error(['message' => 'Không có quyền hoặc lỗi bảo mật!']);
    }
    $limit = isset($_POST['batch_limit']) ? intval($_POST['batch_limit']) : ADOP_DEFAULT_BATCH;
    $post_type = sanitize_text_field($_POST['post_type'] ?? ADOP_DEFAULT_POST_TYPE);
    $delete_type = isset($_POST['delete_type']) && $_POST['delete_type'] === 'delete' ? 'delete' : 'trash';
    $enable_cron = isset($_POST['enable_cron']) ? 1 : 0;
    $months_ago = isset($_POST['months_ago']) ? max(1, intval($_POST['months_ago'])) : ADOP_DEFAULT_MONTHS;
    $cron_hour = isset($_POST['cron_hour']) ? max(0, min(23, intval($_POST['cron_hour']))) : ADOP_DEFAULT_CRON_HOUR;
    $manual_batch = isset($_POST['manual_batch_limit']) ? max(1, intval($_POST['manual_batch_limit'])) : ADOP_DEFAULT_MANUAL_BATCH;
    update_option(ADOP_BATCH_OPTION, max(1, $limit));
    update_option(ADOP_POST_TYPE_OPTION, $post_type);
    update_option(ADOP_DELETE_TYPE_OPTION, $delete_type);
    update_option(ADOP_ENABLE_CRON_OPTION, $enable_cron);
    update_option(ADOP_MONTHS_OPTION, $months_ago);
    update_option(ADOP_CRON_HOUR_OPTION, $cron_hour);
    update_option(ADOP_MANUAL_BATCH_OPTION, $manual_batch);
    adop_unschedule_cron();
    adop_schedule_cron();
    wp_send_json_success(['message' => 'Đã lưu cấu hình thành công!']);
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_adop-delete-posts') {
        wp_enqueue_script('adop-admin-js', plugin_dir_url(__FILE__).'adop-admin.js', ['jquery'], '1.4', true);
        wp_localize_script('adop-admin-js', 'adopAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_delete' => wp_create_nonce('adop_manual_delete'),
            'nonce_settings' => wp_create_nonce('adop_save_settings'),
        ]);
    }
});
