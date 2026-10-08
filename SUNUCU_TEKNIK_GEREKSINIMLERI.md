# eCenter Sunucu Teknik Gereksinimleri

**Tarih:** 5 Ekim 2026  
**Kapsam:** İlk üretim kurulumu ve orta ölçekli kullanım

## 1. Domain ve erişim

- Ana adres: **`https://ecenter.arucad.edu.tr`**
- DNS: `ecenter` alt alan adı sunucunun sabit genel IP adresine yönlendirilmelidir.
- Yönetim paneli ve API için ayrı alt alan adı açılmamalı; mevcut **`/admin`** ve **`/api`** yolları kullanılmalıdır.
- Tüm HTTP trafiği HTTPS'e yönlendirilmelidir.
- TLS sertifikası: **Let's Encrypt**, otomatik yenileme ile.
- Microsoft Entra ID kullanılacaksa yönlendirme adresi: **`https://ecenter.arucad.edu.tr/auth/entra/callback`**

## 2. Önerilen sunucu

- Mimari: **x86_64 sanal sunucu**
- İşlemci: **4 vCPU**
- Bellek: **8 GB RAM**
- Disk: **500 GB NVMe SSD**
- Ağ: **1 Gbit bağlantı** ve sabit IP
- Yapı: Uygulama ve veritabanı başlangıçta aynı sunucuda çalışabilir.
- Disk kapasitesi izlenmelidir; sistem 500 MB'a kadar ders kaydı kabul eder ve kayıtları 180 gün saklar.

## 3. İşletim sistemi

- **Ubuntu Server 24.04 LTS (64-bit)**
- Masaüstü arayüz kurulmasına gerek yoktur.
- Güvenlik güncellemeleri otomatik uygulanmalıdır.
- Sunucu saat dilimi: **Europe/Istanbul**

## 4. Uygulama bileşenleri

- Web sunucusu: **Nginx**
- Uygulama çalışma ortamı: **PHP 8.3 FPM**
- Gerekli temel PHP modülleri: `pgsql`, `intl`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `fileinfo`, `opcache`
- PHP paket yönetimi: **Composer 2**
- Arka plan işleri: **Supervisor** altında iki Laravel kuyruk işçisi
- Zamanlanmış görevler: Laravel scheduler için dakikada bir **cron** çalıştırılması
- Ön yüz derleme: **Node.js yalnızca kurulum/dağıtım aşamasında** gereklidir; sürekli servis olarak çalıştırılmamalıdır.
- Redis: İlk kurulumda gerekli değildir; oturum, önbellek ve kuyruk mevcut yapıda veritabanını kullanır.

## 5. Veritabanı

- **PostgreSQL 16**
- Veritabanı adı: **`ecenter`**
- Uygulama için ayrı bir veritabanı kullanıcısı oluşturulmalıdır.
- Veritabanı dış ağa açılmamalı; yalnızca yerel sunucudan erişilmelidir.
- UTF-8 karakter seti kullanılmalıdır.

## 6. Dosya, e-posta ve entegrasyonlar

- Yüklenen ders materyalleri ve kayıtlar sunucudaki kalıcı diskte tutulmalıdır.
- Laravel'in `storage` ve `bootstrap/cache` dizinleri web servis kullanıcısı tarafından yazılabilir olmalıdır.
- E-posta gönderimi: **Microsoft 365 SMTP**, TLS üzerinden `smtp.office365.com:587`
- Kurumsal giriş istenirse **Microsoft Entra ID SSO** bilgileri ayrıca tanımlanmalıdır.

## 7. Güvenlik

- İnternete yalnızca **80 ve 443** portları açılmalıdır.
- **22/SSH** erişimi yalnızca kurum VPN'i veya izinli IP adresleriyle sınırlandırılmalıdır.
- SSH girişinde parola yerine anahtar kullanılmalı; doğrudan root girişi kapatılmalıdır.
- Uygulama üretim ayarları: `APP_ENV=production`, `APP_DEBUG=false`, güvenli çerezler açık.
- `.env`, veritabanı ve yedek dosyaları web üzerinden erişilemez olmalıdır.
- Basit saldırı engelleme için **UFW** ve **Fail2ban** kullanılmalıdır.

## 8. Yedekleme ve izleme

- PostgreSQL ve yüklenen dosyalar **her gece** yedeklenmelidir.
- Yedekler aynı sunucuda bırakılmamalı; şifreli olarak ayrı bir depoya kopyalanmalıdır.
- Saklama: **30 günlük yedek**, ayrıca aylık bir arşiv.
- Geri yükleme işlemi en az üç ayda bir test edilmelidir.
- Uygulamanın **`/up`** sağlık adresi, disk kullanımı, SSL süresi, kuyruk ve veritabanı izlenmelidir.
- Kritik hata ve kapasite uyarıları sistem yöneticisine e-posta ile gönderilmelidir.

## 9. Kurulum özeti

- **Ubuntu 24.04 LTS + Nginx + PHP 8.3 FPM + PostgreSQL 16**
- **4 vCPU, 8 GB RAM, 500 GB NVMe**
- **Supervisor + cron + günlük dış yedek + HTTPS**
- Canlıya geçmeden önce domain, SMTP, Entra ID ve yedekten geri dönüş testi tamamlanmalıdır.

## Kaynaklar

- [Laravel 13 sunucu ve dağıtım gereksinimleri](https://laravel.com/framework/docs/deployment)
- [Ubuntu sürüm ve destek yaşam döngüsü](https://ubuntu.com/about/release-cycle)
- [Ubuntu 24.04 PHP 8.3 paketi](https://packages.ubuntu.com/noble/php/php8.3-fpm)
- [Ubuntu 24.04 PostgreSQL 16 paketi](https://packages.ubuntu.com/noble/postgresql-16)
- [PostgreSQL sürüm destek politikası](https://www.postgresql.org/support/versioning/)
