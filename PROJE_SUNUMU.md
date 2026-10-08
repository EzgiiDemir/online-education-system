# Arucad Campus LMS — Proje Sunumu

*Bu doküman commit edilmemiştir, sadece yerel bir referans notudur. Projenin bu oturum itibarıyla ulaştığı son, gerçek hale göre yazılmıştır.*

---

## 1. Bu proje ne, tek cümleyle?

**Arucad Campus LMS**, bir şirketin kendi eğitimlerini (video, doküman, sunum, ödev, sınav, canlı ders) tek bir portaldan planlayıp, çalışanlarına dağıtıp, kimin gerçekten tamamladığını doğrulayıp, otomatik sertifika verdiği; hepsi **kurum içinde barınan** (Zoom/Teams gibi dış bir araca ihtiyaç duymayan) kurumsal bir eğitim platformu.

Dört ayrı kullanıcı rolü, dört ayrı panel etrafında kurulu:

| Rol | Panel | Temel iş |
|---|---|---|
| Öğrenci (learner) | Genel site (`/`) | Eğitim keşfet, izle, sınav çöz, ödev teslim et, canlı derse katıl, sertifika al |
| Eğitmen (instructor) | `/instructor` | Kendi eğitimini planla, müfredat/materyal yönet, katılımcı/yoklama takip et, erişim kaydını incele |
| Departman yöneticisi (manager) | `/manager` | Kendi ekibinin eğitim ilerlemesini tek ekrandan izle |
| Sistem yöneticisi (admin) | `/admin` (Filament) | Tüm sistemi uçtan uca yönet — kullanıcı, eğitim, sınav, sertifika, denetim kaydı |

Projenin belirleyici tasarım kararı hâlâ aynı: **"tamamlandı" demek gerçekten tamamlandı demek.** Sunucu, istemcinin bildirdiği hiçbir zaman bilgisine güvenmiyor (bkz. §6).

---

## 2. Panel 1 — Öğrenci (Learner)

Genel siteyi ziyaret eden her çalışanın gördüğü panel. Layout: `resources/views/lms/layout.blade.php` — üstte sabit bir navbar: marka solda, geri kalan her şey (menü sekmeleri, arama, dil, profil) sağda tek sırada. Aynı "marka solda / gerisi sağda" düzeni artık eğitmen ve yönetici panellerinde de birebir aynı.

