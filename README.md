# Auto Delete Old Posts

WordPress plugin that automatically deletes (or moves to trash) old posts in batches, with both **scheduled** (cron) and **manual** deletion, plus a small dashboard to configure and monitor activity.

## Features

- **Batch delete old posts**: delete posts older than _N_ months.
- **Supports any public post type**: `post`, `page`, custom post types, etc.
- **Safe mode (trash)** or **permanent delete**.
- **Daily cron schedule** at a configurable hour.
- **Manual deletion with batching** to avoid timeouts.
- **Check count**: button to see how many posts match deletion criteria (no delete).
- **Simple history log** showing recent deletion runs.

## Installation

1. Copy `auto-delete-old-posts.php` and `adop-admin.js` into a folder named `auto-delete-old-posts` inside your WordPress `wp-content/plugins` directory (e.g. `wp-content/plugins/auto-delete-old-posts/`).
2. In WordPress Admin, go to **Plugins → Installed Plugins**.
3. Activate **Auto Delete Old Posts**.

## Usage

After activation you will see a new menu: **Delete Old Posts** (top-level in the admin sidebar). Click it to open the plugin settings page.

### Automatic deletion

In **Cau hinh tu dong** you can set:

- **Tong so bai can xoa moi lan chay tu dong** (`batch_limit`): total posts to delete per daily cron run.
- **So bai xoa moi luot (batch nho)** (`manual_batch_limit`): batch size for manual deletion.
- **Chi xoa bai cu hon (thang)** (`months_ago`): only posts older than this many months.
- **Loai bai viet** (`post_type`): any public post type (e.g. `post`, `page`, or custom).
- **Hanh dong xoa** (`delete_type`): `trash` (recommended) or `delete` (permanent).
- **Tu dong xoa theo lich** (`enable_cron`): enable/disable daily cron.
- **Gio thuc hien tu dong** (`cron_hour`): 0-23 (e.g. 0 = midnight).

### Manual deletion

In **Xoa thu cong**:

- Set total posts to delete and batch size, then click **Chay xoa bai cu thu cong**.
- Click **Dung lai** to stop between batches.
- Progress and deleted titles appear below the buttons.

### Check count

Use **Kiem tra so bai dat dieu kien xoa** to see how many posts currently match the criteria (no posts are deleted).

### History log

The **Lich su xoa gan day** table shows the last 20 deletion runs (time, count, post type, months).

## Developer notes

- Admin page: `adop_render_admin_page()`.
- Deletion logic: `adop_delete_old_posts()`.
- Cron hook: `adop_cron_event`.
- Options: `adop_batch_limit`, `adop_manual_batch_limit`, `adop_post_type`, `adop_enable_cron`, `adop_months_ago`, `adop_delete_type`, `adop_cron_hour`, `adop_delete_log`.
- AJAX: `adop_save_settings`, `adop_manual_delete_batch`, `adop_check_count`.
