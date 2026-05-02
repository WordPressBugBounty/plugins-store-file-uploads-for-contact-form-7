<?php
/*
Plugin Name: Store file uploads for Contact Form 7
Plugin URI: https://namir.ro/store-file-uploads-for-contact-form-7/
Description: Store all files uploded trough Contact Form 7 in your Media Library
Author: Mircea N.
Text Domain: nmr-store-cf7-uploads
Domain Path: /languages/
Version: 1.3.0
*/

add_action('plugins_loaded', function () {
    if (!class_exists('WPCF7')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Store file uploads for Contact Form 7</strong> requires <a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">Contact Form 7</a> to be installed and active.';
            echo '</p></div>';
        });
        return;
    }
    add_action('wpcf7_before_send_mail', 'nmr_on_before_cf7_send_mail');
});

function nmr_create_attachment($filename)
{
    $filetype = wp_check_filetype(basename($filename), null);
    $wp_upload_dir = wp_upload_dir();

    $attachFileName = $wp_upload_dir['path'] . '/' . basename($filename);
    $attachFileName = apply_filters('nmr_create_attachment_file_name', $attachFileName);
    copy($filename, $attachFileName);

    $skip_save_to_media_library = false;
    $skip_save_to_media_library = apply_filters('nmr_should_skip_save_attachment_to_media_library', $skip_save_to_media_library);
    if (!$skip_save_to_media_library) {
        $attachment = array(
            'guid'           => $attachFileName,
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attachment = apply_filters('nmr_before_insert_attachment', $attachment);
        $attach_id = wp_insert_attachment($attachment, $attachFileName);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $attachFileName);
        wp_update_attachment_metadata($attach_id, $attach_data);
    }

    do_action('nmr_create_attachment_id_generated', $attach_id);
    return $attach_data;
}

function nmr_on_before_cf7_send_mail(\WPCF7_ContactForm $contactForm)
{
    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        $uploaded_files = $submission->uploaded_files();
        if ($uploaded_files) {
            foreach ($uploaded_files as $fieldName => $filepath) {
                if (is_array($filepath)) {
                    foreach ($filepath as $key => $value) {
                        $data = nmr_create_attachment($value);
                    }
                } else {
                    $data = nmr_create_attachment($filepath);
                }
            }
        }
    }
}
// ── Pro upsell (only when pro plugin is not active) ───────────────────────────

function nmr_sfucf7_pro_is_active(): bool
{
    return class_exists('Nmr_Sfucf7_Pro');
}

// Settings page — free plugin owns it, shows pro UI preview when pro not active
add_action('admin_menu', function () {
    if (nmr_sfucf7_pro_is_active()) return;
    add_options_page(
        'Store CF7 Uploads',
        'Store CF7 Uploads',
        'manage_options',
        'nmr-sfucf7',
        'nmr_sfucf7_render_upsell_page'
    );
});

// Plugin action links: Settings + Upgrade
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    if (!nmr_sfucf7_pro_is_active()) {
        $links['upgrade'] = '<a href="https://namir.ro/downloads/store-file-uploads-for-contact-form-7-pro/" target="_blank" style="color:#d63638;font-weight:600">Upgrade to Pro</a>';
    }
    $links['settings'] = '<a href="' . esc_url(admin_url('options-general.php?page=nmr-sfucf7')) . '">Settings</a>';
    return $links;
});

// Dismissible admin notice
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    if (nmr_sfucf7_pro_is_active()) return;
    if (get_user_meta(get_current_user_id(), 'nmr_sfucf7_pro_notice_dismissed', true)) return;

    $dismiss_url = add_query_arg([
        'nmr_sfucf7_dismiss_notice' => '1',
        '_wpnonce'                  => wp_create_nonce('nmr_sfucf7_dismiss'),
    ]);
    $upgrade_url = 'https://namir.ro/downloads/store-file-uploads-for-contact-form-7-pro/';

    echo '<div class="notice notice-info" style="display:flex;align-items:center;gap:16px;padding:12px 16px">';
    echo '<div style="flex:1">';
    echo '<strong>Store CF7 Uploads Pro</strong> — upload log, auto-rename, private files, file URL in email, admin notifications, Flamingo integration, and more. ';
    printf('<a href="%s" target="_blank" class="button button-primary">Upgrade to Pro &rarr;</a>', esc_url($upgrade_url));
    echo '</div>';
    printf('<a href="%s" style="color:#aaa;text-decoration:none;font-size:20px;line-height:1" title="Dismiss">&times;</a>', esc_url($dismiss_url));
    echo '</div>';
});

