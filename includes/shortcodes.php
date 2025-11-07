<?php
if (!defined('ABSPATH')) exit;

// Kullanıcı profil sayfası
add_shortcode('diyar_user_profile', 'diyar_user_profile_shortcode_handler');
function diyar_user_profile_shortcode_handler($atts) {
    $atts = shortcode_atts(array('user_id' => ''), $atts, 'diyar_user_profile');
    
    if (!empty($atts['user_id'])) {
        $user_id = intval($atts['user_id']);
    } else if (isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
    } else if (is_user_logged_in()) {
        $user_id = get_current_user_id();
    } else {
        return '<div class="diyar-error-message"><p>Profil görüntülemek için giriş yapmalısınız veya bir kullanıcı ID belirtmelisiniz.</p></div>';
    }

    if (!$user_id || !get_user_by('id', $user_id)) {
        return '<div class="diyar-error-message"><p>Kullanıcı bulunamadı.</p></div>';
    }
    
    $user_data_obj = get_user_by('id', $user_id);
    $is_own_profile = (is_user_logged_in() && get_current_user_id() == $user_id);
    
    if (!function_exists('diyar_get_user_level_info')) {
        return '<div class="diyar-error-message"><p>Profil sistemi henüz yüklenmedi.</p></div>';
    }
    
    $user_data = array(
        'info' => $user_data_obj,
        'level_info' => diyar_get_user_level_info($user_id),
        'badges' => function_exists('diyar_get_user_badges') ? diyar_get_user_badges($user_id) : array(),
        'comments' => get_comments(array('user_id' => $user_id, 'number' => 10, 'status' => 'approve')),
        'total_comments' => get_comments(array('user_id' => $user_id, 'count' => true, 'status' => 'approve')),
        'total_likes' => function_exists('diyar_get_user_total_likes') ? diyar_get_user_total_likes($user_id) : 0,
        'stats' => function_exists('diyar_get_user_stats') ? diyar_get_user_stats($user_id) : array(),
        'is_own_profile' => $is_own_profile
    );

    ob_start();
    if (file_exists(DIYAR_COMMENT_PATH . 'templates/user-profile-template.php')) {
        include(DIYAR_COMMENT_PATH . 'templates/user-profile-template.php');
    } else {
        echo '<div class="diyar-error-message"><p>Profil şablonu bulunamadı.</p></div>';
    }
    return ob_get_clean();
}

