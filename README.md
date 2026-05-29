# 🐾 PawMart — Backend API

> REST API untuk aplikasi **PawMart**, web petshop online sederhana.  
> Dibuat sebagai proyek uji kelayakan persiapan PKL — SMK Wikrama Bogor, Jurusan PPLG XI 2026.

---

## 📋 Daftar Isi

- [Tentang Proyek](#tentang-proyek)
- [Tech Stack](#tech-stack)
- [Struktur Database](#struktur-database)
- [Instalasi & Setup](#instalasi--setup)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Dokumentasi API](#dokumentasi-api)
- [Status Fitur](#status-fitur)
- [Catatan Penting](#catatan-penting)

---

## 📖 Tentang Proyek

**PawMart** adalah aplikasi e-commerce berbasis web yang fokus pada penjualan produk hewan peliharaan (petshop). Repositori ini berisi **backend** yang dibangun dengan Laravel 13 sebagai pure REST API.

Proyek ini dipisah menjadi dua folder:
- `pawmart-be` → Backend (repositori ini)
- `pawmart-web` → Frontend (React + Vite + Tailwind CSS v4)

---

## 🛠️ Tech Stack

| Komponen | Teknologi |
|---|---|
| Framework | Laravel 13 |
| Bahasa | PHP 8.2+ |
| Database | MySQL |
| Autentikasi | JWT (`php-open-source-saver/jwt-auth`) |
| Tools | Postman, Git, VSCode, Laragon |

---

## 🗄️ Struktur Database

### Tabel `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint | Primary key |
| name | varchar | Nama pengguna |
| email | varchar | Email (unique) |
| password | varchar | Password ter-hash |
| role | enum | `admin` atau `user` |
| phone | varchar | Nomor HP (nullable) |
| timestamps | - | created_at, updated_at |

### Tabel `categories`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint | Primary key |
| name | varchar | Nama kategori |
| timestamps | - | created_at, updated_at |

### Tabel `products`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint | Primary key |
| category_id | bigint | FK → categories.id |
| name | varchar | Nama produk |
| description | text | Deskripsi (nullable) |
| price | decimal(10,2) | Harga |
| stock | int | Stok tersedia |
| image | varchar | Path foto (nullable) |
| timestamps | - | created_at, updated_at |

### Tabel `carts`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint | Primary key |
| user_id | bigint | FK → users.id |
| product_id | bigint | FK → products.id |
| quantity | int | Jumlah item |
| timestamps | - | created_at, updated_at |

### Tabel `orders`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint | Primary key |
| user_id | bigint | FK → users.id |
| total_price | decimal(10,2) | Total harga pesanan |
| status | enum | `diproses` (default) atau `selesai` |
| shipping_address | text | Alamat pengiriman |
| payment_proof | varchar | Path bukti bayar (nullable) |
| timestamps | - | created_at, updated_at |

### Tabel `order_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint | Primary key |
| order_id | bigint | FK → orders.id |
| product_id | bigint | FK → products.id |
| quantity | int | Jumlah item dipesan |
| price | decimal(10,2) | Harga saat transaksi (snapshot) |
| timestamps | - | created_at, updated_at |

### Relasi Antar Tabel
```
users       ──< orders       (one to many)
users       ──< carts        (one to many)
categories  ──< products     (one to many)
products    ──< carts        (one to many)
orders      ──< order_items  (one to many)
products    ──< order_items  (one to many)
```

---

## ⚙️ Instalasi & Setup

### Prasyarat

- PHP 8.2+
- Composer
- MySQL
- Laragon (atau server lokal lainnya)

### Langkah Instalasi

```bash
# 1. Clone repositori
git clone https://github.com/username/pawmart-be.git
cd pawmart-be

# 2. Install dependencies PHP
composer install

# 3. Salin file environment
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Generate JWT secret key
php artisan jwt:secret

# 6. Jalankan migrasi database
php artisan migrate

# 7. (Opsional) Jalankan seeder untuk data awal
php artisan db:seed
```

---

## 🔧 Konfigurasi Environment

Isi file `.env` dengan konfigurasi berikut:

```env
APP_NAME=PawMart
APP_ENV=local
APP_KEY=           # otomatis diisi saat php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pawmart
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=        # otomatis diisi saat php artisan jwt:secret
JWT_TTL=60         # masa berlaku token dalam menit
```

---

## 🚀 Menjalankan Aplikasi

```bash
php artisan serve
```

API akan berjalan di: `http://localhost:8000/api`

---

## 📡 Dokumentasi API

### Format Response

Semua endpoint mengembalikan response dalam format JSON:

```json
{
  "success": true,
  "message": "Keterangan response",
  "data": { ... }
}
```

### 🔐 Autentikasi

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| POST | `/api/register` | Public | Daftar akun baru |
| POST | `/api/login` | Public | Login, mendapat JWT token |
| POST | `/api/logout` | User login | Logout, token dihapus |
| GET | `/api/profile` | User login | Lihat profil sendiri |

#### Contoh Request Login
```json
POST /api/login
{
  "email": "user@example.com",
  "password": "password123"
}
```

#### Contoh Response Login
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "user"
    }
  }
}
```

> 🔑 Untuk endpoint yang membutuhkan login, sertakan token di header:
> `Authorization: Bearer <token>`

---

### 📦 Kategori

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| GET | `/api/categories` | Public | Ambil semua kategori |
| POST | `/api/categories` | Admin | Tambah kategori baru |
| PUT | `/api/categories/{id}` | Admin | Edit kategori |
| DELETE | `/api/categories/{id}` | Admin | Hapus kategori |

---

### 🛒 Produk

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| GET | `/api/products` | Public | Ambil semua produk |
| GET | `/api/products/{id}` | Public | Detail produk |
| POST | `/api/products` | Admin | Tambah produk + upload foto |
| PUT | `/api/products/{id}` | Admin | Edit produk + upload foto |
| DELETE | `/api/products/{id}` | Admin | Hapus produk |

---

### 🛍️ Keranjang Belanja

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| GET | `/api/carts` | User | Lihat isi keranjang sendiri |
| POST | `/api/carts` | User | Tambah produk ke keranjang |
| PUT | `/api/carts/{id}` | User | Update jumlah item |
| DELETE | `/api/carts/{id}` | User | Hapus item dari keranjang |

---

### 📝 Pesanan

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| GET | `/api/orders` | Admin/User | Admin: semua pesanan; User: pesanan sendiri |
| POST | `/api/orders` | User | Checkout (menggunakan Database Transaction) |
| GET | `/api/orders/{id}` | Admin/User | Detail pesanan |
| PUT | `/api/orders/{id}/status` | Admin | Update status pesanan ke `selesai` |
| POST | `/api/orders/{id}/payment` | User | Upload bukti pembayaran |

---

### 📊 Export

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| GET | `/api/export/orders` | Admin | Export data pesanan (PDF/Excel) |

---

## ✅ Status Fitur

| Fitur | Status |
|---|---|
| Login JWT | ✅ Selesai |
| Register & Logout | ✅ Selesai |
| CRUD Kategori | ✅ Controller selesai |
| CRUD Produk + Upload Foto | ⬜ Dalam pengerjaan |
| Keranjang Belanja | ⬜ Dalam pengerjaan |
| Checkout (Database Transaction) | ⬜ Dalam pengerjaan |
| Upload Bukti Pembayaran | ⬜ Dalam pengerjaan |
| Update Status Pesanan | ⬜ Dalam pengerjaan |
| Export PDF/Excel | ⬜ Belum dimulai |
| Middleware IsAdmin | ✅ Selesai |

---

## 📌 Catatan Penting

- **Role pengguna:** `admin` dan `user` (bukan `customer`)
- **Status pesanan:** hanya `diproses` (default saat checkout) dan `selesai` (setelah admin konfirmasi)
- **Keranjang:** disimpan di database, bukan localStorage
- **Harga di `order_items`:** menyimpan harga saat transaksi (price snapshot), tidak mengambil ulang dari tabel `products`
- **Syntax Model:** menggunakan PHP Attribute Laravel 13 (`#[Fillable]`, `#[Hidden]`), tanpa `HasFactory`
- **Semua kode** dilengkapi komentar untuk keperluan dokumentasi dan belajar

---

## 👨‍💻 Developer

**Rafa** — SMK Wikrama Bogor  
Jurusan: Pengembangan Perangkat Lunak dan Gim (PPLG) XI  
Tahun: 2026

---

> Proyek ini dibuat untuk memenuhi syarat **Uji Kelayakan Persiapan PKL** SMK Wikrama Bogor.