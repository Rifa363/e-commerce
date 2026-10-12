# Panduan Deploy ke Hosting Domainesia (cPanel)

Prasyarat: hosting + domain aktif di [domainesia.com](https://www.domainesia.com),
project sudah di GitHub (`Rifa363/e-commerce`), PHP 8.0+ & MySQL tersedia.

---

## 0. Login & Akses cPanel

1. Login ke **Member Area** Domainesia: https://members.domainesia.net
2. Menu **Layanan Saya** → klik **Manage** pada hosting Anda.
3. Klik tombol **Login to cPanel** (biasanya di pojok kanan / tab "Informasi Akun").
4. Catat info penting yang tampil: **Server Nameserver** (mis. `ns1.domainesia.net`,
   `ns2.domainesia.net`) dan **Username** hosting Anda (untuk prefix database).

> Jika domain dibeli terpisah/lama, pastikan nameserver domain sudah diarahkan ke
> nameserver hosting di Member Area → **Domain** → **Manage** → ubah Nameserver.
> Perubahan DNS butuh 1×24 jam (Domainesia lokal biasanya cepat).

---

## 1. Set PHP 8.0+

Di cPanel cari **"Select PHP Version"** (atau *MultiPHP Manager*):

1. Buka **Select PHP Version**.
2. Pilih **PHP Version 8.1** (atau lebih baru yang tersedia) → **Set as current** / **Apply**.
3. Tab **Extensions**, pastikan tercentang:
   - `pdo_mysql` (wajib — project pakai PDO)
   - `mbstring`, `fileinfo`, `json` (biasanya sudah default)

---

## 2. Buat Database + User

Di cPanel → section **Databases** → klik **MySQL Databases** (atau **Database Wizard**):

1. **Create New Database**: isi nama, misal `manis` → **Create Database**
   (hasilnya jadi `usernamecpanel_manis` — **catat nama lengkapnya**).
2. Scroll ke **MySQL Users** → **Add New User**:
   - Username (misal `manis`) → hasil `usernamecpanel_manis`
   - Password kuat → **catat password-nya**
3. Scroll ke **Add User To Database** → pilih user & database → **Add**
   → centang **ALL PRIVILEGES** → **Make Changes**.

Catat 4 nilai ini untuk `.env`:

| Variabel | Nilai |
|---|---|
| `DB_HOST` | `localhost` |
| `DB_NAME` | `usernamecpanel_manis` (prefix otomatis!) |
| `DB_USER` | `usernamecpanel_manis` |
| `DB_PASSWORD` | password yang tadi dibuat |

---

## 3. Import Database

1. cPanel → **phpMyAdmin**.
2. Klik database Anda di sidebar kiri.
3. Tab **Import** → **Choose File** → pilih `database/manis_legit_db.sql` dari komputer.
   Pastikan **Format: SQL** → klik **Go**.
4. Tunggu muncul pesan sukses. Cek: tabel `users`, `products`, `orders`, `chat_messages`
   dll harus muncul di sidebar. **Cukup satu file ini** — file `add_*.sql` tidak perlu.

---

## 4. Upload Project

### 4a. Siapkan ZIP di komputer lokal

Di folder project (mis. `C:\xampp\htdocs\e-commerce`), buat zip **isi folder**
(bukan folder induknya), dengan isi:

- Ikut: `admin/ api/ config/ css/ database/ docs/ js/ uploads/ vendor/`
  dan semua file `.html`, `.htaccess`, `.env.example`, `composer.json`, `composer.lock`
- **Keluarkan**: `.git/`, `.env` (kalau ada), `.vscode/`

> Folder `vendor/` wajib ikut (untuk PHPMailer). Kalau belum ada, jalankan
> `composer install` dulu di folder project lokal.

### 4b. Upload & Extract

1. cPanel → **File Manager** → masuk `public_html`.
2. **Upload** zip-nya → tunggu selesai → kembali ke `public_html`.
3. Klik kanan zip → **Extract** → pastikan target `public_html` → **Extract Files**.
4. Hapus zip setelah extract.
5. Pastikan isi `public_html` = langsung file `index.html`, `api/`, `config/`, dll
   (bukan terbungkus satu folder lagi — kalau terbungkus, pindahkan isinya ke atas).

### Alternatif: deploy dari GitHub

Jika cPanel Anda punya menu **Git Version Control**, bisa clone langsung dari
`https://github.com/Rifa363/e-commerce.git` ke `public_html`, lalu jalankan
`composer install` via **Terminal** cPanel. Cara zip di atas tetap paling simpel.

---

## 5. Buat File `.env`

1. Di File Manager `public_html`, aktifkan **Settings → Show Hidden Files**.
2. Klik kanan `.env.example` → **Rename** → `.env`.
3. Klik kanan `.env` → **Edit** (encoding UTF-8) → isi:

```env
DB_HOST=localhost
DB_NAME=usernamecpanel_manis
DB_USER=usernamecpanel_manis
DB_PASSWORD=password-anda
```

4. (Opsional) isi `SMTP_*` untuk email reset password — lihat `docs/EMAIL_SETUP.md`.
5. **Save**.

---

## 6. Permission Folder Upload

Di `public_html/uploads/`, klik kanan tiap folder (`products`,
`payment-proofs`, `chat-images`, `qris`) → **Change Permissions** → **755**
(jika upload produk tetap gagal, naikkan ke **775**).

---

## 7. Setup Password Admin (sekali saja)

1. Buka `https://domainanda.com/setup_password.php`.
2. Setelah sukses → **hapus file `setup_password.php`** dari `public_html` via File Manager.
3. Login `https://domainanda.com/admin/index.html` → email `admin@riracakes.id`.

---

## 8. Aktifkan SSL (HTTPS)

1. cPanel → **SSL/TLS Status** → pilih domain → **Run AutoSSL**.
   (Domainesia menyediakan SSL gratis Let's Encrypt; kadang sudah otomatis aktif.)
2. Tunggu sampai status **Valid** (beberapa menit).
3. Edit `.htaccess` di `public_html` → **uncomment** 2 baris terakhir:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

4. Akses `https://domainanda.com` → harus redirect otomatis ke HTTPS.

---

## 9. Verifikasi Akhir

- [ ] `https://domain.com/index.html` → daftar produk tampil
- [ ] Register + login customer
- [ ] Tambah keranjang → checkout → dapat kode pesanan
- [ ] Login admin → lihat pesanan masuk
- [ ] Upload produk dari admin → gambar tampil
- [ ] Upload bukti transfer → status pesanan berubah
- [ ] Chat customer ↔ admin
- [ ] `setup_password.php` sudah dihapus

---

## Troubleshooting Khusus Domainesia

| Masalah | Solusi |
|---|---|
| "Koneksi database gagal" | Nama DB/user **wajib pakai prefix username cPanel** (bukan nama polos). Cek di cPanel → MySQL Databases |
| Error 500 | Cek `public_html/error_log` via File Manager; pastikan PHP 8.0+ aktif (langkah 1) |
| API 404 / source code tampil | File ter-upload di folder salah — pastikan di root `public_html`, bukan subfolder |
| Upload gambar gagal | Permission `uploads/*` belum 755/775 |
| Domain belum buka situs | Nameserver belum cocok / DNS belum propage; cek di Member Area → Domain |
| Butuh bantuan lebih lanjut | Ticket ke Domainesia: Member Area → **Support** → buat ticket, sebut "shared hosting cPanel + PHP app" |

Panduan umum project ini (bukan spesifik Domainesia): `docs/DEPLOY_SHARED_HOSTING.md`.
