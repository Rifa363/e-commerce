# Chat internal

Chat internal memerlukan tabel `chat_conversations` dan `chat_messages`.

- Instalasi baru: tabel percakapan, pesan, dan kolom lampiran sudah termasuk dalam `database/manis_legit_db.sql`.
- Instalasi yang sudah berjalan tanpa chat: pilih database `manis_legit_db`, lalu jalankan `database/add_internal_chat.sql` melalui phpMyAdmin atau klien MySQL.
- Instalasi chat yang sudah berjalan: jalankan `database/add_chat_photo_attachments.sql` untuk menambahkan dukungan lampiran foto.
- Instalasi chat dengan lampiran foto: jalankan `database/add_chat_unread_counts.sql` untuk mengaktifkan penghitung pesan belum dibaca.

Customer dapat membuka chat dari navigasi toko atau halaman riwayat transaksi. Customer dan admin dapat mengirim teks dan/atau foto JPG, PNG, atau WEBP maksimal 5 MB. Foto hanya disajikan melalui endpoint chat yang memeriksa akses percakapan. Jumlah pesan masuk yang belum dibaca tampil pada tautan Chat di navigasi customer dan menu admin, dan berkurang saat percakapan dibuka. Checkout, pre-order custom cake, dan pengiriman ulasan otomatis membuat percakapan (jika belum ada) serta menambahkan konteks terkait sebagai pesan customer. Admin membalas melalui menu **Chat Customer** di panel admin. Chat mengambil pesan baru secara berkala selama halaman terbuka; pemberitahuan email tetap aktif.