// Kombine Giriş/Kayıt formu
add_shortcode('diyar_auth', 'diyar_auth_shortcode_handler');
add_shortcode('diyar_login', 'diyar_auth_shortcode_handler'); // Eski uyumluluk için
add_shortcode('diyar_register', 'diyar_auth_shortcode_handler'); // Eski uyumluluk için
function diyar_auth_shortcode_handler($atts) {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $profile_url = diyar_get_user_profile_url($user->ID);
        return '<div class="diyar-info-message">
                    <h3>Zaten giriş yapmışsınız!</h3>
                    <p>Merhaba <strong>' . esc_html($user->display_name) . '</strong>, zaten giriş yapmışsınız.</p>
                    <div class="diyar-action-buttons">
                        <a href="' . esc_url($profile_url) . '" class="diyar-button primary">Profilim</a>
                        <a href="' . esc_url(diyar_logout_url()) . '" class="diyar-button secondary">Çıkış Yap</a>
                    </div>
                </div>';
    }
    
    $atts = shortcode_atts(array('redirect_to' => ''), $atts, 'diyar_auth');
    
    ob_start();
    ?>
    <div class="diyar-auth-container">
        <!-- Tab Navigation -->
        <div class="auth-tabs">
            <button class="auth-tab active" data-tab="login">Giriş Yap</button>
            <button class="auth-tab" data-tab="register">Kayıt Ol</button>
        </div>
        
        <!-- Giriş Formu -->
        <div class="diyar-auth-form diyar-login-form tab-content active" id="login-tab">
            <div class="auth-form-header">
                <h2>Hoş Geldiniz</h2>
                <p>Hesabınıza giriş yaparak yorumlarınızı paylaşabilir, tepkilerinizi gösterebilir ve topluluğa katılabilirsiniz.</p>
            </div>
            
            <form id="diyar-login-form" method="post" class="auth-form">
                <?php wp_nonce_field('diyar_auth_nonce', 'nonce'); ?>
                <input type="hidden" name="action" value="diyar_login">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect_to'] ? $atts['redirect_to'] : get_permalink()); ?>">
                
                <div class="form-group">
                    <label for="login_username">Kullanıcı Adı veya E-posta</label>
                    <input type="text" id="login_username" name="username" required placeholder="kullaniciadi@example.com">
                    <div class="field-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                </div>
                
                <div class="form-group password-group">
                    <label for="login_password">Şifre</label>
                    <input type="password" id="login_password" name="password" required placeholder="••••••••">
                    <div class="field-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18,8h-1V6c0-2.76-2.24-5-5-5S7,3.24,7,6v2H6c-1.1,0-2,0.9-2,2v10c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V10C20,8.9,19.1,8,18,8z M12,17c-1.1,0-2-0.9-2-2s0.9-2,2-2s2,0.9,2,2S13.1,17,12,17z M15.1,8H8.9V6c0-1.71,1.39-3.1,3.1-3.1s3.1,1.39,3.1,3.1V8z"/>
                        </svg>
                    </div>
                    <button type="button" class="password-toggle" data-target="login_password">
                        <span class="show-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/>
                            </svg>
                        </span>
                        <span class="hide-icon" style="display:none">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.83,9L15,12.16C15,12.11 15,12.05 15,12A3,3 0 0,0 12,9C11.94,9 11.89,9 11.83,9M7.53,9.8L9.08,11.35C9.03,11.56 9,11.77 9,12A3,3 0 0,0 12,15C12.22,15 12.44,14.97 12.65,14.92L14.2,16.47C13.53,16.8 12.79,17 12,17A5,5 0 0,1 7,12C7,11.21 7.2,10.47 7.53,9.8M2,4.27L4.28,6.55L4.73,7C3.08,8.3 1.78,10 1,12C2.73,16.39 7,19.5 12,19.5C13.55,19.5 15.03,19.2 16.38,18.66L16.81,19.09L19.73,22L21,20.73L3.27,3M12,7A5,5 0 0,1 17,12C17,12.64 16.87,13.26 16.64,13.82L19.57,16.75C21.07,15.5 22.27,13.86 23,12C21.27,7.61 17,4.5 12,4.5C10.6,4.5 9.26,4.75 8,5.2L10.17,7.35C10.76,7.13 11.37,7 12,7Z"/>
                            </svg>
                        </span>
                    </button>
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span class="checkmark"></span>
                        Beni hatırla
                    </label>
                </div>
                
                <button type="submit" class="diyar-submit">
                    <span class="button-text">Giriş Yap</span>
                    <span class="button-loader" style="display:none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="spinning">
                            <path d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z"/>
                        </svg>
                        Giriş yapılıyor...
                    </span>
                </button>
                
                <div class="form-links">
                    <a href="<?php echo wp_lostpassword_url(); ?>" class="auth-link">Şifremi unuttum</a>
                </div>
            </form>
        </div>
        
        <!-- Kayıt Formu -->
        <div class="diyar-auth-form diyar-register-form tab-content" id="register-tab">
            <div class="auth-form-header">
                <h2>Aramıza Katılın</h2>
                <p>Topluluğumuza katılın! Yorumlarınızı paylaşın, tepkilerinizi gösterin ve seviye atlayarak rozet kazanın.</p>
            </div>
            
            <?php if (!get_option('users_can_register')) : ?>
                <div class="form-message error">
                    <p>Şu anda yeni kullanıcı kaydı kabul edilmemektedir.</p>
                </div>
            <?php else : ?>
                <form id="diyar-register-form" method="post" class="auth-form">
                    <?php wp_nonce_field('diyar_auth_nonce', 'nonce'); ?>
                    <input type="hidden" name="action" value="diyar_register">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect_to'] ? $atts['redirect_to'] : get_permalink()); ?>">
                    
                    <div class="form-group">
                        <label for="register_username">Kullanıcı Adı</label>
                        <input type="text" id="register_username" name="username" required placeholder="kullaniciadi" pattern="[a-zA-Z0-9_]+" title="Sadece harf, rakam ve alt çizgi kullanabilirsiniz">
                        <div class="field-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                        <small>Sadece harf, rakam ve alt çizgi (_) kullanabilirsiniz.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="register_email">E-posta Adresi</label>
                        <input type="email" id="register_email" name="email" required placeholder="email@example.com">
                        <div class="field-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20,8L12,13L4,8V6L12,11L20,6M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4Z"/>
                            </svg>
                        </div>
                        <small>Geçerli bir e-posta adresi girin.</small>
                    </div>
                    
                    <div class="form-group password-group">
                        <label for="register_password">Şifre</label>
                        <input type="password" id="register_password" name="password" required minlength="6" placeholder="••••••••">
                        <div class="field-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18,8h-1V6c0-2.76-2.24-5-5-5S7,3.24,7,6v2H6c-1.1,0-2,0.9-2,2v10c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V10C20,8.9,19.1,8,18,8z M12,17c-1.1,0-2-0.9-2-2s0.9-2,2-2s2,0.9,2,2S13.1,17,12,17z M15.1,8H8.9V6c0-1.71,1.39-3.1,3.1-3.1s3.1,1.39,3.1,3.1V8z"/>
                            </svg>
                        </div>
                        <button type="button" class="password-toggle" data-target="register_password">
                            <span class="show-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/>
                                </svg>
                            </span>
                            <span class="hide-icon" style="display:none">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.83,9L15,12.16C15,12.11 15,12.05 15,12A3,3 0 0,0 12,9C11.94,9 11.89,9 11.83,9M7.53,9.8L9.08,11.35C9.03,11.56 9,11.77 9,12A3,3 0 0,0 12,15C12.22,15 12.44,14.97 12.65,14.92L14.2,16.47C13.53,16.8 12.79,17 12,17A5,5 0 0,1 7,12C7,11.21 7.2,10.47 7.53,9.8M2,4.27L4.28,6.55L4.73,7C3.08,8.3 1.78,10 1,12C2.73,16.39 7,19.5 12,19.5C13.55,19.5 15.03,19.2 16.38,18.66L16.81,19.09L19.73,22L21,20.73L3.27,3M12,7A5,5 0 0,1 17,12C17,12.64 16.87,13.26 16.64,13.82L19.57,16.75C21.07,15.5 22.27,13.86 23,12C21.27,7.61 17,4.5 12,4.5C10.6,4.5 9.26,4.75 8,5.2L10.17,7.35C10.76,7.13 11.37,7 12,7Z"/>
                                </svg>
                            </span>
                        </button>
                        <small>En az 6 karakter olmalıdır.</small>
                        <div class="password-strength">
                            <div class="strength-bar"></div>
                            <span class="strength-text"></span>
                        </div>
                    </div>
                    
                    <div class="form-group password-group">
                        <label for="register_password_confirm">Şifre Tekrar</label>
                        <input type="password" id="register_password_confirm" name="password_confirm" required placeholder="••••••••">
                        <div class="field-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18,8h-1V6c0-2.76-2.24-5-5-5S7,3.24,7,6v2H6c-1.1,0-2,0.9-2,2v10c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V10C20,8.9,19.1,8,18,8z M12,17c-1.1,0-2-0.9-2-2s0.9-2,2-2s2,0.9,2,2S13.1,17,12,17z M15.1,8H8.9V6c0-1.71,1.39-3.1,3.1-3.1s3.1,1.39,3.1,3.1V8z"/>
                            </svg>
                        </div>
                        <button type="button" class="password-toggle" data-target="register_password_confirm">
                            <span class="show-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/>
                                </svg>
                            </span>
                            <span class="hide-icon" style="display:none">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.83,9L15,12.16C15,12.11 15,12.05 15,12A3,3 0 0,0 12,9C11.94,9 11.89,9 11.83,9M7.53,9.8L9.08,11.35C9.03,11.56 9,11.77 9,12A3,3 0 0,0 12,15C12.22,15 12.44,14.97 12.65,14.92L14.2,16.47C13.53,16.8 12.79,17 12,17A5,5 0 0,1 7,12C7,11.21 7.2,10.47 7.53,9.8M2,4.27L4.28,6.55L4.73,7C3.08,8.3 1.78,10 1,12C2.73,16.39 7,19.5 12,19.5C13.55,19.5 15.03,19.2 16.38,18.66L16.81,19.09L19.73,22L21,20.73L3.27,3M12,7A5,5 0 0,1 17,12C17,12.64 16.87,13.26 16.64,13.82L19.57,16.75C21.07,15.5 22.27,13.86 23,12C21.27,7.61 17,4.5 12,4.5C10.6,4.5 9.26,4.75 8,5.2L10.17,7.35C10.76,7.13 11.37,7 12,7Z"/>
                                </svg>
                            </span>
                        </button>
                        <small>Şifrenizi tekrar girin.</small>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms_accepted" required>
                            <span class="checkmark"></span>
                            <span class="terms-link">Kullanım koşullarını</span> okudum ve kabul ediyorum
                        </label>
                    </div>
                    
                    <button type="submit" class="diyar-submit">
                        <span class="button-text">Kayıt Ol</span>
                        <span class="button-loader" style="display:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="spinning">
                                <path d="M12,4a8,8,0,0,1,7.89,6.7A1.53,1.53,0,0,0,21.38,12h0a1.5,1.5,0,0,0,1.48-1.75,11,11,0,0,0-21.72,0A1.5,1.5,0,0,0,2.62,12h0a1.53,1.53,0,0,0,1.49-1.3A8,8,0,0,1,12,4Z"/>
                            </svg>
                            Hesap oluşturuluyor...
                        </span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                
                // Update tab buttons
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(targetTab + '-tab').classList.add('active');
            });
        });
        
        // Password toggle functionality
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const showIcon = this.querySelector('.show-icon');
                const hideIcon = this.querySelector('.hide-icon');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    showIcon.style.display = 'none';
                    hideIcon.style.display = 'inline';
                } else {
                    input.type = 'password';
                    showIcon.style.display = 'inline';
                    hideIcon.style.display = 'none';
                }
            });
        });
        
        // Password strength checker
        const passwordInput = document.getElementById('register_password');
        const strengthBar = document.querySelector('.strength-bar');
        const strengthText = document.querySelector('.strength-text');
        
        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let feedback = '';
                
                if (password.length >= 6) strength += 1;
                if (password.length >= 8) strength += 1;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
                if (/\d/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                const strengthPercentage = (strength / 5) * 100;
                strengthBar.style.width = strengthPercentage + '%';
                
                if (strength <= 1) {
                    strengthBar.className = 'strength-bar weak';
                    feedback = 'Zayıf';
                } else if (strength <= 2) {
                    strengthBar.className = 'strength-bar fair';
                    feedback = 'Orta';
                } else if (strength <= 3) {
                    strengthBar.className = 'strength-bar good';
                    feedback = 'İyi';
                } else {
                    strengthBar.className = 'strength-bar strong';
                    feedback = 'Güçlü';
                }
                
                strengthText.textContent = feedback;
            });
        }
        
        // Login form submission
        const loginForm = document.getElementById('diyar-login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.diyar-submit');
                const buttonText = submitBtn.querySelector('.button-text');
                const buttonLoader = submitBtn.querySelector('.button-loader');
                
                buttonText.style.display = 'none';
                buttonLoader.style.display = 'inline';
                submitBtn.disabled = true;
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (window.showNotification) {
                            window.showNotification(data.data.message, 'success');
                        }
                        setTimeout(() => {
                            window.location.href = data.data.redirect;
                        }, 1500);
                    } else {
                        if (window.showNotification) {
                            window.showNotification(data.data.message, 'error');
                        } else {
                            alert(data.data.message);
                        }
                    }
                })
                .catch(error => {
                    if (window.showNotification) {
                        window.showNotification('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
                    } else {
                        alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    }
                })
                .finally(() => {
                    buttonText.style.display = 'inline';
                    buttonLoader.style.display = 'none';
                    submitBtn.disabled = false;
                });
            });
        }
        
        // Register form submission
        const registerForm = document.getElementById('diyar-register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const password = this.querySelector('[name="password"]').value;
                const passwordConfirm = this.querySelector('[name="password_confirm"]').value;
                
                if (password !== passwordConfirm) {
                    if (window.showNotification) {
                        window.showNotification('Şifreler eşleşmiyor.', 'error');
                    } else {
                        alert('Şifreler eşleşmiyor.');
                    }
                    return;
                }
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('.diyar-submit');
                const buttonText = submitBtn.querySelector('.button-text');
                const buttonLoader = submitBtn.querySelector('.button-loader');
                
                buttonText.style.display = 'none';
                buttonLoader.style.display = 'inline';
                submitBtn.disabled = true;
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (window.showNotification) {
                            window.showNotification(data.data.message, 'success');
                        }
                        setTimeout(() => {
                            window.location.href = data.data.redirect;
                        }, 1500);
                    } else {
                        if (window.showNotification) {
                            window.showNotification(data.data.message, 'error');
                        } else {
                            alert(data.data.message);
                        }
                    }
                })
                .catch(error => {
                    if (window.showNotification) {
                        window.showNotification('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
                    } else {
                        alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    }
                })
                .finally(() => {
                    buttonText.style.display = 'inline';
                    buttonLoader.style.display = 'none';
                    submitBtn.disabled = false;
                });
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

// CSS stilleri ekle
add_action('wp_head', function() {
    $current_post = get_post();
    if (!$current_post) return;
    
    $content = $current_post->post_content;
    if (diyar_comment_content_has_shortcode($content, 'diyar_auth') ||
        diyar_comment_content_has_shortcode($content, 'diyar_login') ||
        diyar_comment_content_has_shortcode($content, 'diyar_register') ||
        diyar_comment_content_has_shortcode($content, 'diyar_user_profile')) {
        ?>
        <style>
        :root {
            --diyar-primary: #005B43;
            --diyar-primary-hover: #007a5a;
            --diyar-primary-light: rgba(0, 91, 67, 0.1);
            --diyar-bg-card: #1a2332;
            --diyar-bg-secondary: #2d3e52;
            --diyar-border: #334155;
            --diyar-text-primary: #ffffff;
            --diyar-text-secondary: #e2e8f0;
            --diyar-text-muted: #94a3b8;
            --diyar-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --diyar-border-radius: 12px;
            --diyar-transition: all 0.2s ease;
        }
        
        /* Spinning animation for loading */
        .spinning {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .diyar-auth-container {
            max-width: 800px;
            margin: 2rem auto;
            background: var(--diyar-bg-card);
            border-radius: var(--diyar-border-radius);
            border: 1px solid var(--diyar-border);
            color: var(--diyar-text-primary);
            box-shadow: var(--diyar-shadow);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 500px;
        }
        
        .diyar-auth-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--diyar-primary), #00b894);
        }
        
        .auth-tabs {
            display: flex;
            border-bottom: 1px solid var(--diyar-border);
        }
        
        .auth-tab {
            flex: 1;
            padding: 1rem 2rem;
            background: none;
            border: none;
            color: var(--diyar-text-muted);
            cursor: pointer;
            font-weight: 600;
            transition: var(--diyar-transition);
            border-bottom: 2px solid transparent;
        }
        
        .auth-tab.active {
            color: var(--diyar-primary);
            border-bottom-color: var(--diyar-primary);
            background: var(--diyar-primary-light);
        }
        
        .auth-tab:hover:not(.active) {
            color: var(--diyar-text-secondary);
            background: var(--diyar-bg-secondary);
        }
        
        .diyar-auth-form {
            padding: 2.5rem;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .auth-form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-form-header h2 {
            margin: 0 0 1rem;
            color: var(--diyar-primary);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .auth-form-header p {
            margin: 0;
            color: var(--diyar-text-secondary);
            line-height: 1.6;
            opacity: 0.9;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--diyar-text-secondary);
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem 3rem 1rem 1rem;
            border: 2px solid var(--diyar-border);
            border-radius: 8px;
            background: var(--diyar-bg-secondary);
            color: var(--diyar-text-primary);
            font-size: 1rem;
            transition: var(--diyar-transition);
            box-sizing: border-box;
        }
        
        .password-group input {
            padding-right: 4.5rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--diyar-primary);
            box-shadow: 0 0 0 3px var(--diyar-primary-light);
        }
        
        .field-icon {
            position: absolute;
            right: 3.5rem;
            top: 2.2rem;
            font-size: 1.2rem;
            opacity: 0.6;
        }
        
        .password-toggle {
            position: absolute;
            right: 0.5rem;
            top: 2.2rem;
            background: none;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: var(--diyar-transition);
            z-index: 2;
        }
        
        .password-toggle:hover {
            background: var(--diyar-primary-light);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: var(--diyar-text-muted);
            font-size: 0.8rem;
        }
        
        .checkbox-group {
            margin: 1.5rem 0;
        }
        
        .checkbox-label {
            display: flex !important;
            align-items: flex-start;
            gap: 0.8rem;
            cursor: pointer;
            line-height: 1.5;
        }
        
        .checkbox-label input {
            width: auto !important;
            margin: 0;
            opacity: 0;
            position: absolute;
        }
        
        .checkmark {
            width: 20px;
            height: 20px;
            background: var(--diyar-bg-secondary);
            border: 2px solid var(--diyar-border);
            border-radius: 4px;
            position: relative;
            flex-shrink: 0;
            margin-top: 2px;
            transition: var(--diyar-transition);
        }
        
        .checkbox-label input:checked + .checkmark {
            background: var(--diyar-primary);
            border-color: var(--diyar-primary);
        }
        
        .checkbox-label input:checked + .checkmark::after {
            content: '';
            position: absolute;
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
            top: 1px;
            left: 6px;
        }
        
        .password-strength {
            margin-top: 0.5rem;
        }
        
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            transition: var(--diyar-transition);
            width: 0%;
        }
        
        .strength-bar.weak { background: #ef4444; }
        .strength-bar.fair { background: #f59e0b; }
        .strength-bar.good { background: #10b981; }
        .strength-bar.strong { background: #059669; }
        
        .strength-text {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
            font-weight: 600;
        }
        
        .diyar-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--diyar-primary), #007a5a);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--diyar-transition);
            position: relative;
            overflow: hidden;
        }
        
        .diyar-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .diyar-submit:hover::before {
            left: 100%;
        }
        
        .diyar-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 91, 67, 0.4);
        }
        
        .diyar-submit:disabled {
            background: var(--diyar-text-muted);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .form-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--diyar-border);
        }
        
        .auth-link, .terms-link {
            color: var(--diyar-primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--diyar-transition);
            display: inline-block;
            margin: 0.25rem 0;
        }
        
        .auth-link:hover, .terms-link:hover {
            color: var(--diyar-primary-hover);
            text-decoration: underline;
        }
        
        .diyar-info-message h3, .diyar-error-message h3 {
            color: var(--diyar-primary);
            margin: 0 0 1rem;
        }
        
        .diyar-error-message h3 {
            color: #ef4444;
        }
        
        .diyar-action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .diyar-button {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--diyar-transition);
            display: inline-block;
        }
        
        .diyar-button.primary {
            background: var(--diyar-primary);
            color: white;
        }
        
        .diyar-button.primary:hover {
            background: var(--diyar-primary-hover);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .diyar-button.secondary {
            background: transparent;
            color: var(--diyar-text-secondary);
            border: 1px solid var(--diyar-border);
        }
        
        .diyar-button.secondary:hover {
            background: var(--diyar-bg-secondary);
            color: var(--diyar-text-primary);
            text-decoration: none;
        }
        
        .form-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .form-message.error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid #ef4444;
        }
        
        @media (max-width: 480px) {
            .diyar-auth-container, .diyar-info-message, .diyar-error-message {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }
            
            .diyar-auth-form {
                padding: 2rem 1.5rem;
            }
            
            .diyar-action-buttons {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
});