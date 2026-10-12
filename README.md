# 📦 InventoryPro - Inventory Management System (PHP Native PDO)

Aplikasi web sistem manajemen inventaris barang dan mutasi stok modern yang dibangun menggunakan **PHP Native** murni dengan koneksi **PDO (PHP Data Objects)** dan basis data relasional **MySQL**. Dilengkapi dengan desain antarmuka responsif berbasis **Tailwind CSS**, sistem keamanan komprehensif, serta penerapan prinsip transaksi database ACID.

Proyek ini dirancang sebagai implementasi standar materi **Mini Project: Relational Database & Advanced PHP Native** (Session, Authentication, Role Authorization, Security, Prepared Statements, dan Database Transactions).

---

## 🚀 Fitur Utama

### 1. 🔐 Autentikasi & Otorisasi Berbasis Peran (Role-Based Access Control)
- **Multi-Role User**:
  - `admin`: Memiliki hak akses penuh ke seluruh fitur (Dashboard, CRUD Produk, Mutasi Stok, Master Kategori, dan Master Supplier).
  - `staff`: Akses operasional gudang (Dashboard, melihat katalog produk, serta mencatat dan meninjau riwayat mutasi stok).
- **Session Keamanan Tingkat Tinggi**:
  - Pengaturan cookie session aman (`HttpOnly` mencegah pencurian sesi via XSS, `SameSite=Lax` memitigasi risiko CSRF).
  - Regenerasi session ID (`session_regenerate_id(true)`) saat proses login untuk mencegah serangan *Session Fixation*.
  - Pembersihan sesi total saat logout (`session_destroy()` & penghapusan cookie sesi).
- **Password Hashing**: Menggunakan fungsi standar industri PHP `password_hash()` dan verifikasi `password_verify()` (Bcrypt).
- **Proteksi Halaman & Halaman Khusus 403 (Forbidden)**: Pengalihan otomatis bagi pengunjung yang belum login dan tampilan error 403 yang informatif jika pengguna mencoba mengakses modul di luar wewenangnya.

### 2. 📊 Dashboard Ringkasan & Laporan Agregat
- **Statistik Cepat**:
  - **Total Produk**: Jumlah seluruh varian barang dalam database.
  - **Total Nilai Inventaris**: Kalkulasi otomatis nilai aset barang (`SUM(stock * price)`).
  - **Peringatan Stok Kritis**: Penghitungan jumlah produk yang stoknya sudah di bawah atau sama dengan batas minimum (`stock <= minimum_stock`).
  - **Total Barang Masuk & Keluar**: Akumulasi kuantitas mutasi dari tabel riwayat.
- **Tabel Peringatan Restock**: Menampilkan daftar 5 produk dengan stok terendah yang membutuhkan penambahan stok segera.
- **Histori Mutasi Terbaru**: Menampilkan 5 transaksi mutasi stok terakhir beserta nama staf yang bertransaksi.

### 3. 🏷️ Master Data Produk & Stok
- **Manajemen Produk (CRUD)** *(Khusus Admin)*:
  - Penambahan produk baru dengan pengecekan keunikan kode SKU (*Stock Keeping Unit*).
  - Pencatatan mutasi awal otomatis bertipe `IN` jika produk dibuat dengan jumlah stok awal > 0.
  - Edit informasi produk (Nama, Kategori, Harga Jual, Batas Minimum Stok).
  - Penghapusan aman (*Referential Integrity*): Produk yang sudah memiliki riwayat mutasi transaksi terkunci dari penghapusan sembarangan.
- **Pencarian, Filter & Sorting Canggih**:
  - Pencarian fleksibel berdasarkan nama produk maupun SKU.
  - Filter berdasarkan kategori barang dan filter cepat khusus barang dengan stok rendah.
  - *Allowlist Sorting*: Pengurutan kolom (Nama, SKU, Harga, Stok, Tanggal) diamankan dengan allowlist untuk mencegah injeksi SQL pada klausa `ORDER BY`.
  - Paginasi data dinamis (8 item per halaman).

### 4. 🔄 Transaksi Mutasi Stok (ACID Database Transactions)
- **Pencatatan Barang Masuk (IN) & Barang Keluar (OUT)**:
  - Menggunakan transaksi database PDO murni (`beginTransaction()`, `commit()`, dan `rollBack()`).
  - **Pencegahan Race Condition**: Menggunakan teknik *pessimistic locking* (`SELECT ... FOR UPDATE`) saat membaca dan memperbarui stok produk.
  - **Validasi Integritas Bisnis**: Mencegah stok bernilai negatif pada pengeluaran barang (`OUT`). Jika kuantitas keluar melebihi stok yang tersedia, transaksi langsung dibatalkan (*roll back*) dan memunculkan notifikasi error.
  - **Jejak Audit (Audit Trail)**: Setiap mutasi menyimpan ID staf penanggung jawab, tipe mutasi, kuantitas, catatan keperluan, dan stempel waktu.
