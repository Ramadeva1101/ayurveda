# Rancangan: Sistem Booking + Dashboard Admin

Status: draf v0.2 (1 Nov 2026). Target hosting: Rumahweb (cPanel), domain
lewat Cloudflare. Acuan tampilan: demo klien "Wellness House Bookings"
(claude.ai/artifact/FLzh2FCXF9QRaNgN4nwQXc).

Demo klien hanya prototipe. Datanya disimpan di browser, tamunya fiktif, dan
tidak ada email yang terkirim. Dokumen ini merancang sistem sungguhan di
belakangnya.

---

## 1. Permintaan klien

1. Ada halaman Book di website.
2. Booking masuk ke email klien **dan** otomatis tampil di dashboard.
3. Calendar dan Stays diisi manual oleh klien.
4. Tidak ada link login di website publik.
5. Tidak ada data palsu atau karangan. Semua harga, jam, dan kebijakan
   datang dari klien.

---

## 2. Arsitektur

```
Pengunjung ──► Cloudflare (DNS + proxy + Turnstile) ──► Rumahweb cPanel
                                                         │
          public_html/
          ├── (hasil build Astro: halaman statis, termasuk /book)
          ├── api/      PHP: menerima booking, cek slot, form kontak
          ├── admin/    PHP: dashboard klinik (tidak ditautkan di mana pun)
          └── ...
          di luar public_html:
          └── app/      PHP: config, koneksi DB, template email, cron
                         │
                         ▼
                      MySQL (cPanel)
```

| Bagian | Teknologi | Catatan |
|---|---|---|
| Halaman publik | Astro (tetap), build ke `dist/` | Tidak berubah, hanya tambah halaman `/book` |
| Form Book | HTML + Alpine.js (sudah dipakai) | Mengambil slot dari `api/availability.php` |
| Backend | PHP 8.1+ | Cek versi PHP di cPanel → "Select PHP Version" |
| Database | MySQL / MariaDB | Dibuat lewat cPanel, tabel di-import lewat phpMyAdmin |
| Email | PHPMailer + SMTP akun `booking@` di cPanel | Perlu SPF, DKIM, DMARC di DNS Cloudflare |
| Otomasi terjadwal | cPanel Cron Jobs → `app/cron/run.php` | Tiap 15 menit + 18:00 |
| Anti-spam | Cloudflare Turnstile | Diverifikasi di server, bukan cuma di browser |
| Login admin | PHP session + password (bcrypt) | Lihat bagian 6 |

Tidak perlu SSH. Semua dipasang lewat File Manager, MySQL Databases,
phpMyAdmin, Cron Jobs, dan Email Accounts di cPanel.

---

## 3. Alur booking

```
1. Tamu buka /book
   └─ browser panggil GET api/availability.php?date=2026-11-07&service=...
      server balas slot yang masih kosong

2. Tamu isi form, lolos Turnstile, klik "Request appointment"
   └─ POST api/booking.php

3. Server:
   a. Verifikasi Turnstile ke Cloudflare
   b. Validasi semua field (wajib, format WhatsApp, tanggal masa depan, hari buka)
   c. Kunci & cek ulang slot di dalam transaksi DB (anti dobel booking)
   d. Simpan guest (atau pakai yang lama bila WhatsApp/email sama)
   e. Simpan booking (status = requested) + jawaban cek kesehatan
   f. Masukkan 2 email ke antrean: notifikasi klinik + tanda terima tamu
   g. Balas ke browser: kode booking (mis. AWH-1060)

4. Cron (tiap 15 menit, atau langsung setelah submit) kirim email di antrean

5. Dashboard: booking muncul di Today + Bookings dengan tanda "Perlu konfirmasi"

6. Admin klik Confirm / Tolak / Usulkan jam lain
   ├─ Confirm: status confirmed, slot terkunci, invoice deposit dibuat (fase 2),
   │           email konfirmasi ke tamu
   └─ Tolak:   status cancelled, slot dibuka lagi, email ke tamu
```

### Status booking
```
requested ──► confirmed ──► checked_in ──► completed
     │             │
     └──► cancelled ◄┘          (no_show bisa diset manual)
```

Permintaan yang belum dikonfirmasi tetap "memegang" slot selama 24 jam
(bisa diatur), supaya dua tamu tidak meminta jam yang sama. Lewat 24 jam
tanpa tindakan, admin diberi peringatan. Slot tidak dilepas otomatis.

