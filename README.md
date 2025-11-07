# Manga Diyarı Yorum Eklentisi - Gelişmiş WordPress Yorum Sistemi

**Versiyon:** 4.0.0  
**WordPress Uyumluluğu:** 5.0 ve üzeri  
**PHP Gereksinimleri:** 7.4 ve üzeri

Disqus benzeri, tepki, seviye ve tam teşekkülü topluluk sistemine sahip gelişmiş bir WordPress yorum eklentisi.

## ✨ Özellikler

### 🎯 Temel Özellikler
- **Gelişmiş Yorum Sistemi** - Hızlı AJAX tabanlı yorum gönderimi
- **Tepki Sistemi** - 6 farklı emoji tepki (Güzel, Sevdim, Aşık Oldum, Şaşırtıcı, Gaza Geldim, Üzücü)
- **Beğeni/Beğenmeme** - Her yorum için like/dislike sistemi
- **Seviye Sistemi** - XP kazanarak seviye atlama
- **Rozet Sistemi** - Manuel ve otomatik rozet kazanma
- **Sıralama** - En Yeniler, En Beğenilenler, En Eskiler, En Çok Yanıtlanan
- **Şikayet Sistemi** - Kullanıcıların yorumları şikayet edebilmesi

### 🎨 Tasarım
- **Ana Renk:** #005B43 (Koyu yeşil)
- **Modern UI** - Responsive ve mobil uyumlu tasarım
- **Dark Theme** - Koyu tema desteği
- **Animasyonlar** - Smooth geçişler ve etkileşimler
- **Özelleştirilebilir** - CSS ile tamamen özelleştirilebilir

### 👤 Kullanıcı Sistemi
- **Profil Sayfaları** - Detaylı kullanıcı profilleri
- **Giriş/Kayıt** - Özel giriş ve kayıt sayfaları
- **Avatar Sistemi** - Özel avatar yükleme desteği
- **Kullanıcı İstatistikleri** - Detaylı aktivite takibi

### 🛡️ Güvenlik ve Moderasyon
- **Spam Koruması** - Gelişmiş spam filtreleme
- **Küfür Filtresi** - Özelleştirilebilir küfür engelleme
- **Rate Limiting** - Hız sınırlama sistemi
- **Moderasyon Araçları** - Kapsamlı yönetim paneli
- **Kullanıcı Yaptırımları** - Engelleme ve susturma sistemi

### 🏆 Gamification
- **XP Sistemi** - Yorum başına XP kazanma
- **8 Seviye Kategorisi** - Yeni Başlayan'dan Efsanevi'ye
- **30+ Rozet Şablonu** - Çeşitli SVG rozet tasarımları
- **Otomatik Rozetler** - Yorum sayısı, beğeni, seviye bazlı
- **Manuel Rozetler** - Adminler tarafından verilen özel rozetler

### 📊 Analitik ve Raporlama
- **Kullanıcı İstatistikleri** - Detaylı aktivite analizi
- **Yorum Trendleri** - Günlük/haftalık analiz
- **Popüler İçerik** - En çok beğenilen yorumlar
- **Sistem Sağlığı** - Performans monitörü

## 🚀 Kurulum

1. Plugin dosyalarını `/wp-content/plugins/diyar-comment/` dizinine yükleyin
2. WordPress admin panelinden eklentiyi etkinleştirin
3. **Manga Diyarı Yorum** menüsünden ayarları yapılandırın
4. Profil, giriş ve kayıt sayfalarını oluşturun (opsiyonel)

### Gerekli Sayfalar (Opsiyonel)

Aşağıdaki sayfaları oluşturup shortcode'ları ekleyin:

**Profil Sayfası:**
```
[diyar_user_profile]
```

**Giriş Sayfası:**
```
[diyar_login]
```

**Kayıt Sayfası:**
```
[diyar_register]
```

## ⚙️ Yapılandırma

### Temel Ayarlar

1. **Manga Diyarı Yorum > Ayarlar** menüsünden temel ayarları yapılandırın
2. **Tepkiler** - Tepki sistemini aktif/pasif yapın
3. **Beğeniler** - Like/dislike sistemini ayarlayın
4. **Sıralama** - Yorum sıralama seçeneklerini aktifleştirin
5. **Şikayet** - Şikayet sistemini yapılandırın

### Rozet Yönetimi

**Manga Diyarı Yorum > Rozet Yönetimi** menüsünden:

- **Manuel Rozetler** - Elle atanan özel rozetler
- **Otomatik Rozetler** - Koşul bazlı otomatik rozetler
- **30+ Şablon** - Hazır SVG rozet tasarımları

### Yorum Yönetimi

**Manga Diyarı Yorum > Yorum Yönetimi** menüsünden:

- Tüm yorumları görüntüleyin ve yönetin
- Toplu işlemler (Onaylama, silme, spam işaretleme)
- Kullanıcı yaptırımları (Engelleme, susturma)
- Şikayet edilen yorumları inceleyin

### Spam Koruması

Plugin otomatik olarak aşağıdaki koruma önlemlerini uygular:

