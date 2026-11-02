# Deploy ke cPanel (Rumahweb)

Paket ini berisi dua ZIP:

| File | Upload ke | Isi |
|---|---|---|
| `public_html.zip` | `/home/krex2584/public_html/ayurvedicwellnesshouse.com/` | Website (hasil build Astro), `api/`, `admin/` |
| `awh-app.zip` | `/home/krex2584/` (jadi `/home/krex2584/awh-app/`) | Kode PHP, config, migrasi SQL, cron, PHPMailer. **Di luar public_html** |

Akun cPanel: `krex2584`. Domain ini addon domain, jadi folder websitenya
`public_html/ayurvedicwellnesshouse.com/` (bukan `public_html/` langsung).

**Jangan sentuh folder `/home/krex2584/app/` yang sudah ada**: itu milik situs
lain. Backend kita sengaja bernama `awh-app` supaya tidak bentrok.

Tidak perlu SSH. Semua lewat menu cPanel.

---

## Pertama kali (instal)

### 1. Cek PHP
cPanel → **Select PHP Version** → pilih **8.1** atau lebih baru.
Pastikan ekstensi ini dicentang: `pdo_mysql`, `curl`, `mbstring`, `openssl`.

### 2. Backup dulu
File Manager → `public_html/ayurvedicwellnesshouse.com` → pilih semua →
**Compress** → simpan ZIP-nya.
(Supaya bisa kembali ke situs lama kalau ada masalah.)

### 3. Upload `awh-app.zip`
File Manager → buka folder home (`/home/krex2584`, **bukan** public_html) →
Upload `awh-app.zip` → klik kanan → **Extract**. Hasilnya folder
`/home/krex2584/awh-app/`. Setelah itu hapus `awh-app.zip`.

### 4. Database
1. cPanel → **MySQL Databases** → buat database (misal `krex2584_awh`) dan user
   (misal `krex2584_awh`) → **Add User To Database** → centang **ALL PRIVILEGES**.
2. cPanel → **phpMyAdmin** → pilih database itu → **Import** →
   `awh-app/migrations/001_init.sql` (download dulu dari File Manager, atau ambil
   dari folder `backend/app/migrations` di proyek).
3. (Opsional) Import juga `002_services_from_website.sql` untuk mengisi daftar
   layanan dari website.

### 5. Config
File Manager → `/home/krex2584/awh-app/` → salin `config.example.php` jadi
`config.php` → **Edit**:

- `env` → `'production'` (biarkan)
- `db` → `name`, `user`, `pass` dari langkah 4 (host tetap `localhost`)
- `setup_token` → isi teks acak panjang (sementara, untuk langkah 8)
- `turnstile` → `site_key` dan `secret` dari Cloudflare → Turnstile
  (**wajib**: tanpa ini booking online ditolak)
- `mail` → lihat cPanel → Email Accounts → `booking@…` → **Connect Devices**:
  - `smtp_host` = Outgoing Server (biasanya `mail.ayurvedicwellnesshouse.com`)
  - `smtp_port` = 465, `smtp_secure` = `'ssl'`
  - `smtp_pass` = password akun email `booking@`

### 6. Upload `public_html.zip`
File Manager → `public_html/ayurvedicwellnesshouse.com` → Upload `public_html.zip` → **Extract**
(timpa file lama) → hapus `public_html.zip`.

File `.htaccess` lama di folder itu (kalau ada) **jangan dihapus**.

### 7. Cek cepat
- `https://ayurvedicwellnesshouse.com/` → website tampil.
- `https://ayurvedicwellnesshouse.com/api/offer.php` → tampil `{"percent":null}`.
  Kalau muncul "Backend folder awh-app not found" atau "Missing awh-app/config.php",
  cek lokasi folder `awh-app` dan file `config.php` di dalamnya.

### 8. Akun developer
Buka `https://ayurvedicwellnesshouse.com/admin/setup.php` → isi setup token,
nama, email, password (min. 12 karakter) → **Create**.
Lalu edit `config.php` lagi: kosongkan `setup_token` (`''`).

### 9. Isi dashboard (`/admin/`)
1. **Settings**: email notifikasi (`booking@ayurvedicwellnesshouse.com`),
   jam buka + jam slot, kapasitas, diskon paket (30%, 1–30 Nov 2026).
2. **Services**: cek harga, durasi, centang *Bookable online*.
3. **Practitioners**: isi nama terapis.
4. **Settings** → *Online booking* → **Turn on**.
5. **Users**: buat akun **Admin** untuk klien (password diberikan langsung,
   bukan lewat email).

### 10. Email
1. Dashboard → **Emails** → *Send a test email* ke Gmail sendiri.
2. cPanel → **Email Deliverability** → salin record **SPF** dan **DKIM** ke
   DNS Cloudflare (tipe TXT, **DNS only** / awan abu-abu). Tambah **DMARC**:
   nama `_dmarc`, isi `v=DMARC1; p=none; rua=mailto:booking@ayurvedicwellnesshouse.com`.
3. Kirim test lagi, pastikan masuk inbox, bukan spam.

### 11. Cron
cPanel → **Cron Jobs** → Add:
- Common setting: *Every 15 minutes* (`*/15 * * * *`)
- Command: `php /home/krex2584/awh-app/cron/run.php`

(Mengirim ulang email yang gagal dan membersihkan data lama.)

### 12. Uji akhir
Booking percobaan dari HP di `/book` → cek email masuk ke `booking@` dan ke
email tamu → buka dashboard → **Confirm** → cek email konfirmasi →
**Cancel** booking percobaan.

---

## Update berikutnya

- **Website saja** (teks/foto halaman): upload `public_html.zip` baru →
  Extract (timpa). Folder `api/` dan `admin/` ikut di dalam ZIP, jadi aman.
- **Kode PHP** (`awh-app.zip`): Extract di `/home/krex2584/` (timpa).
  `config.php` **tidak ada** di ZIP, jadi config server tidak tertimpa.
- Jangan import ulang `001_init.sql` di database yang sudah berisi data:
  itu untuk instal pertama saja.
- Kalau `002_services_from_website.sql` sudah di-import sebelum ganti nama
  "Nusantara" → "Javanese Energy Healing": import `003_javanese_names.sql`
  sekali di phpMyAdmin (aman dijalankan dua kali).