### Aturan slot (harus dikonfirmasi klien)
- Jam buka per hari (tabel `opening_hours`) dan tanggal libur (`closed_dates`).
- Jam mulai per layanan (demo: 09:00, 10:30, 13:00, 14:30, 16:00).
- Kapasitas: per slot berapa tamu, dan apakah bergantung pada terapis.
  Usulan awal yang aman: **1 booking per slot**, admin bisa membuka kapasitas
  lebih lewat Settings.

---

## 4. Email & otomasi

Semua email masuk tabel `email_queue` dulu, lalu dikirim cron. Kalau SMTP gagal,
email tidak hilang. Status dan error-nya terlihat di dashboard.

| # | Pemicu | Penerima | Fase |
|---|---|---|---|
| 1 | Booking baru masuk | Klinik (alamat dari Settings) | 1 |
| 2 | Booking baru masuk | Tamu (bila isi email) | 1 |
| 3 | Admin Confirm | Tamu | 1 |
| 4 | Admin Tolak / ubah jam | Tamu | 1 |
| 5 | Form kontak | Klinik | 1 |
| 6 | H-7 sebelum kedatangan | Tamu: checklist & pengingat | 2 |
| 7 | Tiap hari 18:00 | Klinik: kedatangan & sesi besok | 2 |
| 8 | Invoice lewat jatuh tempo | Tamu: pengingat bayar | 2 |
| 9 | H+1 setelah selesai | Tamu: take-home plan & feedback | 2 |

Setiap otomasi bisa dimatikan dari dashboard (tabel `automations`), sama
seperti demo.

Bahasa email mengikuti pilihan tamu (EN / ID). Template ditulis sekali
lalu disetujui klien. Tidak ada teks email yang dikarang otomatis.

Email ke klinik **tidak** memuat jawaban kesehatan. Isinya cukup "ada catatan
kesehatan, buka dashboard". Data kesehatan termasuk data sensitif.

WhatsApp: fase 1 tetap manual. Dashboard menampilkan tombol `wa.me` dengan
pesan siap kirim (EN/ID) yang diisi otomatis. Integrasi WhatsApp Business API
resmi (inbox gabungan seperti demo) masuk fase 3 karena butuh verifikasi Meta
dan biaya per percakapan.

---

## 5. Dashboard admin

URL: `https://ayurvedicwellnesshouse.com/admin/` (tidak ditautkan, `noindex`,
diblokir di `robots.txt`).

### Fase 1
| Halaman | Isi | Aksi |
|---|---|---|
| Today | Kartu: perlu konfirmasi, sesi hari ini, tamu menginap, email gagal | Klik menuju detail |
| Bookings | Tabel + filter status/tanggal/layanan, pencarian nama/WA/kode | Confirm, Tolak, Ubah jam, Batal, Check-in, Selesai |
| Detail booking | Data tamu, layanan, jadwal, cek kesehatan, riwayat email, catatan internal | Semua aksi status + tombol WhatsApp |
| Calendar | Tampilan hari/minggu, filter terapis | Tambah/ubah/hapus janji **manual**, blok jam (libur/istirahat) |
| Stays | Daftar per kamar/tanggal (timeline sederhana) | Tambah/ubah/hapus **manual** |
| Guests | Daftar tamu + riwayat booking | Edit data |
| Settings | Jam buka, tanggal tutup, slot, kapasitas, email notifikasi, layanan & harga, terapis, otomasi | Simpan |
| Users | Akun staf | Tambah/nonaktifkan, reset password |

### Fase 2
Invoices (buat dari booking, deposit %, tandai lunas manual, PDF), Packages
(isi & harga program bisa diedit), Emails (log + otomasi), Reports dasar.

### Fase 3
Notes klinis per sesi, WhatsApp API, akun & tampilan per terapis, dikte suara.

---

## 6. Keamanan admin (tanpa link login di website)

1. `/admin/` tidak muncul di menu, footer, sitemap, maupun robots-allow.
   Halaman login hanya ada di URL itu.
2. Login: email + password, disimpan dengan `password_hash()` (bcrypt).
   Session cookie `HttpOnly`, `Secure`, `SameSite=Strict`.
3. Batas percobaan login: 5 kali gagal → kunci 15 menit per IP + per akun.
4. Semua form admin memakai token CSRF.
5. Semua query memakai prepared statement (PDO), tidak ada SQL yang disusun dari input.
6. Lapisan tambahan (disarankan, gratis): **Cloudflare Access** di path
   `/admin/*` dengan kode OTP ke email staf. Orang luar sudah diblokir
   Cloudflare sebelum sampai ke halaman login. Login PHP tetap ada sebagai
   lapisan kedua, karena server Rumahweb masih bisa diakses langsung lewat IP.
