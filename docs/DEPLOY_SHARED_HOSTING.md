# Panduan Deploy ke Shared Hosting (cPanel / Hostinger / Niagahoster)

Project ini adalah **PHP + MySQL** (bukan framework). Vercel **tidak bisa** menjalankannya
(karena Vercel = static + serverless tanpa PHP/MySQL). Gunakan shared hosting dengan
**PHP 8.0+** dan **MySQL/MariaDB**.

---

## Ringkasan Masalah di Vercel

| Gejala | Penyebab |
|---|---|
| Produk "Memuat produk..." terus | `api/get_products.php` tidak dieksekusi (disajikan sebagai teks) |
| Login/register tidak bisa | `api/auth.php` tidak jalan + butuh MySQL |
| Chat, checkout, review, voucher error | Semua endpoint di folder `api/` butuh PHP + MySQL |
| Upload produk/bukti bayar gagal | Filesystem Vercel read-only |

---

## Langkah 1 — Buat Database di Hosting

1. Login **cPanel** (atau panel Hostinger hPanel).
2. Buka **MySQL Databases**.
3. Buat database, misal: `u1234567_manis` (nama di shared hosting biasanya otomatis di-prefix username).
4. Buat user database + password, lalu **Add User to Database** → centang **ALL PRIVILEGES**.
5. Catat: `DB_HOST` (biasanya `localhost`), `DB_NAME`, `DB_USER`, `DB_PASSWORD`.

## Langkah 2 — Import SQL

1. Buka **phpMyAdmin**.
2. Pilih database hasil langkah 1.
3. Tab **Import** → pilih `database/manis_legit_db.sql` → **Go**.
4. Selesai. File SQL lain (`add_*.sql`, `configure_*.sql`) hanya riwayat migrasi —
   tabelnya sudah termasuk di `manis_legit_db.sql`, **tidak perlu diimport lagi**.

> Akun setelah import:
> - Admin: `admin@riracakes.id`
> - Customer: `customer@test.com`
>
> Password hash di dump = `password`. Untuk mengganti ke `admin123`/`customer123`,
> jalankan `setup_password.php` sekali (Langkah 4) lalu **hapus file itu**.

## Langkah 3 — Upload File Project

Upload **semua isi folder project** (kecuali `.git/`) ke `public_html/` (atau subfolder).
Gunakan **File Manager** (zip → upload → extract) atau **FTP**.

Wajib ikut di-upload:
- `config/`, `api/`, `admin/`, `css/`, `js/`, `uploads/`, `vendor/`
- `index.html`, `login.html`, `orders.html`, `chat.html`, `custom-cake.html`,
  `forgot-password.html`, `setup_password.php`
- `.htaccess`, `.env.example`

Catatan `vendor/` (untuk PHPMailer, dipakai email reset password):
- **Cara A:** upload folder `vendor/` yang sudah ada di lokal (hasil `composer install`).
- **Cara B:** jika hosting punya terminal/SSH → jalankan `composer install` di root project.

## Langkah 4 — Buat File `.env`

1. Di File Manager, **duplikat** `.env.example` menjadi `.env` (rename).
2. Isi kredensial database hasil Langkah 1:

```env
DB_HOST=localhost
DB_NAME=u1234567_manis
DB_USER=u1234567_manis
DB_PASSWORD=********
```

3. (Opsional) isi `SMTP_*` agar email reset password jalan — lihat `docs/EMAIL_SETUP.md`.
4. (Opsional) isi `WHATSAPP_*` untuk OTP WA — lihat `docs/INTERNAL_CHAT_SETUP.md`.

> Jangan commit `.env` ke Git (sudah masuk `.gitignore`).

## Langkah 5 — Set Permission Folder Upload

Agar upload produk, bukti bayar, dan foto chat bisa ditulis, set permission:

| Folder | Permission |
|---|---|
| `uploads/` | `755` |
| `uploads/products/` | `755` (atau `775` jika tetap gagal) |
| `uploads/payment-proofs/` | `755` (atau `775`) |
| `uploads/chat-images/` | `755` (atau `775`) |

Di File Manager: klik kanan folder → **Change Permissions**.

## Langkah 6 — Setup Password Admin (sekali saja)

1. Buka `https://domainanda.com/setup_password.php`.
2. Setelah muncul sukses, **segera hapus file `setup_password.php`** dari server
   (file ini menyetel password default dan tidak boleh tetap ada di produksi).

## Langkah 7 — Verifikasi

Buka situs dan cek checklist ini:

- [ ] `https://domain.com/index.html` → produk tampil (bukan "Memuat produk...")
- [ ] Register + login customer jalan
- [ ] Tambah ke keranjang → checkout → kode pesanan muncul
- [ ] Login admin (`/admin/index.html`) → pesanan & produk terlihat
- [ ] Upload produk di admin → gambar muncul
- [ ] Upload bukti transfer → status pesanan berubah
- [ ] Chat customer ↔ admin jalan
- [ ] Lupa password → token/reset jalan (butuh SMTP atau WA aktif)

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| API balik 404 | Pastikan file di `public_html`, bukan subfolder tersembunyi; cek `.htaccess` ter-upload |
| "Koneksi database gagal" | Cocokkan `.env` dengan user/database di cPanel; pastikan user di-**Add to Database** |
| Gambar produk tidak bisa diupload | Cek permission folder `uploads/*` (Langkah 5) |
| Email reset password tidak masuk | `vendor/` belum terisi / SMTP belum diisi di `.env` |
| Error 500 | Cek `error_log` di File Manager (biasanya di `public_html/`) |
| Halaman `config/` bisa dibuka | `.htaccess` tidak terbaca — aktifkan `AllowOverride All` atau upload ulang `.htaccess` |

## Setelah Stabil

- **Delete deployment Vercel** (atau biarkan, tidak berpengaruh) — URL Vercel tidak
  akan pernah menjalankan modul backend.
- Update `API_BASE` di `js/script.js` & `js/auth.js` tidak perlu — path sudah relatif (`api/...`).