add_action('admin_init', function () {
    if (!isset($_GET['nmr_sfucf7_dismiss_notice'])) return;
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'nmr_sfucf7_dismiss')) return;
    update_user_meta(get_current_user_id(), 'nmr_sfucf7_pro_notice_dismissed', '1');
    wp_safe_redirect(remove_query_arg(['nmr_sfucf7_dismiss_notice', '_wpnonce']));
    exit;
});

// ── Upsell page renderer ──────────────────────────────────────────────────────

function nmr_sfucf7_render_upsell_page(): void
{
    $tab      = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
    $tabs     = ['settings' => 'Settings', 'log' => 'Upload Log', 'advanced' => 'Advanced'];
    $base_url = admin_url('options-general.php?page=nmr-sfucf7');
    $pro_url  = 'https://namir.ro/downloads/store-file-uploads-for-contact-form-7-pro/';

    echo '<div class="wrap">';
    echo '<h1>Store CF7 Uploads</h1>';

    // Upgrade CTA banner
    echo '<div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:12px 16px;margin:16px 0;display:flex;align-items:center;gap:16px;max-width:900px">';
    echo '<div style="flex:1"><strong>&#128274; Pro features below are locked.</strong> All settings shown are active in the Pro version &mdash; upgrade to unlock them.</div>';
    printf('<a href="%s" target="_blank" class="button button-primary" style="white-space:nowrap">Get Pro &rarr;</a>', esc_url($pro_url));
    echo '</div>';

    echo '<nav class="nav-tab-wrapper">';
    foreach ($tabs as $slug => $label) {
        $active = $tab === $slug ? ' nav-tab-active' : '';
        printf('<a href="%s" class="nav-tab%s">%s</a>', esc_url($base_url . '&tab=' . $slug), $active, esc_html($label));
    }
    echo '</nav>';

    switch ($tab) {
        case 'log':
            nmr_sfucf7_upsell_log_tab($pro_url);
            break;
        case 'advanced':
            nmr_sfucf7_upsell_advanced_tab($pro_url);
            break;
        default:
            nmr_sfucf7_upsell_settings_tab($pro_url);
    }

    echo '</div>';
}