7. File config (password DB, SMTP, secret Turnstile) diletakkan di `app/`
   **di luar** `public_html`, jadi tidak bisa dibuka dari browser.
8. Backup: cPanel Backup harian + export DB mingguan.

Keamanan ini tidak bergantung pada URL yang dirahasiakan. URL tersembunyi
hanya membuat dashboard tidak terlihat. Yang mengamankan adalah login dan
Cloudflare Access.

---

## 7. Database (MySQL)

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','staff') NOT NULL DEFAULT 'staff',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE practitioners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  role VARCHAR(100) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('experience','treatment','program') NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  duration_minutes INT NULL,          -- treatment/experience
  duration_days INT NULL,             -- program
  price_idr INT NULL,                 -- NULL = tampil "to be confirmed"
  bookable_online TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE guests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  whatsapp VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  country VARCHAR(80) NULL,
  language ENUM('en','id') NOT NULL DEFAULT 'en',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (whatsapp), INDEX (email)
);

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,   -- AWH-1060
  guest_id INT NOT NULL,
  service_id INT NOT NULL,
  start_date DATE NOT NULL,
  start_time TIME NULL,               -- NULL untuk program
  guests_count TINYINT NOT NULL DEFAULT 1,
  staying_at VARCHAR(190) NULL,
  message TEXT NULL,
  status ENUM('requested','confirmed','checked_in','completed','cancelled','no_show')
         NOT NULL DEFAULT 'requested',
  source ENUM('website','whatsapp','email','walk_in','other') NOT NULL DEFAULT 'website',
  price_idr_snapshot INT NULL,        -- harga saat booking dibuat
  confirmed_at DATETIME NULL,
  confirmed_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (guest_id) REFERENCES guests(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  INDEX (start_date, start_time), INDEX (status)
);

