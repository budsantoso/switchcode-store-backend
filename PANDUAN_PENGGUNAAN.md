# 📖 Panduan Penggunaan & Dokumentasi Sistem SwitchCode Store
Platform Toko Online Mandiri: **https://store.switchcode.id**  
API Endpoint: **https://api-store.switchcode.id**

---

## 🔑 1. Informasi Akun Demo & Akses Cepat

Portal Login & Registrasi Terpadu: 👉 **https://store.switchcode.id/login/**

| Role | Email | Password | Hak Akses Utama |
| :--- | :--- | :--- | :--- |
| **Owner** *(Puncak)* | `owner@switchcode.id` | `OwnerSwitchCode2026!` | Kelola Akun Admin, Akses Seluruh Fitur Toko, Audit Log |
| **Admin** | `admin@switchcode.id` | `AdminSwitchCode2026!` | Tracking Pesanan, Katalog Produk (Multi-Kategori), Etalase, Topping |
| **Customer (Demo)**| `customer@switchcode.id` | `Customer2026!` | Jelajah Katalog, Filter, Keranjang, Kustomisasi Topping, Checkout |

> *Catatan Pengujian Registrasi Customer Baru*:  
> Pengunjung dapat langsung mendaftar di tab **Daftar (Register)** dengan menyelesaikan verifikasi **Captcha Matematika** dan memasukkan **Kode OTP 6 Digit** yang tampil pada panel demo.

---

## 👑 2. Panduan Khusus Owner (Pemilik Toko)
URL Dashboard: **https://store.switchcode.id/owner/**

Role **Owner** memegang otoritas tertinggi pada ekosistem toko SwitchCode Store:
1. **Manajemen Admin Toko**:
   - Menambah akun Admin baru (`Nama Lengkap`, `Email`, dan `Password`).
   - Menghapus akun Admin yang sudah tidak aktif atau dicabut wewenangnya.
2. **Pintas Akses Operasional**:
   - Tombol cepat ke modul **Tracking Pesanan Customer**.
   - Tombol cepat ke manajemen **Katalog Produk & Kategori**.
   - Tombol cepat ke pengelolaan **Etalase Toko**.
3. **Audit Akun**:
   - Memantau siapa saja staf yang memiliki akses ke back-office toko.

---

## 🛠️ 3. Panduan Khusus Admin (Operasional Toko)
URL Dashboard: **https://store.switchcode.id/admin/**

Sidebar Admin telah dipisahkan menjadi 4 modul mandiri:

### A. Tracking Pesanan Customer
- Memantau setiap transaksi yang masuk (nomor order `ORD-YYYYMMDD...`, nama pemesan, email, nomor telepon, dan catatan tambahan).
- Memperbarui status pesanan: `Pending` ➔ `Paid` ➔ `Completed` / `Cancelled`.
- Dilengkapi integrasi otomatis webhook Midtrans (`settlement`, `capture`, `deny`).

### B. List Produk & Tambah/Edit Produk
- **Multi-Kategori**: Satu produk dapat diklasifikasikan ke beberapa kategori sekaligus via checkbox (contoh: *Website Development* sekaligus *API & Backend*).
- **Edit Produk**: Klik tombol `✏️ Edit Produk` pada list produk untuk mengisi kembali form dan mengupdate data ke server.
- **Mockup / Gambar Produk**: Input URL thumbnail untuk ditampilkan di kartu belanja dan slider carousel detail.
- **🍦 Fitur Topping & Add-on Produk**:
  - Tambahkan opsi kustomisasi per produk (contoh: *Setup Domain & SSL*, *WhatsApp Bot*, *Garansi 1 Tahun*).
  - Tentukan harga tambahan (+Rp).
  - Opsi centang **Topping Wajib (Required)** jika add-on tersebut merupakan paket standar yang tidak bisa dilepas.

### C. Manajemen Kategori Produk
- Mengelola taksonomi teknis software (contoh: *Website Development*, *SaaS & ERP*, *Payment Adapter*).
- Menampilkan penghitung dynamic berapa banyak produk yang terkait pada kategori tersebut.

### D. Manajemen Etalase Toko (Showcase)
- Mengelompokkan produk display kurasi (contoh: *🔥 Paling Laris (Best Seller)*, *⚡ Promo Kilat SwitchCode*).
- **Multi-Produk per Etalase**: 1 etalase dapat menampung banyak produk sekaligus melalui seleksi checkbox.

---

## 🛍️ 4. Panduan Khusus User / Customer (Pembeli)
URL Marketplace: **https://store.switchcode.id/**

1. **Pencarian Cepat & Filter ala Shopee/Tokopedia**:
   - Ketik kata kunci pada **Search Bar** utama (otomatis mencari berdasarkan nama, ringkasan, atau deskripsi).
   - Filter cepat berdasarkan **Kategori** atau tab highlight **Etalase Promo**.
   - Urutkan harga produk: *Terbaru*, *Harga Terendah*, atau *Harga Tertinggi*.
2. **Detail Produk & Interactive Carousel**:
   - Klik kartu produk untuk membuka halaman detail: `/product/?slug=...`.
   - Geser foto screenshot/mockup melalui **Carousel Gallery** (tombol navigasi `<` / `>` atau klik thumbnail bar).
3. **Kustomisasi Topping / Add-on**:
   - Di halaman detail, centang add-on yang diinginkan.
   - Total harga akan terhitung secara otomatis dan transparan secara *real-time*.
4. **Alur Checkout Terproteksi**:
   - Klik tombol **"Beli Sekarang"** atau **"Lanjut ke Pembayaran"** dari keranjang belanja.
   - Pengunjung yang belum login otomatis diarahkan ke portal login/register dengan proteksi **Captcha + OTP**.
   - Setelah masuk, sistem langsung membuka formulir checkout terisi otomatis dan menerbitkan nomor pesanan resmi serta tautan pembayaran Midtrans Snap.

---

## 🔒 5. Ringkasan Fitur Keamanan Terpasang
- **Anti php2shell**: Validasi MIME type ketat (`jpeg, png, jpg, webp`), random hash storage, dan path traversal blocker (HTTP 403).
- **Anti Race Condition**: Database row locking (`lockForUpdate`) pada verifikasi OTP dan settlement webhook pembayaran.
- **Anti Timing Attack**: Verifikasi string sensitif (OTP & Signature Midtrans) menggunakan `hash_equals()`.
- **Anti Inject Burst & Spam**: Rate limiting bertingkat (Maks 3 OTP / 5 menit, 5 Register / jam, 5 Login / menit, 10 Checkout / menit).