- **Ana sayfa / Katalog (`/`)** — Sadece **yayında** olan eğitimler, kart listesi. Her kartta canlı/içerik tabanlı etiketi, dil, eğitmen, seans sayısı, kayıt durumu.
- **Eğitimlerim (`/my-learning`)** — Kayıtlı olduğu eğitimler: seans/materyal/müfredat listeleri, tamamlanan eğitimler için sertifika indirme.
- **Öğrenim yolları (`/learning-paths`)** — Birden çok eğitimi belirli bir sırada (veya sırasız) bir araya getiren paket programlar; bir yola kaydolmak, içindeki eğitimlere de otomatik kaydediyor.
- **Yetkinliklerim (`/skills`)** — Tamamlanan eğitimler üzerinden kazanılan yetkinliklerin listesi.
- **Takvim (`/calendar`)** — Yaklaşan canlı seanslar; her seans için tek tıkla `.ics` dosyası indirip kendi takvimine (Outlook/Google) ekleyebiliyor.
- **Arama (`/search`)** — Eğitim, etkinlik, materyal, seans ve eğitmen adında canlı (debounce'lu) arama; navbar'daki arama kutusundan da anlık sonuç açılır penceresiyle çalışıyor.
- **Bildirimler** — Navbar'daki profil menüsünün içine gömülü bir bildirim merkezi (son 6 bildirim + "tümünü okundu işaretle") ve ayrı bir tam liste sayfası (`/notifications`). Aynı olaylar e-posta ile de gidiyor (bkz. §7).
- **İçerik görüntüleme (`/materials/{id}`, `/activities/{id}`)** — Videoda gerçek zamanlı "izlenen süre" göstergesi; PDF/sunumda "yaklaşık okuma süresi" ipucu + doğrudan "Okudum / İnceledim" ile tamamlama. Bkz. §6.
- **Sınav (`/activities/{id}`, tip=quiz)** — Çoktan seçmeli/çoklu seçim/doğru-yanlış sorular, geçme notu ve deneme hakkı sınırı; admin panelinde yazılan sorular buradan çözülüyor.
- **Ödev (`/activities/{id}`, tip=assignment)** — Metin/dosya/URL teslimi; eğitmen panelinden puanlanıp geri bildirim yazılabiliyor.
- **Canlı sınıf (`/sessions/{id}/classroom`)** — Portal içi WebRTC görüntülü sınıf: kamera/mikrofon seçimi, video karoları, sohbet, katılımcı listesi, ekran paylaşımı, (eğitmen için) kayıt başlat/durdur. "Dersi tamamla" yalnızca yeterli süre odada kalınınca aktifleşiyor.
- **Sertifika (`/certificates/{id}`)** — Yazdırılabilir PDF sertifika. Ayrıca **herkese açık bir doğrulama sayfası** var (`/verify/{sertifika-no}`): bir sertifikanın gerçekliği, sahibi giriş yapmadan da üçüncü bir tarafça (ör. işveren) doğrulanabiliyor.
- **Hesabım (`/account`)** — Profil bilgileri + şifre onaylı, geri alınamaz hesap silme.
- **Giriş / Kayıt** — E-posta+şifre, opsiyonel **Microsoft Entra ID (Azure AD) SSO**, opsiyonel self-servis kayıt (izinli e-posta domainleriyle sınırlı) — hepsi portal ayarından açılıp kapanıyor.

---

## 3. Panel 2 — Eğitmen (Instructor)

`/instructor` altında, öğrenci panelindekiyle birebir aynı navbar düzenini kullanan kendi çalışma alanı (`instructor-layout.blade.php`). Eskiden sol tarafta sabit bir kenar çubuğu vardı; artık marka solda, "Genel bakış / Eğitimlerim / Profilim" sekmeleri ve profil menüsü sağda, tek bir üst navbar'da. Sayfalar arası gezinmede ayrıca bir "Geri" butonu yok — sekmeler zaten aynı işi görüyor.

- **Genel bakış (`/instructor`)** — Üç metrik kartı (aktif eğitim, toplam katılımcı, planlanan seans) + günün eylemleri + kendi eğitimlerinin kart listesi + doğrudan bu sayfadan yeni eğitim planlama formu.
- **Eğitim yönetimi (`/instructor/courses/{id}`)** — Katılımcılar, müfredat (bölüm + etkinlik), dönemler, seanslar tek ekranda. Müfredattaki her materyal ve etkinliğin yanında artık **Önizle / Düzenle / Sil** butonları var (öncesinde materyaller hiç yönetilemiyordu, sadece eklenebiliyordu).
- **Erişim ve işlem kaydı (`/instructor/courses/{id}/log`)** — Yeni bir sayfa: bir eğitimdeki her materyal/etkinliğe **kim ne zaman erişti, ne kadar izledi, tamamladı mı** (öğrenci tarafı) + eğitimin/bölümlerin/etkinliklerin/materyallerin üzerinde **kim ne zaman ne değiştirdi** (denetim izi) — ikisi de tek sayfada, üstte özet istatistiklerle (toplam erişim, tamamlanma oranı, ortalama izleme süresi, benzersiz öğrenci sayısı).
- **Profilim (`/instructor/profile`)** — Ad, departman, unvan, fotoğraf, biyografi düzenlenebilir; e-posta kilitli.

**Yetki sınırı:** Bir eğitmen sadece kendi eğitimini görüp düzenleyebilir (`CoursePolicy`); admin her eğitime erişebilir.

---

## 4. Panel 3 — Departman Yöneticisi (Manager)

`/manager` altında, eğitmen paneliyle aynı görsel dilde ama çok daha sade bir tek-sayfalık panel (`manager-layout.blade.php` / `manager-dashboard.blade.php`). Bir kullanıcının "yöneticisi" (`manager_id`) bu hesapsa, o kişi otomatik olarak bu panelde "ekip" sayılıyor.

- **Ekip genel bakış** — Doğrudan bağlı her çalışan için kaç eğitime kayıtlı / kaçını tamamlamış; tamamlanmamış kayıtların listesi (kimin, hangi eğitimi, ne zamandır beklediği); ekibin toplam aldığı sertifika sayısı.

Bu panel salt-okunur bir izleme ekranı — yönetici içerik oluşturmuyor, sadece kendi ekibinin durumunu görüyor.

---

## 5. Panel 4 — Sistem Yöneticisi (Admin, Filament)

`/admin` altında, Filament 4 üzerine kurulu, artık **üstten menülü** (`topNavigation()`) çalışan tam bir yönetim merkezi — marka solda, kaynak grupları sağda, tıpkı diğer üç panelle aynı düzende. Renk paleti kurumsal marka renkleriyle özelleştirilmiş; TR/EN ve açık/koyu tema Filament'in kendi altyapısıyla çalışıyor.

**12 kaynak, 5 navigasyon grubunda:**

| Grup | Kaynaklar |
|---|---|
| Katalog | Eğitimler, Dönemler (Course Runs) |
| Eğitim yönetimi | Müfredat bölümleri, Etkinlikler, Materyaller |
| Canlı sınıflar | Seanslar |
| Katılımcılar | Kullanıcılar, Kayıtlar (Enrollments), Öğrenim Yolları, Yetkinlikler |
| Sistem | Portal Ayarları, Denetim Günlüğü |

**Öne çıkan, bu oturumda eklenen iki yenilik:**

1. **Sınav soru/şık yazarlığı** — Daha önce "quiz" tipi bir etkinlik oluşturulabiliyordu ama içine tek bir soru eklemenin **hiçbir yolu yoktu**. Artık Etkinlikler kaynağında, tipi "Sınav" olan her etkinliğin altında soru + şık (doğru cevap işaretlemeli, tek/çoklu seçim veya doğru-yanlış) yazılabiliyor — tamamen admin panelinden, koda dokunmadan.
2. **Her ana kaynakta tekil Önizle / Düzenle / Sil / Log** — Öncesinde sadece toplu (checkbox ile seçip) silme vardı, tek bir satırı silmenin bile yolu yoktu. Artık Eğitimler, Etkinlikler, Materyaller, Bölümler, Seanslar, Dönemler, Kayıtlar, Öğrenim Yolları, Yetkinlikler, Kullanıcılar — hepsinde satır bazlı Önizle (eğitim/materyal/etkinlik için gerçek dosyayı/genel sayfayı yeni sekmede açar), Düzenle, Sil butonları var. Denetlenebilir (Auditable) her kayıt için de tek tıkla o kaydın oluşturma/güncelleme/silme geçmişini gösteren ortak bir **"Kayıt geçmişi"** butonu eklendi.

**Portal Ayarları** — tek kayıtlı global ayar: portal adı/logosu, dil, kabul edilen e-posta domainleri, yerel giriş / self-servis kayıt aç-kapa, Entra ID SSO yapılandırması, canlı sınıf özelliği aç-kapa.

**Panel ana sayfası** — istatistik kartları: yayındaki eğitim sayısı, aktif kayıt, tamamlanma oranı, verilen sertifika sayısı, yaklaşan canlı seanslar, aktif eğitmen sayısı.

---

## 6. Projenin belirleyici kuralı: sahte "tamamlandı" imkânsız — ama artık içerik tipine göre farklı çalışıyor

Kural hâlâ aynı sertlikte, ama bu oturumda **video ile doküman/sunum için ayrıştırıldı** — önceden ikisine de aynı sahte "süre sayacı" mantığı (arka planda kalan sekmeler saymaz vb.) uygulanıyordu, oysa bir PDF'te "sekme açık kaldı" hiçbir şey kanıtlamıyor.

**Video (materyal/etkinlik):**
Sayfa açıkken, sekme görünür olduğu sürece (`document.visibilityState==='visible'`) 5 saniyede bir sunucuya "heartbeat" gidiyor. Sunucu istemcinin bildirdiği süreye **hiç güvenmiyor** — her kabul edilen çağrıda sabit +5 saniye ekliyor, iki çağrı arası en az 4 saniye zorunlu. Gerekli eşik videonun gerçek süresinin **%90'ı**; bu süre artık videonun kendisinden (`<video>` elementinin `loadedmetadata` olayından) **otomatik algılanıyor** — kimse elle süre girmiyor. Sunucu bu süreyi yalnızca ilk kez bildirildiğinde kabul ediyor; bir öğrenci daha kısa bir sahte süre göndererek eşiği küçültemiyor.

**PDF / Doküman / Sunum:**
Sahte zamanlayıcı tamamen kaldırıldı. Bunun yerine dosyanın sayfa sayısına göre "Yaklaşık X dk okuma süresi" ipucu gösteriliyor (kesin bir kanıt değil, sadece bir tahmin olarak sunuluyor), tamamlama yetkisi doğrudan öğrenciye veriliyor: "Okudum / İnceledim" butonu, hiçbir süre koşuluna bağlı değil.

**Canlı sınıf:** oturumda geçirilen süre, gerçekten kamera/mikrofon açılıp odaya girildikten sonra sayılmaya başlıyor; yüz yüze eğitimlerde sayaç yok, sadece eğitmenin elle işaretlemesi geçerli.

---

## 7. E-posta bildirimleri

Uygulama içi bildirimlerin (`notifications` tablosu, navbar'daki zil) yanında dört olay artık **e-posta ile de** gönderiliyor: yeni eğitim yayınlandığında, canlı ders planlandığında, bir eğitimin son tarihi yaklaştığında (`SendTrainingReminders` günlük komutu), bir sertifikanın süresi dolmak üzereyken.

**Operasyonel not:** Bu dört bildirim sınıfı `ShouldQueue` kullanıyor ve `.env`'de `QUEUE_CONNECTION=database` tanımlı, ama projede bir kuyruk işçisi (`php artisan queue:work`) sürekli çalıştıran bir servis/süreç yönetimi yok. Yani kod doğru ama üretim ortamında bir worker çalıştırılmadan bu e-postalar kuyrukta bekleyip kalır — bu bir kod hatası değil, deploy/altyapı meselesi.

---

## 8. Gösterim (demo) verisi — `php artisan demo:seed` / `demo:clear`

Portalın çok kullanıcılı, hareketli haliyle nasıl göründüğünü ekranda görebilmek için statik, tekrar çalıştırılabilir bir gösterim verisi seti eklendi: 8 öğrenci, 1 ek eğitmen, 2 ek eğitim, enrolled/in_progress/completed durumlarında dağılmış kayıtlar, sertifikalar, bekleyen bir ödev teslimi, gerçek sorularla dolu bir sınav.

`php artisan demo:seed` ile eklenir, `php artisan demo:clear` ile **sadece bu gösterim verisi** (gerçek veriye dokunmadan) tek komutla kaldırılır — her satır ayrı bir izleme tablosunda (`demo_seed_records`) kayıtlı olduğu için "bu gerçek mi yoksa gösterim verisi mi" tahmin etmeye gerek kalmıyor.

---

## 9. Veri modeli (özet)

**23 model:** `User`, `Course`, `CourseRun`, `CourseSection`, `CourseSession`, `Activity`, `Material`, `Enrollment`, `MaterialProgress`, `ActivityProgress`, `SessionProgress`, `SessionAttendance`, `Certificate`, `ClassMessage`, `PortalSetting`, `LearningPath`, `Skill`, `QuizQuestion`, `QuizOption`, `QuizAttempt`, `AssignmentSubmission`, `AuditLog`, `DemoSeedRecord`.

Müfredat hâlâ iki paralel yapı üzerinden yürüyor — eski **Session/Material** (canlı seans + dosya) ile daha yeni **Section/Activity** (bölüm + çok tipli etkinlik, sınav/ödev dahil) — ikisi de aynı `Enrollment`/ilerleme sistemine besleniyor. Kademeli evrimi yansıtan, ileride birleştirilebilecek bir teknik borç.

**Denetim izi (`AuditLog` + `Auditable` trait):** `Course`, `CourseSection`, `Activity`, `Material`, `CourseRun`, `CourseSession`, `Skill`, `Enrollment`, `Certificate`, `AssignmentSubmission`, `LearningPath`, `PortalSetting`, `User` — bu modellerden birinde bir oluşturma/güncelleme/silme olduğunda otomatik olarak kim/ne zaman/ne değişti bilgisi yazılıyor; hem eğitmen panelindeki erişim kaydında hem admin panelindeki "Kayıt geçmişi" butonunda tüketiliyor.

**Yetkilendirme:** İki Policy sınıfı — `CoursePolicy` ve `CertificatePolicy`. Diğer kaynaklar için controller içi kontrol ya da Filament kaynak görünürlüğü kullanılıyor.

---

## 10. Öne çıkan teknik özellikler

- **Dış araca bağımsız canlı sınıf** — WebRTC + STUN, tamamen portal içi peer-to-peer görüntülü ders; kayıt `MediaRecorder` ile tarayıcıda alınıp sunucuya yükleniyor.
- **Microsoft Entra ID (Azure AD) SSO** — kurumsal tek tıkla giriş.
- **Sınav ve ödev sistemi** — admin panelinden yazılan çoktan seçmeli/çoklu/doğru-yanlış sorular; öğrenci tarafında geçme notu + deneme hakkı sınırı; metin/dosya/URL ödev teslimi + eğitmen tarafından puanlama.
- **Öğrenim yolları** — birden fazla eğitimi tek bir paket program altında sıralı/sırasız birleştirme.
- **Herkese açık sertifika doğrulama** — bir sertifika numarasıyla, giriş yapmadan gerçeklik kontrolü.
- **Takvim entegrasyonu** — canlı seanslar için tek tıkla `.ics` indirme.
- **Kapsamlı denetim izi** — 13 modelde otomatik değişiklik takibi, hem eğitmen hem admin tarafında görüntülenebilir.
- **Çift dilli (TR/EN)** ve **açık/koyu tema**, dört panelde de tutarlı.
- **Otomatik sertifika üretimi** — tüm zorunlu içerik tamamlandığında otomatik oluşup PDF olarak indirilebiliyor.
- **BM Sürdürülebilir Kalkınma Amaçları (SDG) etiketleme** — her eğitim ilgili SDG hedefleriyle etiketlenebiliyor.
- **Tekrar çalıştırılabilir, geri alınabilir gösterim verisi araç seti** (`demo:seed` / `demo:clear`).

---

## 11. Sayılarla proje (bu oturum itibarıyla)

- 4 panel, 23 veri modeli, 13 admin kaynağı (Filament resource) + 1 özel sayfa (Sistem Sağlığı)
- 260 otomatik test, hepsi geçiyor (`PAO_DISABLE=1 php vendor/bin/phpunit`)
- 110 kayıtlı route (vendor hariç)
- 4 e-posta+uygulama-içi bildirim türü, 13 modelde otomatik denetim izi
- 10 maddelik production-hardening turu tamamlandı (bkz. §12): merkezi tamamlanma motoru, rate limiting, dosya yükleme güvenliği, yarış durumu düzeltmeleri, yedekleme/geri yükleme, sertifika iptal yaşam döngüsü, hesap anonimleştirme, gözlemlenebilirlik

---

## 12. Kurumsal sertleştirme (production hardening) turu

Bir dış mimari değerlendirmenin ardından, "yeni özellik eklemek" yerine **mevcut sistemi ölçülebilir, güvenilir, yönetilebilir hale getirmeye** odaklanan 10 maddelik bir tur tamamlandı (Faz 61-70). Hepsi test edilerek, birbirini bozmadan, artımlı commit'lerle:

1. **Tamamlanma mantığı merkezi bir yerde** — `CompletionEngine`: izleme eşiği, heartbeat/oturum katılımı biriktirme, "enrollment tamamlandı mı, sertifika verilsin mi" kontrolü artık tek bir sınıfta; önceden 5 farklı controller metodunda neredeyse birebir kopyalanmıştı.
2. **Yetkilendirme denetlendi** — çoğu kaynağın zaten `Auth::user()->enrollments()->where(...)` üzerinden doğru şekilde scope'landığı görüldü; gerçek tekrar (`CoursePolicy` kuralının elle yeniden yazıldığı iki yer) birleştirildi.
3. **Queue worker + scheduler artık gerçek bir süreç** — `Procfile` (PaaS) ve `deploy/` altında Supervisor + crontab örnekleri (geleneksel VPS); mekanizma canlı ortamda 12 bekleyen işin hatasız işlenmesiyle doğrulandı.
4. **Dosya yükleme güvenliği** — materyal/canlı ders dosyası/ödev tesliminde artık gerçek içerik türü doğrulanıyor (`.pdf` diye yeniden adlandırılmış bir çalıştırılabilir dosya reddediliyor).
5. **Rate limiting** — giriş, kayıt, sertifika doğrulama, arama, heartbeat, sınav denemesi, dosya teslimi; hiçbirinde önceden bir istek sınırı yoktu.
6. **Yarış durumu ve çift gönderim düzeltmeleri** — izleme/katılım biriktirmesi artık `lockForUpdate()` ile atomik (iki sekme açık tutarak eşzamanlı istekle biriktirmeyi ikiye katlama imkânsız); sınav denemesinde 3 saniyelik çift-tıklama koruması.
7. **Yedekleme/geri yükleme** — `deploy/backup.sh` / `restore.sh`, canlı veritabanı üzerinde uçtan uca doğrulandı (yedek alındı, satır sayıları eşleşti, izole bir ortamda geri yükleme test edildi).
8. **Sertifika yaşam döngüsü** — silme yerine iptal: `revoked_at/revoked_reason/revoked_by`, iptal edilmiş sertifika indirilemez ve doğrulama sayfasında açıkça "iptal edilmiş" görünür; yeni bir admin kaynağı (Sertifikalar) eklendi.
9. **Hesap silme artık anonimleştirme** — kritik bulgu: `enrollments.user_id` cascade-delete olduğu için hesap silme, o kişinin TÜM sertifikalarını/kayıtlarını da sessizce siliyordu. Artık PII temizleniyor, geçmiş kayıtlar (sertifikalar dahil) korunuyor.
10. **Gözlemlenebilirlik** — her istek için paylaşılan bir `request_id` (loglara ve `X-Request-Id` başlığına yazılıyor) ve yeni bir admin "Sistem Sağlığı" sayfası: veritabanı/kuyruk/scheduler/e-posta/disk/hata durumunu tek ekranda gösteriyor, başarısız işleri tek tek veya toplu yeniden deneme/silme imkânı sunuyor.

Bu turdan sonra test sayısı 231'den **260'a** çıktı; hepsi geçiyor.

---

## 13. Projenin genel teması / vizyonu

Arucad Campus LMS, "eğitim tamamlandı" ibaresinin bir güven belgesi olduğu, dış SaaS araçlarına bağımlı olmadan kurum içinde çalışan, dört farklı kullanıcı tipinin ihtiyacına göre ayrı ayrı tasarlanmış ama tamamen ortak bir tasarım dilini (aynı marka-solda/sekmeler-sağda navbar düzeni, aynı renk paleti, aynı cam-efekt arayüz, aynı tema sistemi) paylaşan bir kurumsal öğrenme platformu olarak konumlanıyor.

Öncelik sırası: **önce doğruluk** (sahte tamamlama imkânsız, içerik tipine uygun şekilde), **sonra kontrol** (admin ve eğitmen her şeyi görüp yönetebiliyor, hiçbir şey için koda dokunmaya gerek kalmıyor), **sonra deneyim** (sade, tutarlı, iki dilli, dört panelde de aynı hissettiren arayüz).