CREATE TABLE health_checks (
  booking_id INT PRIMARY KEY,
  answers JSON NOT NULL,              -- {"pregnant":"no","high_bp":false,...}
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE appointments (            -- isi Calendar (otomatis dari booking atau manual)
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NULL,
  practitioner_id INT NULL,
  title VARCHAR(190) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  kind ENUM('session','block') NOT NULL DEFAULT 'session',  -- block = libur/istirahat
  notes TEXT NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  FOREIGN KEY (practitioner_id) REFERENCES practitioners(id),
  INDEX (starts_at)
);

CREATE TABLE stays (                   -- manual
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT NULL,
  booking_id INT NULL,
  place VARCHAR(190) NOT NULL,        -- "Bali Silent Retreat" / lainnya
  room VARCHAR(100) NULL,
  check_in DATE NOT NULL,
  check_out DATE NOT NULL,
  status ENUM('requested','reserved','checked_in','checked_out','cancelled')
         NOT NULL DEFAULT 'reserved',
  notes TEXT NULL,
  FOREIGN KEY (guest_id) REFERENCES guests(id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

CREATE TABLE opening_hours (
  weekday TINYINT PRIMARY KEY,        -- 1=Senin ... 7=Minggu
  is_open TINYINT(1) NOT NULL,
  slot_times VARCHAR(255) NULL         -- "09:00,10:30,13:00,14:30,16:00"
);

CREATE TABLE closed_dates (
  date DATE PRIMARY KEY,
  reason VARCHAR(190) NULL
);

CREATE TABLE booking_events (          -- jejak audit: siapa ubah apa kapan
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  detail TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE email_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NULL,
  template VARCHAR(60) NOT NULL,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  send_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  INDEX (status, send_after)
);

CREATE TABLE automations (
  key_name VARCHAR(60) PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE settings (
  key_name VARCHAR(60) PRIMARY KEY,
  value TEXT NULL
);

CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NULL,
  success TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, created_at)
);
```

Fase 2 menambah: `invoices`, `invoice_items`, `payments`, `packages`,
`package_items`. Fase 3: `clinical_notes`, `messages`.

---

## 8. Struktur file

```
Proyek lokal (folder ini)
├── src/pages/book.astro            # halaman Book (statis, isi slot via API)
├── src/components/booking/         # komponen form (Alpine.js)
├── backend/                        # semua PHP, di-upload ke server
│   ├── public/                     # → public_html/
│   │   ├── api/
│   │   │   ├── availability.php    # GET slot kosong
│   │   │   ├── booking.php         # POST booking baru
│   │   │   ├── services.php        # GET layanan & harga aktif
│   │   │   └── contact.php         # POST form kontak
│   │   └── admin/
│   │       ├── index.php           # Today
│   │       ├── login.php, logout.php
│   │       ├── bookings.php, booking.php
│   │       ├── calendar.php, stays.php, guests.php
│   │       ├── settings.php, users.php
│   │       └── assets/             # css/js dashboard
│   └── app/                        # → di luar public_html (/home/krex2584/awh-app)
│       ├── config.php              # rahasia: DB, SMTP, Turnstile (tidak di-commit)
│       ├── config.example.php
│       ├── bootstrap.php           # koneksi PDO, session, helper
│       ├── lib/                    # Auth, Csrf, Availability, Booking, Mailer, Validator
│       ├── templates/email/        # en/ & id/
│       ├── cron/run.php            # kirim antrean + otomasi terjadwal
│       ├── migrations/001_init.sql
│       └── vendor/                 # PHPMailer (di-upload, tanpa Composer di server)
└── dist/                           # hasil build Astro → public_html/
```

Halaman publik yang sudah ada tetap sama. Yang berubah hanya: tambah
`book.astro`, tombol "Book" di navbar, dan form kontak diarahkan ke
`api/contact.php` (endpoint-nya sekarang masih placeholder).

Harga dan daftar layanan di halaman publik untuk sementara tetap dari Markdown.
Fase 2: halaman Book membaca harga dari `api/services.php` supaya satu sumber
dengan dashboard.

---

## 9. Langkah deploy (cPanel, tanpa SSH)

1. cPanel → Select PHP Version: pastikan 8.1 atau lebih baru.
2. MySQL Databases: buat database + user, beri ALL PRIVILEGES.
3. phpMyAdmin → Import `migrations/001_init.sql`.
4. File Manager: upload `awh-app.zip` ke `/home/krex2584/` lalu Extract (di luar `public_html`),
   salin `config.example.php` → `config.php`, isi kredensial.
5. Upload `dist/` + `backend/public/` ke `public_html/`.
6. Email Accounts: buat `booking@ayurvedicwellnesshouse.com`.
7. Cloudflare DNS: tambah SPF, DKIM (dari cPanel → Email Deliverability), DMARC.
8. Cron Jobs: `*/15 * * * * php /home/krex2584/awh-app/cron/run.php`
   dan `0 18 * * * php /home/krex2584/awh-app/cron/run.php daily`.
9. Cloudflare Turnstile: buat site key, isi di `config.php` dan halaman Book.
10. Buat akun admin pertama lewat skrip satu kali, lalu hapus skripnya.
11. (Disarankan) Cloudflare Zero Trust → Access → aplikasi untuk `/admin/*`.
12. Uji: booking palsu dari HP, cek email masuk, cek dashboard, Confirm,
    cek email konfirmasi. Hapus data uji.

Update selanjutnya: halaman publik tetap seperti sekarang (build lalu upload
`dist/`). PHP hanya diupload ulang kalau ada perubahan.

Catatan: jangan hapus folder `api/` dan `admin/` saat mengganti isi
`public_html` dengan `dist/` yang baru.

---

## 10. Rencana kerja

| Fase | Isi | Selesai bila |
|---|---|---|
| 1a | Skema DB, auth admin, Settings (jam buka, slot, layanan, email) | Admin bisa login dan mengisi aturan |
| 1b | API availability + booking, halaman `/book`, email ke klinik & tamu | Booking uji masuk email dan dashboard |
| 1c | Bookings + detail + aksi status, Calendar & Stays manual, Today | Klinik bisa jalan sehari-hari |
| 2 | Invoices, Packages, otomasi H-7 / 18:00 / overdue / H+1, Reports dasar | Disetujui klien |
| 3 | WhatsApp API, catatan klinis, akun per terapis | Sesuai kebutuhan |

---

## 11. Yang harus dikonfirmasi klien (jangan dikarang)

1. Harga final semua layanan yang bisa dibooking online. Harga herbal di demo
   masih bertuliskan "placeholder".
2. Layanan mana yang boleh dibooking online. Demo menyebut "17 terapi",
   situs sekarang punya 19 file treatment.
3. Hari & jam buka, jam mulai slot, kapasitas per slot.
4. Kalimat "kami konfirmasi lewat WhatsApp biasanya dalam satu jam": apakah
   sanggup?
5. Email penerima notifikasi booking, dan siapa saja yang boleh masuk dashboard.
6. Pertanyaan cek kesehatan: disetujui Dr. Komang?
7. Kebijakan pembatalan dan deposit (demo: pajak & servis 21%, deposit 30%,
   jatuh tempo 3 hari).
8. Rekening bank untuk invoice (fase 2).
9. Bali Silent Retreat: perlu email otomatis ke mereka, atau cukup dicatat di Stays?
10. Teks email EN & ID: disetujui klien sebelum aktif.
11. Paket Rumahweb: versi PHP, batas email per jam (penting untuk antrean email).
12. Tampilan: tetap desain situs sekarang, atau ganti ke salah satu desain demo?

---

## 12. Menjalankan di komputer sendiri (uji lokal)

Butuh Laragon (PHP 8.1 + MySQL 8 sudah terpasang di `C:\laragon`).

1. Jalankan `backend\dev\start.cmd` (dobel klik). Dua jendela terbuka: MySQL dan PHP backend (port 8099).
2. Di folder proyek: `npm run dev`, lalu buka http://localhost:4321.
   Astro meneruskan `/api` dan `/admin` ke PHP (lihat `astro.config.mjs`).
3. Pertama kali: buka http://localhost:4321/admin/setup.php, isi token dari
   `backend/app/config.php` (`setup_token`), buat akun owner.
4. Isi Settings (email notifikasi, jam buka), Services, lalu nyalakan booking.
5. Coba booking di http://localhost:4321/book. Email tidak benar-benar terkirim
   di mode lokal; isinya bisa dilihat di Admin → Emails.

Database lokal: `awh_local`. Untuk mengulang dari nol, drop database itu lalu
import ulang `backend/app/migrations/001_init.sql`.

---

## 13. Checklist sebelum go-live (cPanel)

Hasil uji instalasi bersih (database kosong dari `001_init.sql`), 61/61 lulus:
setup akun developer, login/lockout/CSRF, Settings, Services, diskon paket,
booking online (slot penuh ditolak, honeypot, tanpa Turnstile di production
ditolak), email terkirim (data kesehatan tidak ikut email), confirm/pindah/
selesai, calendar + blok jam menutup slot online, stays, guests, role Admin
tidak bisa buka Users atau kirim email uji, cron.

**Kebutuhan server**
- PHP 8.0+ dengan ekstensi `pdo_mysql`, `curl`, `mbstring`, `openssl`.
- MySQL 5.7.8+ atau MariaDB 10.2.7+ (kolom JSON).
- Situs di-proxy Cloudflare (awan oranye). IP pengunjung dari header
  Cloudflare hanya dipercaya jika request memang datang dari IP Cloudflare.

**Isi `awh-app/config.php` di server**
- `env` = `production`, `cookie_secure` = `true`.
- `db`: nama database, user, password dari cPanel.
- `turnstile.site_key` + `turnstile.secret`: **wajib**. Tanpa ini booking
  online sengaja ditolak (503).
- `mail`: `driver` = `smtp`, host/port dari Connect Devices akun `booking@`,
  password akun email itu.
- `setup_token`: isi sementara, buat akun developer, lalu kosongkan lagi.

**Upload**
- `public_html.zip` ke `public_html/ayurvedicwellnesshouse.com/` (addon domain), lalu Extract.
- `awh-app.zip` ke `/home/krex2584/` (jadi `awh-app/`, di luar `public_html`), termasuk `vendor/PHPMailer`. Folder `app/` yang sudah ada di sana milik situs lain: jangan disentuh.
- Saat update halaman publik berikutnya: ganti isi `public_html` dengan `dist/`
  baru **tanpa menghapus** `api/` dan `admin/`.

**Setelah upload**
1. phpMyAdmin → Import `app/migrations/001_init.sql`.
2. `/admin/setup.php` → buat akun developer → kosongkan `setup_token`.
3. Settings: email notifikasi, jam buka, slot, diskon paket (30%, 1–30 Nov).
4. Services: isi layanan + harga (atau import `backend/dev/seed-from-website.sql`
   bagian INSERT/UPDATE services saja), lalu nyalakan booking.
5. Users: buat akun Admin untuk klien.
6. Emails → Send test email ke Gmail sendiri; cek tidak masuk spam.
7. DNS Cloudflare: SPF, DKIM (dari Email Deliverability), DMARC.
8. Cron Jobs: `*/15 * * * * php /home/krex2584/awh-app/cron/run.php`.
9. Booking percobaan dari HP → cek email klinik + tamu → Confirm di dashboard
   → hapus data percobaan.