function nmr_sfucf7_upsell_settings_tab(string $pro_url): void
{
    $forms = class_exists('WPCF7_ContactForm') ? WPCF7_ContactForm::find(['posts_per_page' => -1]) : [];

    // Fallback demo rows if no forms yet
    $demo_forms = empty($forms) ? [
        ['title' => 'Contact Form 1',  'id' => 1],
        ['title' => 'Quote Request',    'id' => 2],
        ['title' => 'Job Application',  'id' => 3],
    ] : [];

    echo '<div style="margin-top:0">';
    echo '<h2>Per-form settings</h2>';
    echo '<p style="color:#666">Choose which forms save uploads and set a custom folder and filename pattern per form.</p>';
    echo '<table class="wp-list-table widefat striped" style="max-width:900px;table-layout:fixed;width:100%">';
    echo '<colgroup><col style="width:60px"><col style="width:22%"><col style="width:39%"><col style="width:39%"></colgroup>';
    echo '<thead><tr>';
    echo '<th>Save</th>';
    echo '<th>Form</th>';
    echo '<th>Custom folder <code style="font-weight:normal">(relative to /uploads/)</code></th>';
    echo '<th>Rename pattern <code style="font-weight:normal">{name}-{date}.{ext}</code></th>';
    echo '</tr></thead><tbody>';

    foreach ($forms as $form) {
        $title = $form->title();
        $fid   = $form->id();
        echo '<tr>';
        echo '<td><input type="checkbox" disabled checked></td>';
        echo '<td>' . esc_html($title) . ' <span style="color:#aaa;font-size:12px">ID: ' . esc_html($fid) . '</span></td>';
        echo '<td><input type="text" disabled value="cf7-uploads/' . esc_attr(sanitize_title($title)) . '/" style="width:100%;box-sizing:border-box"></td>';
        echo '<td><input type="text" disabled value="{name}-{date}.{ext}" style="width:100%;box-sizing:border-box"></td>';
        echo '</tr>';
    }

    foreach ($demo_forms as $form) {
        echo '<tr>';
        echo '<td><input type="checkbox" disabled checked></td>';
        echo '<td>' . esc_html($form['title']) . ' <span style="color:#aaa;font-size:12px">ID: ' . esc_html($form['id']) . '</span></td>';
        echo '<td><input type="text" disabled value="cf7-uploads/' . esc_attr(sanitize_title($form['title'])) . '/" style="width:100%;box-sizing:border-box"></td>';
        echo '<td><input type="text" disabled value="{name}-{date}.{ext}" style="width:100%;box-sizing:border-box"></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<h2 style="margin-top:24px">Global settings</h2>';
    echo '<table class="form-table" style="max-width:900px">';

    $rows = [
        [
            'Rename pattern',
            '<input type="text" disabled value="{name}-{date}.{ext}" class="regular-text">
             <p class="description">Tokens: <code>{ext}</code> <code>{date}</code> <code>{timestamp}</code> <code>{filename}</code> and any CF7 field name e.g. <code>{your-name}</code>. Per-form pattern overrides this.</p>',
        ],
        [
            'Unique filenames',
            '<label><input type="checkbox" disabled checked> Append a number suffix when a file with the same name already exists</label>',
        ],
        [
            'Admin notification',
            '<label><input type="checkbox" disabled checked> Send an email when a file is uploaded</label><br><br>
             <input type="email" disabled value="' . esc_attr(get_option('admin_email')) . '" class="regular-text">
             <p class="description">Leave blank to use the site admin email.</p>',
        ],
        [
            'File URL in email',
            '<label><input type="checkbox" disabled checked> Replace the <code>[file]</code> CF7 mail tag with a clickable link to the stored file</label>
             <p class="description">Works in both Mail 1 and Mail 2 templates.</p>',
        ],
    ];

    foreach ($rows as [$label, $control]) {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $control . '</td></tr>';
    }

    echo '</table>';

    echo '<p style="margin-top:20px"><a href="' . esc_url($pro_url) . '" target="_blank" class="button button-primary button-large">Unlock these settings &mdash; Get Pro</a></p>';
    echo '</div>';
}

function nmr_sfucf7_upsell_log_tab(string $pro_url): void
{
    // Use real attachments from the media library as preview data
    $attachments = get_posts([
        'post_type'      => 'attachment',
        'posts_per_page' => 5,
        'post_status'    => 'inherit',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    echo '<h2 style="margin-top:16px">Upload Log</h2>';
    echo '<p style="color:#666">Every file saved through CF7 is listed here with the form name, field, date, and a direct download link.</p>';

    echo '<table class="wp-list-table widefat fixed striped" style="max-width:900px">';
    echo '<thead><tr><th>File</th><th>Form</th><th>Field</th><th>Date</th><th>Size</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    if (!empty($attachments)) {
        // Real media library files — show them but "Form" / "Field" columns show what pro would track
        foreach ($attachments as $att) {
            $file = get_attached_file($att->ID);
            $size = $file && file_exists($file) ? size_format(filesize($file)) : '—';
            $url  = wp_get_attachment_url($att->ID);
            $name = basename($file ?: $att->post_title);
            echo '<tr>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td style="color:#aaa"><em>tracked in Pro</em></td>';
            echo '<td style="color:#aaa"><em>tracked in Pro</em></td>';
            echo '<td>' . esc_html(date_i18n('Y-m-d H:i', strtotime($att->post_date))) . '</td>';
            echo '<td>' . esc_html($size) . '</td>';
            echo '<td>' . ($url ? '<a href="' . esc_url($url) . '" target="_blank">View</a>' : '—') . '</td>';
            echo '</tr>';
        }
    } else {
        // Pure demo rows
        $demo = [
            ['photo_resume.jpg',    'Job Application', 'upload-cv',   '2024-11-01 09:12', '1.2 MB'],
            ['contract_signed.pdf', 'Contract Upload',  'file-upload', '2024-10-28 14:37', '342 KB'],
            ['logo_draft.png',      'Contact Form 1',   'attachment',  '2024-10-15 11:05', '88 KB'],
        ];
        foreach ($demo as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row[0]) . '</td>';
            echo '<td>' . esc_html($row[1]) . '</td>';
            echo '<td>' . esc_html($row[2]) . '</td>';
            echo '<td>' . esc_html($row[3]) . '</td>';
            echo '<td>' . esc_html($row[4]) . '</td>';
            echo '<td><a href="#">View</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    echo '<p style="margin-top:20px;color:#666">In the Pro version, Form and Field columns are populated automatically for every CF7 upload.</p>';
    echo '<p><a href="' . esc_url($pro_url) . '" target="_blank" class="button button-primary button-large">Get the full upload log &rarr;</a></p>';
}

function nmr_sfucf7_upsell_advanced_tab(string $pro_url): void
{
    echo '<h2 style="margin-top:16px">Advanced</h2>';
    echo '<p style="color:#666">Fine-grained control over file handling, storage, and cleanup.</p>';
    echo '<table class="form-table" style="max-width:900px">';

    $rows = [
        [
            'Private files',
            '<label><input type="checkbox" disabled> Store files outside the Media Library with <code>.htaccess</code> protection</label>
             <p class="description">Files are saved to <code>wp-content/nmr-private-uploads/</code>. Direct URL access is blocked. Admins download via a secure link.</p>',
        ],
        [
            'Detect duplicates',
            '<label><input type="checkbox" disabled checked> Skip saving if an identical file (by MD5 hash) was already uploaded</label>
             <p class="description">Prevents the same file from being stored multiple times across repeated form submissions.</p>',
        ],
        [
            'Flamingo integration',
            '<label><input type="checkbox" disabled checked> Link uploaded files to the corresponding Flamingo inbound message</label>
             <p class="description">Requires the <a href="https://wordpress.org/plugins/flamingo/" target="_blank">Flamingo</a> plugin. Attachment thumbnails appear inside the Flamingo entry.</p>',
        ],
        [
            'Auto-delete after',
            '<input type="number" disabled value="30" style="width:70px"> days &nbsp;<span style="color:#aaa">(0 = disabled)</span>
             <p class="description">Runs daily via WP-Cron. Useful for GDPR compliance.</p>',
        ],
        [
            'Image max width',
            '<input type="number" disabled value="1920" style="width:80px"> px &nbsp;<span style="color:#aaa">(0 = disabled)</span>
             <p class="description">Resize images wider than this on upload. Saves disk space on large phone photos.</p>',
        ],
        [
            'Image quality',
            '<input type="number" disabled value="82" min="1" max="100" style="width:70px"> %
             <p class="description">JPEG compression quality applied when resizing.</p>',
        ],
        [
            'Skip-mail support',
            '<label><input type="checkbox" disabled checked> Save files even when CF7 <code>skip_mail: on</code> is set in Additional Settings</label>',
        ],
    ];

    foreach ($rows as [$label, $control]) {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $control . '</td></tr>';
    }

    echo '</table>';
    echo '<p style="margin-top:20px"><a href="' . esc_url($pro_url) . '" target="_blank" class="button button-primary button-large">Unlock all of this &rarr;</a></p>';
}