- **Rate Limiting** - Kullanıcı başına yorum sınırı
- **Honeypot** - Bot koruması
- **Link Limiti** - Spam link engelleme
- **Küfür Filtresi** - Yasaklı kelime filtreleme
- **IP Bazlı Koruma** - IP seviyesinde hız sınırlama

## 🎨 Özelleştirme

### CSS Özelleştirmesi

Plugin CSS değişkenler kullanır, temanızda override edebilirsiniz:

```css
:root {
    --primary-color: #005B43;          /* Ana renk */
    --primary-hover: #007a5a;          /* Hover rengi */
    --bg-primary: #0d1421;             /* Ana arkaplan */
    --bg-secondary: #1a2332;           /* İkincil arkaplan */
    --text-primary: #ffffff;           /* Ana metin */
    --text-secondary: #e2e8f0;         /* İkincil metin */
}
```

### Seviye Renkleri

```css
/* Seviye 100+ - Efsanevi */
.user-level[data-level="100"] {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
}

/* Seviye 75+ - Mitik */
.user-level[data-level="75"] {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
}

/* Seviye 50+ - Epik */
.user-level[data-level="50"] {
    background: linear-gradient(135deg, #3498db, #2980b9);
}
```

### Hook'lar ve Filtreler

```php
// Yorum gönderildikten sonra
add_action('diyar_comment_posted', function($comment_id, $user_id) {
    // Özel işlemleriniz
});

// XP miktarını değiştir
add_filter('diyar_xp_per_comment', function($xp, $user_id) {
    return $xp * 2; // 2 katı XP ver
});

// Seviye hesaplamasını özelleştir
add_filter('diyar_calculate_level_xp', function($required_xp, $level) {
    return $required_xp * 1.5; // Daha zor seviye atlama
});
```

## 🔧 Geliştirici API'si

### REST API Endpoints

```
GET /wp-json/diyar-comment/v1/user/{id}
GET /wp-json/diyar-comment/v1/stats
```

### JavaScript Events

```javascript
// Yorum gönderildiğinde
document.addEventListener('diyar-comment-posted', function(event) {
    console.log('Yeni yorum:', event.detail.comment_id);
});

// Tepki verildiğinde
document.addEventListener('diyar-reaction-given', function(event) {
    console.log('Tepki:', event.detail.reaction);
});
```

## 📱 Widget Desteği

**Manga Diyarı Yorum İstatistikleri** widget'ı:
- Haftalık istatistikler
- En aktif üyeler
- Topluluk metrikleri

## 🔒 Güvenlik

Plugin aşağıdaki güvenlik önlemlerini alır:

- **XSS Koruması** - Tüm çıktılar escape edilir
- **CSRF Koruması** - Nonce kontrolü
- **SQL Injection** - Prepared statements
- **Rate Limiting** - API kötüye kullanım koruması
- **Input Validation** - Girdi doğrulama
- **Honeypot** - Bot koruması

## 🐛 Sorun Giderme

### Sık Karşılaşılan Sorunlar

**Yorumlar gözükmüyor:**
- Temanızda `comments_template()` çağrısının olduğundan emin olun
- Plugin ayarlarından yorumların aktif olduğunu kontrol edin

**AJAX çalışmıyor:**
- jQuery'nin yüklendiğinden emin olun
- Konsol hatalarını kontrol edin
- Nonce değerlerini doğrulayın

**Stil sorunları:**
- CSS dosyasının yüklendiğini kontrol edin
- Tema çakışmalarını inceleyin
- Tarayıcı cache'ini temizleyin

### Debug Modu

WordPress debug modunu aktifleştirin:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Log dosyaları `/wp-content/debug.log` altında oluşacaktır.

## 📈 Performans

### Optimizasyon İpuçları

- **Cache Kullanın** - Object cache veya sayfa cache eklentileri
- **Database Optimizasyonu** - Düzenli veritabanı temizliği
- **CDN** - Statik dosyalar için CDN kullanın
- **Image Optimization** - Avatar görselleri optimize edin

### Sistem Gereksinimleri

- **WordPress:** 5.0+
- **PHP:** 7.4+
- **MySQL:** 5.6+
- **Memory Limit:** 256MB (önerilen)

## 📝 Değişiklik Geçmişi

### v4.0.0 (2025-09-01)
- Gelişmiş tepki sistemi
- Seviye ve rozet sistemi
- Yeni admin paneli
- Modern UI/UX
- Gelişmiş güvenlik
- REST API desteği
- Widget sistemi
- Otomatik temizlik görevleri

## 🤝 Katkıda Bulunma

1. Repository'yi fork edin
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Değişikliklerinizi commit edin (`git commit -m 'Add amazing feature'`)
4. Branch'inizi push edin (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylar için `LICENSE` dosyasını inceleyin.

## 📧 Destek

- **Plugin Desteği:** mangadiyaru@gmail.com
- **Dokümantasyon:** https://docs.diyardev.com/diyar-comment
- **GitHub:** https://github.com/diyardev/diyar-comment

## ⭐ Değerlendirme

Plugin hoşunuza gittiyse WordPress.org'da 5 yıldız verin ve yorumlarınızı paylaşın!

---

**Hudson Hornet** tarafından ❤️ ile geliştirilmiştir.
