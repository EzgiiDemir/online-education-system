# eCenter — Sunucuya Kurulum ve Güncelleme

Hedef sunucu: **Ubuntu 24.04 LTS** (bkz. `SUNUCU_TEKNIK_GEREKSINIMLERI.md`).
Alan adı: **`ecenter.arucad.edu.tr`**. Uygulama dizini: **`/var/www/ecenter`**.

Bu adımlar Ubuntu 24.04 üzerinde baştan sona denendi: kurulum, dağıtım, ikinci
dağıtım, geri alma, yedek alma ve yedekten geri yükleme. Uygulamanın 600 testi
PHP 8.3 ile geçti.

---

## Gerekenler

| Ne | Kimden |
|---|---|
| Sudo yetkili SSH kullanıcısı (anahtarla giriş) | Sistem yöneticisi |
| `ecenter.arucad.edu.tr` DNS kaydının sunucu IP'sine yönlenmesi | Sistem yöneticisi / DNS |
| `support@arucad.edu.tr` posta kutusunun şifresi ve SMTP AUTH'un açık olması | Microsoft 365 yöneticisi |
| (İsteğe bağlı) Entra ID uygulama kaydı: Tenant ID, Client ID, Client Secret | Azure yöneticisi |
| Release paketi: `ecenter-<tarih>-<commit>.tar.gz` ve `.sha256` dosyası | Geliştirici (`deploy/package.sh`) |

Sunucuda internet erişimi olmalı (apt ve composer paketleri indirir). Node.js
gerekmez; ön yüz paketin içinde derlenmiş olarak gelir.

---

## İlk kurulum

### 1. Paketi sunucuya kopyalayın