- **Riwayat Mutasi Stok**:
  - Dilengkapi fitur filter berdasarkan tipe (`IN` / `OUT`) atau spesifik per produk.
  - Paginasi data riwayat (10 data per halaman).

### 5. 📂 Master Data Kategori *(Khusus Admin)*
- Penambahan dan penghapusan kategori produk.
- Menampilkan penghitungan agregat jumlah produk yang terdaftar di masing-masing kategori (`COUNT(p.id)`).
- Perlindungan relasi integritas database: Kategori yang masih menampung produk tidak dapat dihapus (`FOREIGN KEY RESTRICT`).

### 6. 🏢 Master Data Supplier *(Khusus Admin)*
- Pencatatan data mitra pemasok (Nama Perusahaan, Kontak Person, No. Telepon, Email, dan Alamat).
- Relasi *Many-to-Many* melalui tabel perantara `product_suppliers` untuk memetakan produk dengan vendor beserta harga beli supplier.

### 7. 🛡️ Keamanan & Best Practices (Security Standards)
- **Anti SQL Injection**: 100% query parametrik menggunakan PDO Prepared Statements dengan `PDO::ATTR_EMULATE_PREPARES => false`.
- **Anti Cross-Site Scripting (XSS)**: Seluruh output yang ditampilkan ke HTML disanitasi menggunakan helper encoding `e()` (`htmlspecialchars` dengan flag `ENT_QUOTES`).
- **Anti Cross-Site Request Forgery (CSRF)**: Formulir POST diverifikasi menggunakan CSRF token acak (`random_bytes(32)`) yang disimpan di sesi dan dicek dengan `hash_equals()`.
- **Flash Notification System**: Sistem notifikasi pesan umpan balik sementara (`success`, `error`, `warning`, `info`) yang otomatis terhapus setelah ditampilkan.

---

## 📁 Struktur Direktori Proyek

```text
project php native/
│
├── config/
│   └── database.php         # Konfigurasi koneksi MySQL PDO (Singleton Pattern)
│
├── database/
│   └── schema.sql           # Skema DDL tabel MySQL & data awal (seed data)
│
├── helpers/
│   ├── auth.php             # Helper session, login, logout, RBAC, CSRF token
│   ├── escape.php           # Helper proteksi XSS e(), formatRupiah(), formatTanggal()
│   └── validation.php       # Helper flash message & validasi/sanitasi input
│
├── public/
│   ├── categories/
│   │   └── index.php        # Kelola kategori barang (Admin)
│   ├── products/
│   │   ├── create.php       # Tambah produk baru (Admin)
│   │   ├── delete.php       # Hapus produk dengan validasi histori (Admin)
│   │   ├── edit.php         # Edit informasi produk (Admin)
│   │   └── index.php        # Daftar katalog produk, search, filter, sort, paginasi
│   ├── stock/
│   │   ├── create.php       # Catat mutasi stok masuk/keluar (PDO Transaction & Lock)
│   │   └── index.php        # Histori log mutasi stok & filter
│   ├── suppliers/
│   │   └── index.php        # Kelola data master supplier/vendor (Admin)
│   ├── dashboard.php        # Halaman dashboard metrik & ringkasan stok
│   ├── login.php            # Halaman login autentikasi pengguna
│   └── logout.php           # Pemusnah sesi & pengalihan ke halaman login
│
├── views/
│   ├── header.php           # Template layout atas, navigasi dinamis, & flash alerts
│   └── footer.php           # Template layout bawah & status sistem
│
├── index.php                # Entrypoint router utama (pengalihan sesuai status login)
└── README.md                # Dokumentasi lengkap proyek
```

---

## 🛠️ Prasyarat Sistem

Sebelum menjalankan proyek, pastikan lingkungan server lokal Anda memenuhi spesifikasi berikut:

