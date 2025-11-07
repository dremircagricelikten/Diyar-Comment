<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) { require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php'); }

class Diyar_Comments_List_Table extends WP_List_Table {
    function __construct() { 
        parent::__construct(['singular' => 'Yorum', 'plural' => 'Yorumlar', 'ajax' => false]); 
    }

    function get_columns() {
        return [
            'cb' => '<input type="checkbox" />', 
            'author' => 'Yazar', 
            'comment' => 'Yorum', 
            'response' => 'Yanıtlanan Yazı', 
            'likes' => 'Beğeniler', 
            'reports' => 'Şikayetler', 
            'date' => 'Tarih'
        ];
    }

    function get_bulk_actions() {
        $current_status = isset($_GET['comment_status']) ? $_GET['comment_status'] : 'all';
        
        $actions = [];
        
        if ($current_status !== 'trash') {
            $actions['approve'] = __('Onayla', 'diyar-comment');
            $actions['unapprove'] = __('Onayı Kaldır', 'diyar-comment');
            $actions['spam'] = __('Spam İşaretle', 'diyar-comment');
            $actions['trash'] = __('Çöpe At', 'diyar-comment');
        }
        
        if ($current_status === 'spam') {
            $actions['unspam'] = __('Spam İşaretini Kaldır', 'diyar-comment');
            $actions['delete'] = __('Kalıcı Sil', 'diyar-comment');
        }
        
        if ($current_status === 'trash') {
            $actions['restore'] = __('Geri Yükle', 'diyar-comment');
            $actions['delete'] = __('Kalıcı Sil', 'diyar-comment');
        }
        
        return $actions;
    }

    function process_bulk_action() {
        if (empty($_GET['comment']) || !is_array($_GET['comment'])) return;
        
        $comment_ids = array_map('intval', $_GET['comment']);
        $action = $this->current_action();
        
        if (!$action || !wp_verify_nonce($_GET['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
            return;
        }

        $message = '';
        $redirect_status = isset($_GET['comment_status']) ? $_GET['comment_status'] : 'all';
        
        switch ($action) {
            case 'approve':
                foreach ($comment_ids as $comment_id) {
                    wp_set_comment_status($comment_id, 'approve');
                }
                $message = sprintf(__('%d yorum onaylandı.', 'diyar-comment'), count($comment_ids));
                break;
                
            case 'unapprove':
                foreach ($comment_ids as $comment_id) {
                    wp_set_comment_status($comment_id, 'hold');
                }
                $message = sprintf(__('%d yorumun onayı kaldırıldı.', 'diyar-comment'), count($comment_ids));
                break;
                
            case 'spam':
                foreach ($comment_ids as $comment_id) {
                    wp_spam_comment($comment_id);
                }
                $message = sprintf(__('%d yorum spam olarak işaretlendi.', 'diyar-comment'), count($comment_ids));
                break;
                
            case 'unspam':
                foreach ($comment_ids as $comment_id) {
                    wp_unspam_comment($comment_id);
                }
                $message = sprintf(__('%d yorumun spam işareti kaldırıldı.', 'diyar-comment'), count($comment_ids));
                $redirect_status = 'all'; // Spam'dan çıkan yorumlar all'a gitsin
                break;
                
            case 'trash':
                foreach ($comment_ids as $comment_id) {
                    wp_trash_comment($comment_id);
                }
                $message = sprintf(__('%d yorum çöpe atıldı.', 'diyar-comment'), count($comment_ids));
                break;
                
            case 'restore':
                foreach ($comment_ids as $comment_id) {
                    wp_untrash_comment($comment_id);
                }
                $message = sprintf(__('%d yorum geri yüklendi.', 'diyar-comment'), count($comment_ids));
                $redirect_status = 'all'; // Restore edilen yorumlar all'a gitsin
                break;
                
            case 'delete':
                $deleted_count = 0;
                foreach ($comment_ids as $comment_id) {
                    // Kalıcı silme işlemi
                    $result = wp_delete_comment($comment_id, true);
                    if ($result) {
                        $deleted_count++;
                        
                        // Manga Diyarı Yorum meta verilerini de temizle
                        global $wpdb;
                        $wpdb->delete($wpdb->prefix . 'diyar_reports', ['comment_id' => $comment_id]);
                    }
                }
                
                if ($deleted_count > 0) {
                    $message = sprintf(__('%d yorum kalıcı olarak silindi.', 'diyar-comment'), $deleted_count);
                    // Kalıcı silme sonrası aynı sayfada kal
                } else {
                    $message = __('Yorumlar silinemedi.', 'diyar-comment');
                }
                break;
        }
        
        if (!empty($message)) {
            // Session kullanarak mesajı sakla
            set_transient('diyar_admin_message_' . get_current_user_id(), $message, 30);
            
            // Yönlendirme URL'ini oluştur
            $redirect_url = admin_url('admin.php?page=diyar-comment-manager');
            if ($redirect_status !== 'all') {
                $redirect_url = add_query_arg('comment_status', $redirect_status, $redirect_url);
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }

    function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->process_bulk_action();
        
        $paged = $this->get_pagenum();
        $per_page = 20;

        $args = ['number' => $per_page, 'offset' => ($paged - 1) * $per_page, 'orderby' => 'comment_date', 'order' => 'DESC'];
        
        if (isset($_GET['comment_status'])) { 
            $status = sanitize_key($_GET['comment_status']);
            if ($status !== 'all') {
                if ($status === 'moderated') {
                    $args['status'] = 'hold';
                } else {
                    $args['status'] = $status;
                }
            }
        }
        
        $this->items = get_comments($args);
        $total_items = get_comments(array_merge($args, ['count' => true, 'offset' => 0, 'number' => 0]));
        $this->set_pagination_args(['total_items' => $total_items, 'per_page' => $per_page]);
    }

    function column_cb($item) { 
        return sprintf('<input type="checkbox" name="comment[]" value="%s" />', $item->comment_ID); 
    }
    
    function column_author($item) { 
        $user_info = '';
        if ($item->user_id) {
            $user = get_userdata($item->user_id);
            $user_info = $user ? ' (ID: ' . $item->user_id . ')' : '';
        }
        return '<strong>' . esc_html($item->comment_author) . '</strong>' . $user_info . '<br>' . 
               '<span style="color:#666;">' . esc_html($item->comment_author_email) . '</span>';
    }
    
    function column_response($item) {
        $source_url = diyar_get_comment_source_url($item);
        $comment_link = diyar_get_comment_permalink($item);
        $label = diyar_get_comment_location_label($item);

        if (empty($source_url) && $item->comment_post_ID) {
            $source_url = get_permalink($item->comment_post_ID);
        }

        if (empty($source_url)) {
            $source_url = $comment_link;
        }

        if ($source_url) {
            return '<a href="' . esc_url($source_url) . '" target="_blank">' . esc_html($label) . '</a>';
        }

        return esc_html($label);
    }
    
    function column_likes($item) { 
        $likes = get_comment_meta($item->comment_ID, '_likes', true) ?: 0; 
        $dislikes = get_comment_meta($item->comment_ID, '_dislikes', true) ?: 0; 
        return "<div class='comment-stats'><span class='likes'>👍 {$likes}</span> / <span class='dislikes'>👎 {$dislikes}</span></div>";
    }
    
    function column_reports($item) { 
        global $wpdb; 
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}diyar_reports WHERE comment_id = %d", $item->comment_ID)); 
        $class = $count > 0 ? "color:red; font-weight:bold;" : "";
        return "<span style='{$class}'>{$count}</span>";
    }
    
    function column_date($item) { 
        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item->comment_date); 
    }
    
    function column_comment($item) {
        $actions = [];
        $current_status = $item->comment_approved;
        $current_page_status = isset($_GET['comment_status']) ? $_GET['comment_status'] : 'all';
        
        // Nonce oluştur
        $approve_nonce = wp_create_nonce("approve-comment_{$item->comment_ID}");
        $delete_nonce = wp_create_nonce("delete-comment_{$item->comment_ID}");
        $trash_nonce = wp_create_nonce("trash-comment_{$item->comment_ID}");
        $spam_nonce = wp_create_nonce("spam-comment_{$item->comment_ID}");
        
        // Durum bazında eylemler
        if ($current_status === 'trash') {
            // Çöp durumu - sadece geri yükle ve kalıcı sil
            $actions['restore'] = "<a href='?page=diyar-comment-manager&action=restore&c={$item->comment_ID}&_wpnonce=$approve_nonce&comment_status=$current_page_status' class='button button-small' style='color:#00a32a;'>Geri Yükle</a>";
            $actions['delete'] = "<a href='?page=diyar-comment-manager&action=delete&c={$item->comment_ID}&_wpnonce=$delete_nonce&comment_status=$current_page_status' onclick='return confirm(\"Bu yorumu kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!\")' class='button button-small' style='color:#d63638;'>Kalıcı Sil</a>";
        } elseif ($current_status === 'spam') {
            // Spam durumu
            $actions['unspam'] = "<a href='?page=diyar-comment-manager&action=unspam&c={$item->comment_ID}&_wpnonce=$spam_nonce&comment_status=$current_page_status' class='button button-small'>Spam Değil</a>";
            $actions['delete'] = "<a href='?page=diyar-comment-manager&action=delete&c={$item->comment_ID}&_wpnonce=$delete_nonce&comment_status=$current_page_status' onclick='return confirm(\"Bu yorumu kalıcı olarak silmek istediğinizden emin misiniz?\")' class='button button-small' style='color:#d63638;'>Kalıcı Sil</a>";
        } else {
            // Normal durumlar için eylemler
            if ($current_status == '0') { 
                $actions['approve'] = "<a href='?page=diyar-comment-manager&action=approve&c={$item->comment_ID}&_wpnonce=$approve_nonce&comment_status=$current_page_status' class='button button-small' style='color:#00a32a;'>Onayla</a>"; 
            } else { 
                $actions['unapprove'] = "<a href='?page=diyar-comment-manager&action=unapprove&c={$item->comment_ID}&_wpnonce=$approve_nonce&comment_status=$current_page_status' class='button button-small' style='color:#dba617;'>Onayı Kaldır</a>"; 
            }
            
            $view_link = diyar_get_comment_permalink($item);
            $actions['view'] = "<a href='" . esc_url($view_link ?: '#') . "' target='_blank' class='button button-small'>Görüntüle</a>";
            $actions['edit'] = "<a href='" . admin_url('comment.php?action=editcomment&c=' . $item->comment_ID) . "' class='button button-small'>Düzenle</a>";
            $actions['quick-edit'] = "<a href='#' class='quick-edit-comment button button-small' data-comment-id='{$item->comment_ID}'>Hızlı Düzenle</a>";
            
            $actions['spam'] = "<a href='?page=diyar-comment-manager&action=spam&c={$item->comment_ID}&_wpnonce=$spam_nonce&comment_status=$current_page_status' class='button button-small' style='color:#d63638;'>Spam</a>";
            $actions['trash'] = "<a href='?page=diyar-comment-manager&action=trash&c={$item->comment_ID}&_wpnonce=$trash_nonce&comment_status=$current_page_status' onclick='return confirm(\"Bu yorumu çöpe atmak istediğinizden emin misiniz?\")' class='button button-small' style='color:#d63638;'>Çöpe At</a>";
        }

        // Kullanıcı yönetimi eylemleri (çöp ve spam durumunda gösterme)
        if ($current_status !== 'trash' && $current_status !== 'spam') {
            $ban_nonce = wp_create_nonce("ban-user_{$item->user_id}");
            if ($item->user_id && $item->user_id != get_current_user_id()) {
                $user = get_userdata($item->user_id);
                if ($user) {
                    $ban_status = get_user_meta($item->user_id, 'diyar_ban_status', true);
                    $timeout_until = get_user_meta($item->user_id, 'diyar_timeout_until', true);
                    
                    if ($ban_status !== 'banned') {
                        $actions['ban'] = "<a href='?page=diyar-comment-manager&action=ban&user_id={$item->user_id}&_wpnonce=$ban_nonce&comment_status=$current_page_status' onclick='return confirm(\"Bu kullanıcıyı kalıcı olarak engellemek istediğinizden emin misiniz?\")' class='button button-small' style='background:#d63638;color:white;'>Engelle</a>";
                    }
                    
                    if (!$timeout_until || $timeout_until < time()) {
                        $actions['timeout'] = "<a href='?page=diyar-comment-manager&action=timeout&user_id={$item->user_id}&_wpnonce=$ban_nonce&comment_status=$current_page_status' onclick='return confirm(\"Bu kullanıcıya 24 saat zaman aşımı uygulamak istediğinizden emin misiniz?\")' class='button button-small' style='background:#e67e22;color:white;'>24 Saat Sustur</a>";
                    }
                }
            }
        }
        
        // Yorum metni ve durum göstergesi
        $status_indicator = '';
        switch ($current_status) {
            case '1':
                $status_indicator = '<span class="status-indicator approved" title="Onaylı"></span>';
                break;
            case '0':
                $status_indicator = '<span class="status-indicator pending" title="Onay Bekliyor"></span>';
                break;
            case 'spam':
                $status_indicator = '<span class="status-indicator spam" title="Spam"></span>';
                break;
            case 'trash':
                $status_indicator = '<span class="status-indicator trash" title="Çöp"></span>';
                break;
        }
        
        $comment_text = '<div class="comment-content-wrapper">';
        $comment_text .= $status_indicator;
        $comment_text .= '<div id="comment-text-' . $item->comment_ID . '">' . wp_trim_words(esc_html($item->comment_content), 50) . '</div>';
        $comment_text .= '<div id="edit-comment-' . $item->comment_ID . '" style="display:none;">';
        $comment_text .= '<textarea style="width:100%; min-height:80px;" rows="4">' . esc_textarea($item->comment_content) . '</textarea>';
        $comment_text .= '<div style="margin-top:8px;">';
        $comment_text .= '<button class="button button-primary save-edit-comment" data-comment-id="' . $item->comment_ID . '">Kaydet</button> ';
        $comment_text .= '<button class="button cancel-edit-comment">İptal</button>';
        $comment_text .= '</div></div></div>';

        // Eylem butonları
        $comment_text .= '<div class="comment-actions-wrapper" style="margin-top:12px;">';
        foreach ($actions as $action_key => $action_link) {
            $comment_text .= $action_link . ' ';
        }
        $comment_text .= '</div>';

        return $comment_text;
    }
    
    function get_sortable_columns() {
        return array(
            'author' => array('comment_author', true),
            'date' => array('comment_date', true),
            'likes' => array('likes', false),
            'reports' => array('reports', false)
        );
    }
    
    function get_views() {
        $status_links = [];
        $num_comments = wp_count_comments();
        
        $stati = [
            'all' => ['label' => 'Tümü', 'count' => $num_comments->total_comments],
            'moderated' => ['label' => 'Onay Bekleyen', 'count' => $num_comments->moderated],
            'approved' => ['label' => 'Onaylı', 'count' => $num_comments->approved],
            'spam' => ['label' => 'Spam', 'count' => $num_comments->spam],
            'trash' => ['label' => 'Çöp', 'count' => $num_comments->trash]
        ];

        $current_status = isset($_GET['comment_status']) ? $_GET['comment_status'] : 'all';

        foreach ($stati as $status => $data) {
            $class = ($current_status === $status) ? ' class="current"' : '';
            $link = add_query_arg('comment_status', $status, admin_url('admin.php?page=diyar-comment-manager'));
            $status_links[$status] = "<a href='$link'$class>" . $data['label'] . " <span class='count'>(" . number_format_i18n($data['count']) . ")</span></a>";
        }
        
        return $status_links;
    }
}

function render_comment_manager_page_content() {
    // Admin mesajlarını göster
    $user_id = get_current_user_id();
    $admin_message = get_transient('diyar_admin_message_' . $user_id);
    if ($admin_message) {
        delete_transient('diyar_admin_message_' . $user_id);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($admin_message) . '</p></div>';
    }
    
    // Tekli aksiyonları işle (URL'den gelen)
    if (isset($_GET['action']) && isset($_GET['c'])) {
        $action = sanitize_key($_GET['action']);
        $comment_id = intval($_GET['c']);
        $current_status = isset($_GET['comment_status']) ? sanitize_key($_GET['comment_status']) : 'all';
        
        switch ($action) {
            case 'approve':
            case 'unapprove':
                check_admin_referer("approve-comment_$comment_id");
                wp_set_comment_status($comment_id, $action === 'approve' ? 'approve' : 'hold');
                $message = $action === 'approve' ? 'Yorum onaylandı.' : 'Yorumun onayı kaldırıldı.';
                break;
                
            case 'trash':
                check_admin_referer("trash-comment_$comment_id");
                wp_trash_comment($comment_id);
                $message = 'Yorum çöpe atıldı.';
                break;
                
            case 'restore':
                check_admin_referer("approve-comment_$comment_id");
                wp_untrash_comment($comment_id);
                $message = 'Yorum geri yüklendi.';
                $current_status = 'all'; // Restore sonrası all'a git
                break;
                
            case 'spam':
                check_admin_referer("spam-comment_$comment_id");
                wp_spam_comment($comment_id);
                $message = 'Yorum spam olarak işaretlendi.';
                break;
                
            case 'unspam':
                check_admin_referer("spam-comment_$comment_id");
                wp_unspam_comment($comment_id);
                $message = 'Yorumun spam işareti kaldırıldı.';
                $current_status = 'all'; // Unspam sonrası all'a git
                break;
                
            case 'delete':
                check_admin_referer("delete-comment_$comment_id");
                $result = wp_delete_comment($comment_id, true);
                if ($result) {
                    // Manga Diyarı Yorum meta verilerini de temizle
                    global $wpdb;
                    $wpdb->delete($wpdb->prefix . 'diyar_reports', ['comment_id' => $comment_id]);
                    $message = 'Yorum kalıcı olarak silindi.';
                } else {
                    $message = 'Yorum silinemedi.';
                }
                break;
        }
        
        if (isset($message)) {
            set_transient('diyar_admin_message_' . get_current_user_id(), $message, 30);
            
            // Yönlendirme URL'ini oluştur
            $redirect_url = admin_url('admin.php?page=diyar-comment-manager');
            if ($current_status !== 'all') {
                $redirect_url = add_query_arg('comment_status', $current_status, $redirect_url);
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    // Kullanıcı durumu aksiyonları
    if (isset($_GET['action']) && isset($_GET['user_id']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_key($_GET['action']);
        $user_id = intval($_GET['user_id']);
        $current_status = isset($_GET['comment_status']) ? sanitize_key($_GET['comment_status']) : 'all';
        
        if (wp_verify_nonce($_GET['_wpnonce'], "ban-user_$user_id")) {
            switch ($action) {
                case 'ban':
                    update_user_meta($user_id, 'diyar_ban_status', 'banned');
                    $message = 'Kullanıcı kalıcı olarak engellendi.';
                    break;
                    
                case 'timeout':
                    update_user_meta($user_id, 'diyar_timeout_until', time() + 86400); // 24 saat
                    $message = 'Kullanıcı 24 saat süreyle susturuldu.';
                    break;
            }
            
            if (isset($message)) {
                set_transient('diyar_admin_message_' . get_current_user_id(), $message, 30);
                
                // Yönlendirme URL'ini oluştur
                $redirect_url = admin_url('admin.php?page=diyar-comment-manager');
                if ($current_status !== 'all') {
                    $redirect_url = add_query_arg('comment_status', $current_status, $redirect_url);
                }
                
                wp_redirect($redirect_url);
                exit;
            }
        }
    }

    $list_table = new Diyar_Comments_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Yorum Yönetimi', 'diyar-comment'); ?></h1>
        
        <div class="diyar-comment-manager-stats" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 6px;">
            <?php
            $total_comments = wp_count_comments();
            global $wpdb;
            $total_reports = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}diyar_reports");
            $banned_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'diyar_ban_status' AND meta_value = 'banned'");
            ?>
            <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div><strong>Toplam Yorum:</strong> <?php echo number_format_i18n($total_comments->total_comments); ?></div>
                <div><strong>Onay Bekleyen:</strong> <span style="color:#e67e22;"><?php echo number_format_i18n($total_comments->moderated); ?></span></div>
                <div><strong>Toplam Şikayet:</strong> <span style="color:#d63638;"><?php echo number_format_i18n($total_reports); ?></span></div>
                <div><strong>Engellenmiş Kullanıcı:</strong> <span style="color:#d63638;"><?php echo number_format_i18n($banned_users); ?></span></div>
            </div>
        </div>
        
        <?php $list_table->views(); ?>
        <form id="comments-filter" method="get">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
            <?php if (isset($_GET['comment_status'])) : ?>
                <input type="hidden" name="comment_status" value="<?php echo esc_attr($_GET['comment_status']); ?>" />
            <?php endif; ?>
            <?php $list_table->display(); ?>
        </form>
        
        <style>
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-indicator.approved { background: #00a32a; }
        .status-indicator.pending { background: #dba617; }
        .status-indicator.spam { background: #d63638; }
        .status-indicator.trash { background: #646970; }
        
        .comment-actions-wrapper .button {
            margin-right: 4px;
            margin-bottom: 4px;
            font-size: 11px;
            padding: 2px 8px;
            height: auto;
            line-height: 1.4;
        }
        
        .comment-content-wrapper {
            max-width: 500px;
        }
        
        #edit-comment textarea {
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
        }
        
        .diyar-comment-manager-stats {
            border: 1px solid #ddd;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Hızlı düzenle
            $(document).on('click', '.quick-edit-comment', function(e) {
                e.preventDefault();
                var commentId = $(this).data('comment-id');
                $('#comment-text-' + commentId).hide();
                $('#edit-comment-' + commentId).show();
                $('#edit-comment-' + commentId + ' textarea').focus();
            });

            $(document).on('click', '.cancel-edit-comment', function() {
                var wrapper = $(this).closest('[id^="edit-comment-"]');
                var commentId = wrapper.attr('id').replace('edit-comment-', '');
                wrapper.hide();
                $('#comment-text-' + commentId).show();
            });

            $(document).on('click', '.save-edit-comment', function() {
                var button = $(this);
                var commentId = button.data('comment-id');
                var newContent = button.siblings('textarea').val();
                
                if (!newContent.trim()) {
                    alert('Yorum içeriği boş olamaz.');
                    return;
                }
                
                button.prop('disabled', true).text('Kaydediliyor...');

                $.post(ajaxurl, {
                    action: 'diyar_admin_edit_comment',
                    _ajax_nonce: '<?php echo wp_create_nonce("diyar_admin_edit_comment"); ?>',
                    comment_id: commentId,
                    content: newContent
                }).done(function(response) {
                    if (response.success) {
                        $('#comment-text-' + commentId).html(response.data.content).show();
                        $('#edit-comment-' + commentId).hide();
                        
                        // Başarı mesajı
                        $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
                            .insertAfter('.wp-header-end')
                            .delay(3000)
                            .fadeOut();
                    } else {
                        alert('Hata: ' + (response.data.message || 'Bilinmeyen hata'));
                    }
                }).fail(function() {
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                }).always(function() {
                    button.prop('disabled', false).text('Kaydet');
                });
            });
        });
        </script>
        <?php
    }


// AJAX handler'ı ekle
add_action('wp_ajax_diyar_admin_edit_comment', 'diyar_admin_edit_comment_ajax');
function diyar_admin_edit_comment_ajax() {
    // Admin yetkisi kontrolü
    if (!current_user_can('moderate_comments')) {
        wp_send_json_error(array('message' => 'Yetkiniz bulunmuyor.'));
    }
    
    // Nonce kontrolü
    if (!check_ajax_referer('diyar_admin_edit_comment', '_ajax_nonce', false)) {
        wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız.'));
    }
    
    $comment_id = intval($_POST['comment_id']);
    $content = trim($_POST['content']);
    
    if (empty($content)) {
        wp_send_json_error(array('message' => 'Yorum içeriği boş olamaz.'));
    }
    
    // Yorumu güncelle
    $result = wp_update_comment(array(
        'comment_ID' => $comment_id,
        'comment_content' => wp_kses_post($content)
    ));
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }
    
    wp_send_json_success(array(
        'content' => wp_trim_words(esc_html($content), 50),
        'message' => 'Yorum başarıyla güncellendi.'
    ));
}