Paketin bulunduğu bilgisayarda (Windows PowerShell'de de çalışır):

```bash
scp ecenter-XXXX.tar.gz ecenter-XXXX.tar.gz.sha256 kullanici@SUNUCU_IP:~
```

### 2. SSH ile bağlanın ve paketi açın

```bash
ssh kullanici@SUNUCU_IP
sha256sum -c ecenter-XXXX.tar.gz.sha256          # "OK" yazmalı
mkdir -p ~/ecenter-pkg && tar -xzf ecenter-XXXX.tar.gz -C ~/ecenter-pkg
cd ~/ecenter-pkg
```

### 3. Sunucuyu hazırlayın (tek sefer, 3–5 dakika)

```bash
sudo DOMAIN=ecenter.arucad.edu.tr bash deploy/server-setup.sh
```

Bu betik şunları yapar: Nginx, PHP 8.3, PostgreSQL 16, Composer, Supervisor,
UFW, Fail2ban ve Certbot'u kurar. Ardından `ecenter` veritabanını ve kullanıcısını
rastgele bir şifreyle oluşturur. `/var/www/ecenter/shared/.env` dosyasını
`APP_KEY` ve veritabanı şifresi dolu olarak yazar. Son olarak 2 kuyruk işçisini,
dakikalık zamanlayıcıyı ve her gece 02:30'daki yedeği ayarlar.

Betik tekrar çalıştırılabilir: var olan `.env` dosyasına ve veritabanı şifresine
dokunmaz. UFW zaten açıksa SSH kurallarını değiştirmez, yalnızca 80 ve 443
portlarını ekler.

### 4. Gizli bilgileri girin

```bash
sudo nano /var/www/ecenter/shared/.env
```

- `MAIL_PASSWORD=` → posta kutusu şifresi
- SSO kullanılacaksa `ENTRA_TENANT_ID`, `ENTRA_CLIENT_ID`, `ENTRA_CLIENT_SECRET`

`APP_KEY` satırını **asla değiştirmeyin**. Değişirse tüm oturumlar ve şifreli
veriler okunamaz hale gelir.

### 5. Uygulamayı yayına alın

```bash
sudo bash deploy/deploy.sh ~/ecenter-XXXX.tar.gz
```

Betik sonunda `OK -- /up answers 200.` yazmalı.

### 6. İlk yönetici hesabını oluşturun

```bash
sudo -u www-data php /var/www/ecenter/current/artisan ecenter:create-admin ad.soyad@arucad.edu.tr --name="Ad Soyad"
```

Komut şifreyi ekranda göstermeden sorar (en az 12 karakter).

### 7. HTTPS sertifikası (DNS sunucuya yönlendikten sonra)

```bash
sudo certbot --nginx -d ecenter.arucad.edu.tr --redirect -m it@arucad.edu.tr --agree-tos
```

Certbot sertifikayı alır, HTTP isteklerini HTTPS'e yönlendirir ve otomatik
yenilemeyi kurar. **Giriş yapmak HTTPS kurulduktan sonra mümkündür.** Bu bilinçli
bir güvenlik ayarıdır: oturum çerezleri yalnızca HTTPS üzerinden gönderilir.

### 8. Kontrol

```bash
sudo -u www-data php /var/www/ecenter/current/artisan ecenter:mail-test ad.soyad@arucad.edu.tr
sudo supervisorctl status            # ecenter-worker_00 / _01  RUNNING
```

Ardından tarayıcıda şunları kontrol edin:

- `https://ecenter.arucad.edu.tr/admin` adresinden giriş yapın.
- **Sistem Sağlığı** sayfasında zamanlayıcı ve kuyruk yeşil görünmeli.
- **Portal Ayarları**'nda kabul edilen domainleri kontrol edin. Varsayılan
  `company.com` kaydını silin.

---

## Güncelleme (yeni sürüm)

Geliştirici bilgisayarında (Git Bash):

```bash
bash deploy/package.sh          # dist/ecenter-<tarih>-<commit>.tar.gz üretir
```

Sunucuda:

```bash
sudo bash /var/www/ecenter/current/deploy/deploy.sh ~/ecenter-YENI.tar.gz
```

Güncelleme birkaç saniyelik bakım modunda yapılır. Veritabanı geçişleri
otomatik çalışır ve yeni sürüme tek adımda geçilir. Son 5 sürüm
`/var/www/ecenter/releases/` altında saklanır.

Güncellemeden önce elle yedek almak isterseniz:

```bash
sudo -u www-data /var/www/ecenter/current/deploy/backup.sh
```

## Geri alma

```bash
sudo bash /var/www/ecenter/current/deploy/rollback.sh
```

Bu komut yalnızca kodu bir önceki sürüme döndürür. Veritabanı geçişleri geri
alınmaz. Gerekirse aşağıdaki yedekten geri yükleme adımını kullanın.

## Yedekler

- Her gece 02:30'da alınır: `/var/www/ecenter/shared/storage/app/backups/`
  (veritabanı `.sql` + tüm yüklenen dosyalar `.tar.gz`). Sunucuda 14 gün saklanır.
- Son başarılı yedeğin zamanı yönetim panelinde **Sistem Sağlığı** sayfasında
  görünür. Yedek alınamazsa o satır "gecikmiş" olarak işaretlenir; nedeni
  `/var/www/ecenter/shared/storage/logs/backup.log` dosyasındadır.
- **Bu yedekler aynı diskte durur.** Sistem yöneticisi bunları her gece şifreli
  olarak başka bir depoya kopyalamalıdır (30 gün + aylık arşiv; gereksinimler §8).
- Geri yükleme (mevcut veritabanının üzerine yazar):

```bash
cd /var/www/ecenter/current
sudo -u www-data bash deploy/restore.sh storage/app/backups/db-TARIH.sql storage/app/backups/storage-TARIH.tar.gz
```

## Dosya yerleri

| Ne | Nerede |
|---|---|
| Yayındaki sürüm | `/var/www/ecenter/current` (bir sürüm dizinini gösteren kısayol) |
| Ayarlar | `/var/www/ecenter/shared/.env` |
| Ders içerikleri, ödevler, kayıtlar (yalnızca yetkili kullanıcıya, uygulama üzerinden sunulur) | `/var/www/ecenter/shared/storage/app/private/` |
| Herkese açık görseller (logo, kapak, avatar) | `/var/www/ecenter/shared/storage/app/public/` |
| Uygulama logları | `/var/www/ecenter/shared/storage/logs/laravel-*.log` |
| Kuyruk işçisi logu | `/var/www/ecenter/shared/storage/logs/worker.log` |
| Nginx | `/etc/nginx/sites-available/ecenter`, `/var/log/nginx/` |
| Kuyruk işçileri | `/etc/supervisor/conf.d/ecenter-worker.conf` |
| Zamanlayıcı (yedek, temizlik, hatırlatmalar `bootstrap/app.php` içinde) | `/etc/cron.d/ecenter` |

## Canlı sunucuda asla çalıştırmayın

- `php artisan migrate:fresh`: Tüm veriyi siler.
- `php artisan db:seed` ve `php artisan demo:seed` üretimde zaten çalışmaz
  (uygulama reddeder), çünkü şifresi `password` olan demo hesaplar oluştururlar.
- `php artisan key:generate`: Mevcut `APP_KEY` değerini değiştirir.

## Sistem yöneticisine kalan işler

- SSH erişimini VPN'e veya izinli IP'lere sınırlamak; parola ile girişi ve
  doğrudan root girişini kapatmak.
- Gece yedeklerini sunucu dışına şifreli kopyalamak.
- `/up` adresini, disk doluluğunu ve SSL süresini izlemek.

## Sık karşılaşılan sorunlar

| Belirti | Neden / Çözüm |
|---|---|
| Giriş yapınca tekrar giriş sayfasına dönüyor | HTTPS henüz kurulmadı (adım 7) |
| E-postalar gitmiyor | `ecenter:mail-test` komutunu çalıştırın. `535 5.7.139` hatası M365'te SMTP AUTH'un kapalı olduğunu gösterir |
| Bildirimler hiç gitmiyor | `sudo supervisorctl status` ile işçilerin RUNNING olduğunu kontrol edin |
| 500 hatası | `sudo tail -50 /var/www/ecenter/shared/storage/logs/laravel-*.log` |
| Büyük dosya yüklenmiyor (413) | Sınır 600 MB'tır: Nginx `client_max_body_size` ve `/etc/php/8.3/fpm/conf.d/99-ecenter.ini` |
