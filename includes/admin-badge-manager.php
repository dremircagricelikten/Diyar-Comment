<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('diyar_handle_badge_form_submissions')) {
    function diyar_handle_badge_form_submissions() {
        if (!isset($_POST['diyar_badge_nonce']) || !wp_verify_nonce($_POST['diyar_badge_nonce'], 'diyar_badge_actions')) return;
        if (!current_user_can('manage_options')) return;

        global $wpdb;
        $badges_table = $wpdb->prefix . 'diyar_badges';

        if (isset($_POST['add_badge'])) {
            $name = sanitize_text_field($_POST['badge_name']);
            $template = sanitize_key($_POST['badge_template']);
            $color = sanitize_hex_color($_POST['badge_color']);
            $templates = diyar_get_badge_templates();

            if ($name && $color && isset($templates[$template])) {
                $svg = str_replace('{color}', $color, $templates[$template]);
                $result = $wpdb->insert($badges_table, [
                    'badge_name' => $name, 
                    'badge_svg' => $svg, 
                    'is_automated' => 0
                ]);
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Rozet başarıyla oluşturuldu.', 'diyar-comment') . '</p></div>';
                }
            }
        }
        
        if (isset($_POST['add_auto_badge'])) {
            $name = sanitize_text_field($_POST['auto_badge_name']);
            $template = sanitize_key($_POST['auto_badge_template']);
            $color = sanitize_hex_color($_POST['auto_badge_color']);
            $condition_type = sanitize_key($_POST['auto_condition_type']);
            $condition_value = intval($_POST['auto_badge_condition_value']);
            $templates = diyar_get_badge_templates();
            
            $valid_conditions = ['comment_count', 'like_count', 'level'];
            
            if ($name && $color && $condition_value > 0 && isset($templates[$template]) && in_array($condition_type, $valid_conditions)) {
                $svg = str_replace('{color}', $color, $templates[$template]);
                $result = $wpdb->insert($badges_table, [
                    'badge_name' => $name, 
                    'badge_svg' => $svg, 
                    'is_automated' => 1, 
                    'auto_condition_type' => $condition_type, 
                    'auto_condition_value' => $condition_value
                ]);
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Otomatik rozet başarıyla oluşturuldu.', 'diyar-comment') . '</p></div>';
                }
            }
        }

        if (isset($_POST['delete_badge'])) {
            $badge_id_to_delete = intval($_POST['badge_id']);
            if ($badge_id_to_delete > 0) {
                $wpdb->delete($badges_table, ['badge_id' => $badge_id_to_delete]);
                $wpdb->delete($wpdb->prefix . 'diyar_user_badges', ['badge_id' => $badge_id_to_delete]);
                echo '<div class="notice notice-success"><p>' . __('Rozet silindi.', 'diyar-comment') . '</p></div>';
            }
        }

        if (isset($_POST['assign_badge'])) {
            $user_id = intval($_POST['assign_user_id']);
            $badge_id = intval($_POST['assign_badge_id']);
            
            if ($user_id && $badge_id) {
                $user_badges_table = $wpdb->prefix . 'diyar_user_badges';
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $user_badges_table WHERE user_id = %d AND badge_id = %d",
                    $user_id, $badge_id
                ));
                
                if (!$exists) {
                    $wpdb->insert($user_badges_table, [
                        'user_id' => $user_id,
                        'badge_id' => $badge_id
                    ]);
                    echo '<div class="notice notice-success"><p>' . __('Rozet kullanıcıya verildi.', 'diyar-comment') . '</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>' . __('Bu kullanıcıda bu rozet zaten var.', 'diyar-comment') . '</p></div>';
                }
            }
        }
    }
}