- **Web Server**: Apache / Nginx (Disarankan menggunakan paket [XAMPP](https://www.apachefriends.org/) untuk Windows).
- **Bahasa Pemrograman**: PHP versi **7.4** atau **8.0+** (Direkomendasikan PHP 8.1 / 8.2).
- **Basis Data**: MySQL versi 5.7+ atau MariaDB 10.3+.
- **Ekstensi PHP Aktif**:
  - `pdo_mysql`
  - `mbstring`
  - `openssl`
  - `session`
- **Koneksi Internet**: Diperlukan saat pertama kali memuat halaman untuk mengunduh stylesheet dari CDN (Tailwind CSS dan Google Fonts).

---

## ⚙️ Panduan Instalasi & Menjalankan Proyek

Ikuti langkah-langkah di bawah ini untuk menyiapkan proyek pada server lokal:

### 1. Salin Proyek ke Direktori Web Server
Letakkan folder proyek di direktori root server web Anda. Jika menggunakan XAMPP di Windows:
```text
C:\xampp\htdocs\project php native
```

### 2. Nyalakan Web Server & Database
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan **MySQL**.

### 3. Impor Skema Basis Data
1. Buka browser dan akses **phpMyAdmin** di URL:
   ```text
   http://localhost/phpmyadmin/
   ```
2. Pilih tab **Import**.
3. Klik tombol **Choose File** / **Pilih File**, lalu pilih file skema SQL yang ada di dalam proyek:
   ```text
   C:\xampp\htdocs\project php native\database\schema.sql
   ```
4. Klik tombol **Import** / **Go** di bagian bawah.
5. Basis data bernama `inventory_db` beserta tabel-tabel dan data dummy awal (*seed data*) akan terbuat secara otomatis.

> 💡 **Alternatif via MySQL Command Line / Terminal:**
> ```bash
> mysql -u root -p < "C:\xampp\htdocs\project php native\database\schema.sql"
> ```

### 4. Periksa Konfigurasi Database
Secara default, konfigurasi koneksi pada file `config/database.php` adalah sebagai berikut:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'inventory_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```
*Sesuaikan nilai `DB_USER` atau `DB_PASS` jika instalasi MySQL lokal Anda menggunakan kata sandi tertentu.*

### 5. Jalankan Aplikasi di Browser
Buka peramban (browser) dan akses alamat:
```text
http://localhost/project%20php%20native/
```
Aplikasi akan secara otomatis mendeteksi sesi dan mengarahkan Anda ke halaman login: `http://localhost/project%20php%20native/public/login.php`.

---

## 👥 Akun Pengguna Bawaan (Seed Data)

Database telah dilengkapi akun pengguna bawaan yang dapat langsung digunakan untuk pengujian:

| Username | Password | Role | Hak Akses Utama |
|---|---|---|---|
| **`admin`** | `admin123` | **`admin`** | Akses penuh: Dashboard, Produk (CRUD), Kategori (CRUD), Supplier (CRUD), Mutasi Stok (+/-), Laporan. |
| **`staff`** | `staff123` | **`staff`** | Akses operasional: Dashboard, Katalog Produk (Read-Only), Pencatatan Mutasi Stok (+/-), Histori Mutasi. |

---

## 🗄️ Ringkasan Struktur Database

Basis data `inventory_db` terdiri dari 6 tabel relasional yang saling terhubung:

1. **`users`**: Menyimpan kredensial akun pengguna, nama lengkap, role (`admin` / `staff`), dan hash kata sandi.
2. **`categories`**: Master kategori untuk mengelompokkan jenis produk.
3. **`products`**: Master barang dengan relasi ke kategori (`category_id`), kode SKU unik, harga satuan, stok saat ini, dan batas minimum stok.
4. **`suppliers`**: Master kontak dan identitas penyedia barang / vendor.
5. **`product_suppliers`**: Tabel relasi *many-to-many* antara produk dan supplier, lengkap dengan harga beli supplier (`supplier_price`).
6. **`stock_movements`**: Log transaksi mutasi stok masuk (`IN`) dan keluar (`OUT`), kuantitas, catatan, dan referensi staf pencatat (`user_id`).

---

## 🎨 Teknologi & Desain Antarmuka
- **Backend**: PHP Native 8.x (Berorientasi Objek & Prosedural Bersih dengan PDO).
- **Frontend**: [Tailwind CSS](https://tailwindcss.com/) (CDN) untuk antarmuka modern, bersih, dan sepenuhnya responsif.
- **Tipografi**: Google Font [Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans).
- **Icons**: SVG Icons (Heroicons style) yang ringan tanpa ketergantungan library eksternal.

---

## 📄 Lisensi & Kontribusi
Aplikasi ini dikembangkan untuk kebutuhan pembelajaran dan mini-proyek sistem informasi inventaris berbasis PHP Native. Bebas digunakan, dipelajari, dan dimodifikasi untuk keperluan edukasi.