if (!function_exists('render_badges_page_content')) {
    function render_badges_page_content() {
        diyar_handle_badge_form_submissions(); 
        global $wpdb;
        $all_badges = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}diyar_badges ORDER BY badge_id DESC");
        $templates = diyar_get_badge_templates();
        ?>
        <div class="wrap diyar-admin-wrap">
            <h1><?php _e('Rozet Yönetimi', 'diyar-comment'); ?></h1>
            
            <div class="badge-manager-container">
                <div class="badge-forms-section">
                    <!-- Manuel Rozet Ekleme -->
                    <div class="badge-form-card">
                        <h3><?php _e('Manuel Rozet Ekle', 'diyar-comment'); ?></h3>
                        <p class="description"><?php _e('Kullanıcı profillerinden manuel olarak atamak için rozetler oluşturun.', 'diyar-comment'); ?></p>
                        
                        <form method="post" class="badge-form">
                            <input type="hidden" name="diyar_badge_nonce" value="<?php echo wp_create_nonce('diyar_badge_actions'); ?>">
                            
                            <div class="form-field">
                                <label><?php _e('Şablon Seçin', 'diyar-comment'); ?></label>
                                <div class="badge-templates">
                                    <?php foreach($templates as $key => $svg) : ?>
                                    <div class="template-item" data-template="<?php echo esc_attr($key); ?>" title="<?php echo esc_attr(ucfirst($key)); ?>">
                                        <?php echo str_replace('{color}', '#005B43', $svg); ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="badge_template" id="selected_badge_template" value="shield">
                            </div>
                            
                            <div class="form-field">
                                <label for="badge_name"><?php _e('Rozet Adı', 'diyar-comment'); ?></label>
                                <input type="text" name="badge_name" id="badge_name" placeholder="<?php _e('Örn: Aktif Üye', 'diyar-comment'); ?>" required>
                            </div>
                            
                            <div class="form-field">
                                <label for="badge_color"><?php _e('Rozet Rengi', 'diyar-comment'); ?></label>
                                <input type="text" name="badge_color" class="diyar-color-picker" value="#005B43">
                            </div>
                            
                            <button type="submit" name="add_badge" class="button button-primary"><?php _e('Rozeti Oluştur', 'diyar-comment'); ?></button>
                        </form>
                    </div>

                    <!-- Otomatik Rozet Ekleme -->
                    <div class="badge-form-card">
                        <h3><?php _e('Otomatik Rozet Ekle', 'diyar-comment'); ?></h3>
                        <p class="description"><?php _e('Belirli koşullara ulaşan kullanıcıların otomatik olarak alacağı rozetler.', 'diyar-comment'); ?></p>
                        
                        <form method="post" class="badge-form">
                            <input type="hidden" name="diyar_badge_nonce" value="<?php echo wp_create_nonce('diyar_badge_actions'); ?>">
                            
                            <div class="form-field">
                                <label><?php _e('Şablon Seçin', 'diyar-comment'); ?></label>
                                <div class="badge-templates-auto">
                                    <?php foreach($templates as $key => $svg) : ?>
                                    <div class="template-item-auto" data-template="<?php echo esc_attr($key); ?>" title="<?php echo esc_attr(ucfirst($key)); ?>">
                                        <?php echo str_replace('{color}', '#DAA520', $svg); ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="auto_badge_template" id="selected_auto_badge_template" value="star">
                            </div>
                            
                            <div class="form-field">
                                <label for="auto_badge_name"><?php _e('Rozet Adı', 'diyar-comment'); ?></label>
                                <input type="text" name="auto_badge_name" id="auto_badge_name" placeholder="<?php _e('Örn: 50. Yorum Rozeti', 'diyar-comment'); ?>" required>
                            </div>
                            
                            <div class="form-field">
                                <label for="auto_condition_type"><?php _e('Koşul Türü', 'diyar-comment'); ?></label>
                                <select name="auto_condition_type" id="auto_condition_type" required>
                                    <option value="comment_count"><?php _e('Yorum Sayısı', 'diyar-comment'); ?></option>
                                    <option value="like_count"><?php _e('Toplam Beğeni', 'diyar-comment'); ?></option>
                                    <option value="level"><?php _e('Kullanıcı Seviyesi', 'diyar-comment'); ?></option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="auto_badge_condition_value"><?php _e('Gerekli Değer', 'diyar-comment'); ?></label>
                                <input type="number" name="auto_badge_condition_value" id="auto_badge_condition_value" min="1" required>
                            </div>
                            
                            <div class="form-field">
                                <label for="auto_badge_color"><?php _e('Rozet Rengi', 'diyar-comment'); ?></label>
                                <input type="text" name="auto_badge_color" class="diyar-color-picker" value="#DAA520">
                            </div>
                            
                            <button type="submit" name="add_auto_badge" class="button button-primary"><?php _e('Otomatik Rozeti Oluştur', 'diyar-comment'); ?></button>
                        </form>
                    </div>

                    <!-- Manuel Rozet Atama -->
                    <div class="badge-form-card">
                        <h3><?php _e('Kullanıcıya Rozet Ver', 'diyar-comment'); ?></h3>
                        <p class="description"><?php _e('Manuel rozetleri belirli kullanıcılara verebilirsiniz.', 'diyar-comment'); ?></p>
                        
                        <form method="post" class="badge-form">
                            <input type="hidden" name="diyar_badge_nonce" value="<?php echo wp_create_nonce('diyar_badge_actions'); ?>">
                            
                            <div class="form-field">
                                <label for="assign_user_id"><?php _e('Kullanıcı ID', 'diyar-comment'); ?></label>
                                <input type="number" name="assign_user_id" id="assign_user_id" min="1" required>
                                <small><?php _e('Kullanıcının ID numarasını girin', 'diyar-comment'); ?></small>
                            </div>
                            
                            <div class="form-field">
                                <label for="assign_badge_id"><?php _e('Rozet Seçin', 'diyar-comment'); ?></label>
                                <select name="assign_badge_id" id="assign_badge_id" required>
                                    <option value=""><?php _e('Rozet seçin...', 'diyar-comment'); ?></option>
                                    <?php 
                                    $manual_badges = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}diyar_badges WHERE is_automated = 0");
                                    foreach($manual_badges as $badge) : ?>
                                        <option value="<?php echo $badge->badge_id; ?>"><?php echo esc_html($badge->badge_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="assign_badge" class="button button-secondary"><?php _e('Rozeti Ver', 'diyar-comment'); ?></button>
                        </form>
                    </div>
                </div>

                <!-- Mevcut Rozetler -->
                <div class="badge-list-section">
                    <h3><?php _e('Mevcut Rozetler', 'diyar-comment'); ?></h3>
                    
                    <div class="badge-list">
                        <?php if (!empty($all_badges)) : ?>
                            <?php foreach ($all_badges as $badge) : ?>
                            <div class="badge-item-card">
                                <div class="badge-preview">
                                    <?php echo $badge->badge_svg; ?>
                                </div>
                                <div class="badge-info">
                                    <h4><?php echo esc_html($badge->badge_name); ?></h4>
                                    <p class="badge-type">
                                        <?php if($badge->is_automated): ?>
                                            <?php 
                                            $condition_text = '';
                                            switch($badge->auto_condition_type) {
                                                case 'comment_count':
                                                    $condition_text = sprintf(__('%d Yorum', 'diyar-comment'), $badge->auto_condition_value);
                                                    break;
                                                case 'like_count':
                                                    $condition_text = sprintf(__('%d Beğeni', 'diyar-comment'), $badge->auto_condition_value);
                                                    break;
                                                case 'level':
                                                    $condition_text = sprintf(__('Seviye %d', 'diyar-comment'), $badge->auto_condition_value);
                                                    break;
                                            }
                                            printf(__('Otomatik (%s)', 'diyar-comment'), $condition_text); 
                                            ?>
                                        <?php else: ?>
                                            <?php _e('Manuel', 'diyar-comment'); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php 
                                    // Bu rozete sahip kullanıcı sayısı
                                    $user_count = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$wpdb->prefix}diyar_user_badges WHERE badge_id = %d",
                                        $badge->badge_id
                                    ));
                                    ?>
                                    <p class="badge-stats"><?php printf(__('%d kullanıcıda var', 'diyar-comment'), $user_count); ?></p>
                                </div>
                                <div class="badge-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="diyar_badge_nonce" value="<?php echo wp_create_nonce('diyar_badge_actions'); ?>">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge->badge_id; ?>">
                                        <button type="submit" name="delete_badge" class="button button-small is-destructive" onclick="return confirm('<?php _e('Bu rozeti silmek istediğinizden emin misiniz?', 'diyar-comment'); ?>');">
                                            <?php _e('Sil', 'diyar-comment'); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-badges">
                                <p><?php _e('Henüz rozet yok. Yukarıdaki formları kullanarak rozet oluşturun.', 'diyar-comment'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .badge-manager-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .badge-forms-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .badge-form-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .badge-form-card h3 {
            margin: 0 0 10px;
            color: #005B43;
        }
        
        .badge-form-card .description {
            color: #666;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .form-field {
            margin-bottom: 15px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-field input, .form-field select {
            width: 100%;
            max-width: 300px;
        }
        
        .badge-templates, .badge-templates-auto {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 8px 0;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .template-item, .template-item-auto {
            width: 40px;
            height: 40px;
            padding: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: #fff;
        }
        
        .template-item svg, .template-item-auto svg {
            width: 24px;
            height: 24px;
        }
        
        .template-item:hover, .template-item-auto:hover {
            border-color: #005B43;
            transform: scale(1.1);
        }
        
        .template-item.selected, .template-item-auto.selected {
            border-color: #005B43;
            background: #f0f8f5;
            box-shadow: 0 0 0 2px rgba(0, 91, 67, 0.2);
        }
        
        .badge-list {
            display: grid;
            gap: 15px;
        }
        
        .badge-item-card {
            background: #fff;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .badge-preview {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            border-radius: 50%;
        }
        
        .badge-preview svg {
            width: 30px;
            height: 30px;
        }
        
        .badge-info {
            flex: 1;
        }
        
        .badge-info h4 {
            margin: 0 0 5px;
            color: #333;
        }
        
        .badge-type {
            margin: 0 0 5px;
            color: #666;
            font-size: 13px;
        }
        
        .badge-stats {
            margin: 0;
            color: #999;
            font-size: 12px;
        }
        
        .no-badges {
            text-align: center;
            padding: 40px;
            color: #666;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        @media (max-width: 1200px) {
            .badge-manager-container {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
}