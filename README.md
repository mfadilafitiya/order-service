# E-Commerce Microservices: Order Service

> **Ujian Tengah Semester (Take Home) - Mata Kuliah Microservices**  
> **Program Studi:** Teknologi Rekayasa Perangkat Lunak - Politeknik Negeri Padang  
> **Nama Mahasiswa:** M. Fadhil Aditya  
> **NIM:** [Silakan isi NIM Anda]  
> **Tech Stack Utama:** Laravel 11 (PHP 8.3), MySQL 8.0, Docker & Docker Compose

---

## Daftar Isi
1. [Ringkasan Proyek & Studi Kasus](#1-ringkasan-proyek--studi-kasus)
2. [Arsitektur Sistem Terdistribusi](#2-arsitektur-sistem-terdistribusi)
   - [Peta Service & Port](#peta-service--port)
   - [Diagram Komunikasi Antar Service](#diagram-komunikasi-antar-service)
   - [Alur Proses Checkout](#alur-proses-checkout)
   - [Arsitektur Berlapis (Layered Architecture) Order Service](#arsitektur-berlapis-layered-architecture-order-service)
3. [Panduan Instalasi & Menjalankan Sistem](#3-panduan-instalasi--menjalankan-sistem)
   - [Prasyarat](#prasyarat)
   - [Langkah Menjalankan](#langkah-menjalankan)
   - [Verifikasi Status Service](#verifikasi-status-service)
4. [Dokumentasi Lengkap API (Order Service)](#4-dokumentasi-lengkap-api-order-service)
   - [Tabel Ringkasan Endpoint](#tabel-ringkasan-endpoint)
   - [Detail Spesifikasi & Contoh Request-Response](#detail-spesifikasi--contoh-request-response)
     - [1. GET /health](#1-get-health)
     - [2. POST /orders](#2-post-orders)
     - [3. GET /orders/{id}](#3-get-ordersid)
     - [4. GET /orders/user/{userId}](#4-get-ordersuseruserid)
     - [5. PATCH /orders/{id}/pay](#5-patch-ordersidpay)
5. [Panduan Skenario Pengujian Video Demo](#5-panduan-skenario-pengujian-video-demo)
6. [Daftar Environment Variables](#6-daftar-environment-variables)
7. [Keputusan Desain & Keterbatasan Sistem](#7-keputusan-desain--keterbatasan-sistem)
8. [Struktur Direktori Repositori](#8-struktur-direktori-repositori)

---

## 1. Ringkasan Proyek & Studi Kasus

Proyek ini merupakan implementasi arsitektur microservices untuk toko online. Sistem ini terdiri dari 4 microservices independen yang masing-masing memiliki basis data sendiri (*Database-per-Service Pattern*):
- **Product Service:** Mengelola katalog produk, harga, dan ketersediaan stok barang.
- **Cart Service:** Mengelola keranjang belanja pengguna (tambah, perbarui, hapus, dan kosongkan item).
- **Review Service:** Mengelola ulasan dan penilaian rating produk.
- **Order Service (Fokus Utama UTS):** Layanan checkout yang mengubah isi keranjang belanja user menjadi pesanan (*order*) yang valid, melakukan snapshot harga/nama produk, mengosongkan keranjang, dan mengelola status pembayaran pesanan.

---

## 2. Arsitektur Sistem Terdistribusi

### Peta Service & Port

| Service | Teknologi Stack | Port Host | Port Kontainer | Database |
|---|---|---|---|---|
| **product-services** | Node.js (Express.js) | `3000` | `3000` | MySQL 8.0 (`productdb`) |
| **cart-service** | PHP 8.3 (Laravel) | `3001` | `3002` | PostgreSQL 13 (`cartdb`) |
| **review-service** | Python (Flask) | `3002` | `3002` | MongoDB 6.0 (`reviewdb`) |
| **order-service** | PHP 8.3 (Laravel) | `3003` | `3000` | MySQL 8.0 (`orderdb`, port host `3308`) |

---

### Diagram Komunikasi Antar Service

```
                           +--------------------------+
                           |  Client / Insomnia / App |
                           +------------+-------------+
                                        |
                                        | HTTP REST (Port 3003)
                                        v
                           +--------------------------+
                           |      order-service       |
                           +-----+--------------+-----+
                                 |              |
                    1. Baca &    |              | 2. Validasi Katalog,
                    Kosongkan    |              |    Snapshot Harga,
                    Keranjang    |              |    & Cek Stok
                                 v              v
                     +---------------+      +---------------+
                     | cart-service  |      |product-service|
                     |  (Port 3001)  |      |  (Port 3000)  |
                     +-------+-------+      +-------+-------+
                             |                      |
                             v                      v
                       +-----------+          +-----------+
                       |  cart-db  |          | product-db|
                       |(Postgres) |          |  (MySQL)  |
                       +-----------+          +-----------+
```

---

### Alur Proses Checkout (`POST /orders`)

1. **Client Request:** Klien mengirim HTTP request `POST /orders` dengan body `{ "user_id": 7 }`.
2. **Validasi Input:** `CreateOrderRequest` memvalidasi keberadaan dan tipe data `user_id`. Jika tidak valid, kembalikan status `422`.
3. **Ambil Data Keranjang:** `OrderService` memanggil `CartClient` (`GET /carts/{userId}`) ke `cart-service`.
4. **Verifikasi Keranjang:** Jika keranjang kosong atau item kosong, lempar `CartEmptyException` (respon status `409`).
5. **Verifikasi Produk & Stok:** Untuk setiap item keranjang, `OrderService` memanggil `ProductClient` (`GET /products/{productId}`) ke `product-service`:
   - Jika produk tidak ditemukan/telah dihapus, lempar `ProductNotFoundException` (status `409`).
   - Jika stok tidak mencukupi (`stock < quantity`), lempar `InsufficientStockException` (status `409`).
6. **Snapshot & Transaksi Database:**
   - Dijalankan di dalam transaksi database atomik (`DB::transaction`).
   - Salin snapshot `product_name` dan `unit_price` ke tabel `order_items`.
   - Simpan entitas `orders` dengan status awal `PENDING` dan hitung total amount.
7. **Pembersihan Keranjang (Non-blocking):**
   - `OrderService` meminta `cart-service` mengosongkan keranjang (`DELETE /carts/{userId}`).
   - Jika pengosongan keranjang gagal atau timeout, **pesanan tidak dibatalkan** (ACID tetap terjaga), status tetap `201 Created`, dan insiden dicatat dalam log sistem.
8. **Respon Klien:** Mengembalikan data pesanan lengkap berserta relasi itemnya dengan HTTP status `201 Created`.

---

### Arsitektur Berlapis (Layered Architecture) Order Service

Order Service dibangun dengan pemisahan tanggung jawab yang ketat (*Strict Separation of Concerns*):

```
[ HTTP Request ]
       |
       v
+-------------------------------------------------------------------------+
| 1. Route Layer (routes/api.php)                                         |
|    - Endpoint mapping (/health, /orders, /orders/{id}, dsb)             |
|    - Validasi regex path parameter (whereNumber)                        |
+-------------------------------------------------------------------------+
       |
       v
+-------------------------------------------------------------------------+
| 2. Validation Layer (app/Http/Requests/)                                |
|    - CreateOrderRequest: Memvalidasi user_id (required, integer, min:1) |
|    - GetOrdersByUserRequest: Memvalidasi parameter ?status=             |
|    - Mengembalikan format JSON standar 422 jika tidak valid             |
+-------------------------------------------------------------------------+
       |
       v
+-------------------------------------------------------------------------+
| 3. Controller Layer (app/Http/Controllers/OrderController.php)          |
|    - Mengorkestrasi input HTTP ke Service Layer                         |
|    - Menangkap Custom Business Exceptions (409, 404, 503, 500)          |
|    - Memformat JSON Response                                            |
|    - Controller TIDAK BERISI query SQL maupun logika bisnis             |
+-------------------------------------------------------------------------+
       |
       v
+-------------------------------------------------------------------------+
| 4. Service / Business Logic Layer (app/Services/OrderService.php)       |
|    - Menjalankan seluruh aturan bisnis checkout                         |
|    - Menghitung subtotal & total amount                                 |
|    - Mengelola DB::transaction untuk integritas data order & item       |
|    - Service TIDAK MEMILIKI ketergantungan terhadap HTTP Request        |
+-------------------------------------------------------------------------+
       |                                |
       v                                v
+----------------------------+   +----------------------------------------+
| 5. Client Layer            |   | 6. Eloquent Model Layer                |
|    (app/Clients/)          |   |    (app/Models/)                       |
|    - ProductClient.php     |   |    - Order.php (hasMany OrderItem)     |
|    - CartClient.php        |   |    - OrderItem.php (belongsTo Order)   |
|    - Timeout 5 detik       |   |    - Snapshot attributes & casting     |
|    - Custom 503 Exception  |   +----------------------------------------+
+----------------------------+
```

---

## 3. Panduan Instalasi & Menjalankan Sistem

### Prasyarat
- Docker Engine versi >= 20.10
- Docker Compose versi >= 2.0
- Git
- Pastikan port host berikut tidak sedang dipakai oleh aplikasi lokal lain:
  - `3000` (Product Service)
  - `3001` (Cart Service)
  - `3002` (Review Service)
  - `3003` (Order Service)
  - `3306`, `3308`, `5432`, `27017` (Database Services)

### Langkah Menjalankan

1. **Clone Repositori:**
   ```bash
   git clone <URL_REPOSITORI_ANDA>
   cd mi-psdku
   ```

2. **Jalankan Seluruh Service Menggunakan Docker Compose:**
   ```bash
   docker compose up --build
   ```
   *Atau jalankan di background (detached mode):*
   ```bash
   docker compose up --build -d
   ```
   > **Catatan:** Seluruh container, network docker, migrasi skema tabel PostgreSQL (`cartdb`) dan MySQL (`orderdb`, `productdb`) akan diinisialisasi secara otomatis saat proses build pertama kali.

3. **Verifikasi Status Kontainer:**
   ```bash
   docker compose ps
   ```
   Pastikan seluruh kontainer berada dalam status `Up` atau `Up (healthy)`.

4. **Mematikan dan Menghapus Volume (Opsional / Reset Data):**
   ```bash
   docker compose down -v
   ```

---

## 4. Dokumentasi Lengkap API (Order Service)

**Base URL:** `http://localhost:3003`

### Tabel Ringkasan Endpoint

| Method | Endpoint | Fungsi | Status Sukses | Kondisi & Status Gagal |
|---|---|---|---|---|
| `GET` | `/health` | Pengecekan status hidup service | `200 OK` | Tidak ada |
| `POST` | `/orders` | Checkout pesanan dari isi keranjang | `201 Created` | `422`: Body tidak valid<br>`409`: Keranjang kosong, produk tidak ada, atau stok kurang<br>`503`: Service eksternal mati/timeout<br>`500`: Kesalahan tak terduga |
| `GET` | `/orders/{id}` | Detail satu pesanan beserta item snapshot | `200 OK` | `404`: Pesanan tidak ditemukan / ID bukan angka<br>`500`: Kesalahan tak terduga |
| `GET` | `/orders/user/{userId}` | Daftar pesanan user (terbaru di atas) | `200 OK` | `422`: Nilai parameter status tidak valid<br>`404`: User ID bukan angka<br>`500`: Kesalahan tak terduga |
| `PATCH` | `/orders/{id}/pay` | Simulasi pembayaran pesanan | `200 OK` | `404`: Pesanan tidak ditemukan / ID bukan angka<br>`409`: Status pesanan bukan PENDING<br>`500`: Kesalahan tak terduga |

---

### Detail Spesifikasi & Contoh Request-Response

#### 1. GET /health
Mengecek apakah kontainer `order-service` aktif dan siap menerima request.

- **Request:**
  ```bash
  curl -i http://localhost:3003/health
  ```
- **Response (200 OK):**
  ```json
  {
    "status": "ok",
    "service": "order-service"
  }
  ```

---

#### 2. POST /orders
Mengubah isi keranjang belanja pengguna menjadi pesanan baru.

- **Header:** `Content-Type: application/json`
- **Request Body:**
  ```json
  {
    "user_id": 7
  }
  ```
- **Contoh Request:**
  ```bash
  curl -i -X POST http://localhost:3003/orders \
    -H "Content-Type: application/json" \
    -d '{"user_id": 7}'
  ```

- **Response Sukses (201 Created):**
  ```json
  {
    "message": "Pesanan berhasil dibuat",
    "data": {
      "id": 1,
      "user_id": 7,
      "status": "PENDING",
      "total_amount": 1350000,
      "created_at": "2026-10-29T06:03:31+00:00",
      "updated_at": "2026-10-29T06:03:31+00:00",
      "items": [
        {
          "product_id": 1,
          "product_name": "Keyboard Mekanikal",
          "unit_price": 500000,
          "quantity": 2,
          "subtotal": 1000000
        },
        {
          "product_id": 2,
          "product_name": "Mouse Wireless",
          "unit_price": 350000,
          "quantity": 1,
          "subtotal": 350000
        }
      ]
    }
  }
  ```

- **Response Gagal - Body Tidak Valid (422 Unprocessable Content):**
  ```json
  {
    "message": "Data yang dikirim tidak valid",
    "errors": {
      "user_id": [
        "Field user_id wajib diisi"
      ]
    }
  }
  ```

- **Response Gagal - Keranjang Kosong (409 Conflict):**
  ```json
  {
    "message": "Keranjang belanja kosong"
  }
  ```

- **Response Gagal - Stok Kurang (409 Conflict):**
  ```json
  {
    "message": "Stok Keyboard Mekanikal tidak mencukupi: diminta 100, tersedia 25"
  }
  ```

- **Response Gagal - Produk Tidak Ditemukan di Katalog (409 Conflict):**
  ```json
  {
    "message": "Produk dengan id 99999 tidak ditemukan"
  }
  ```

- **Response Gagal - Layanan Lain Tidak Dapat Dihubungi (503 Service Unavailable):**
  ```json
  {
    "message": "Product service tidak dapat dihubungi, coba lagi nanti",
    "debug": {
      "exception": "App\\Exceptions\\ServiceUnavailableException",
      "file": "/var/www/app/Clients/ProductClient.php",
      "line": 42
    }
  }
  ```
  *(Catatan: Blok `debug` hanya akan muncul jika `APP_DEBUG=true`).*

---

#### 3. GET /orders/{id}
Mengambil detail satu pesanan beserta seluruh item snapshot produknya.

- **Contoh Request:**
  ```bash
  curl -i http://localhost:3003/orders/1
  ```

- **Response Sukses (200 OK):**
  ```json
  {
    "message": "Berhasil mengambil pesanan",
    "data": {
      "id": 1,
      "user_id": 7,
      "status": "PENDING",
      "total_amount": 1350000,
      "created_at": "2026-10-29T06:03:31+00:00",
      "updated_at": "2026-10-29T06:03:31+00:00",
      "items": [
        {
          "product_id": 1,
          "product_name": "Keyboard Mekanikal",
          "unit_price": 500000,
          "quantity": 2,
          "subtotal": 1000000
        },
        {
          "product_id": 2,
          "product_name": "Mouse Wireless",
          "unit_price": 350000,
          "quantity": 1,
          "subtotal": 350000
        }
      ]
    }
  }
  ```

- **Response Gagal - Pesanan Tidak Ditemukan / ID Bukan Angka (404 Not Found):**
  ```json
  {
    "message": "Pesanan dengan id 99 tidak ditemukan"
  }
  ```

---

#### 4. GET /orders/user/{userId}
Mengambil daftar riwayat pesanan milik satu pengguna dengan urutan terbaru di atas (`created_at DESC`), didukung filter opsional query parameter `?status=`.

- **Contoh Request (Semua status):**
  ```bash
  curl -i http://localhost:3003/orders/user/7
  ```
- **Contoh Request (Filter status PENDING):**
  ```bash
  curl -i "http://localhost:3003/orders/user/7?status=PENDING"
  ```

- **Response Sukses (200 OK):**
  ```json
  {
    "message": "Berhasil mengambil daftar pesanan",
    "data": [
      {
        "id": 1,
        "user_id": 7,
        "status": "PENDING",
        "total_amount": 1350000,
        "created_at": "2026-10-29T06:03:31+00:00",
        "updated_at": "2026-10-29T06:03:31+00:00",
        "items": [
          {
            "product_id": 1,
            "product_name": "Keyboard Mekanikal",
            "unit_price": 500000,
            "quantity": 2,
            "subtotal": 1000000
          }
        ]
      }
    ]
  }
  ```

- **Response Gagal - Filter Status Tidak Valid (422 Unprocessable Content):**
  ```bash
  curl -i "http://localhost:3003/orders/user/7?status=SHIPPED"
  ```
  Respon:
  ```json
  {
    "message": "Data yang dikirim tidak valid",
    "errors": {
      "status": [
        "Status harus PENDING atau PAID"
      ]
    }
  }
  ```

- **Response Gagal - User ID Bukan Angka (404 Not Found):**
  ```json
  {
    "message": "Pesanan tidak ditemukan"
  }
  ```

---

#### 5. PATCH /orders/{id}/pay
Melakukan simulasi pembayaran pesanan dari status `PENDING` menjadi `PAID`.

- **Contoh Request:**
  ```bash
  curl -i -X PATCH http://localhost:3003/orders/1/pay
  ```

- **Response Sukses (200 OK):**
  ```json
  {
    "message": "Pesanan berhasil dibayar",
    "data": {
      "id": 1,
      "user_id": 7,
      "status": "PAID",
      "total_amount": 1350000,
      "created_at": "2026-10-29T06:03:31+00:00",
      "updated_at": "2026-10-29T06:04:41+00:00",
      "items": [ ... ]
    }
  }
  ```

- **Response Gagal - Sudah Dibayar / Status Bukan PENDING (409 Conflict):**
  ```json
  {
    "message": "Pesanan berstatus PAID, hanya pesanan PENDING yang dapat dibayar"
  }
  ```

- **Response Gagal - Pesanan Tidak Ditemukan / ID Bukan Angka (404 Not Found):**
  ```json
  {
    "message": "Pesanan dengan id 99 tidak ditemukan"
  }
  ```

---

## 5. Panduan Skenario Pengujian Video Demo

Gunakan urutan pengujian berikut saat merekam video demo UTS untuk memastikan setiap kriteria penilaian pada Bagian 6 terpenuhi secara runtut:

### Tahap 1: Pembukaan & Arsitektur (0:00 - 3:00)
1. Perkenalan diri (Nama, NIM) dan penjelasan singkat arsitektur microservices.
2. Tunjukkan pembagian lapisan pada kode `order-service` (Routes, Form Request, Controller, Service, Client, Model, Exception).
3. Jalankan `docker compose up --build` di terminal untuk membuktikan seluruh sistem siap tanpa setup manual tambahan.

### Tahap 2: Pengujian Endpoint Utama & Validasi (3:00 - 8:00)

1. **Health Check (200 OK):**
   - Kirim `GET http://localhost:3003/health`
2. **Uji Validasi Form Request (422 Unprocessable Content):**
   - Kirim `POST http://localhost:3003/orders` dengan body kosong `{}`.
   - Hasil: Pesan error `user_id` wajib diisi.
3. **Uji Keranjang Kosong (409 Conflict):**
   - Kirim `POST http://localhost:3003/orders` dengan body `{"user_id": 999}` (user tanpa belanjaan).
   - Hasil: Pesan `Keranjang belanja kosong`.
4. **Isi Keranjang User 7 via Cart Service:**
   - Kirim `POST http://localhost:3001/carts` -> `{"user_id": 7, "product_id": 1, "quantity": 2}`.
5. **Uji Stok Kurang (409 Conflict):**
   - Tambahkan produk yang stoknya melebihi katalog ke user lain (misal user 8 diisi produk 1 sebanyak 100 unit).
   - Kirim `POST http://localhost:3003/orders` untuk user 8.
   - Hasil: `Stok Keyboard Mekanikal tidak mencukupi: diminta 100, tersedia 25`.
6. **Uji Checkout Sukses (201 Created):**
   - Kirim `POST http://localhost:3003/orders` untuk user 7.
   - Tunjukkan order terbuat dengan status `PENDING`, total harga benar, snapshot item ada.
   - Tunjukkan keranjang user 7 otomatis kosong saat dicek via `GET http://localhost:3001/carts/7`.
7. **Uji Detail Pesanan (200 OK & 404 Not Found):**
   - Kirim `GET http://localhost:3003/orders/1` -> Data pesanan muncul lengkap.
   - Kirim `GET http://localhost:3003/orders/999` -> Respon `404 Not Found`.
   - Kirim `GET http://localhost:3003/orders/abc` -> Respon `404 Not Found`.
8. **Uji Daftar Pesanan & Filter Status (200 OK & 422):**
   - Kirim `GET http://localhost:3003/orders/user/7` -> Muncul pesanan urutan terbaru.
   - Kirim `GET http://localhost:3003/orders/user/7?status=PENDING` -> Filter berhasil.
   - Kirim `GET http://localhost:3003/orders/user/7?status=SHIPPED` -> Respon `422 Data yang dikirim tidak valid`.
9. **Uji Simulasi Pembayaran (200 OK & 409 Conflict):**
   - Kirim `PATCH http://localhost:3003/orders/1/pay` -> Status berubah menjadi `PAID`.
   - Kirim `PATCH http://localhost:3003/orders/1/pay` sekali lagi -> Respon `409 Conflict` (pesanan sudah dibayar).

### Tahap 3: Pembuktian Snapshot & Ketahanan Layanan (8:00 - 12:00)

10. **Pembuktian Snapshot Harga:**
    - Perlihatkan harga item pada pesanan 1 adalah `500000`.
    - Ubah harga produk 1 di database `product-service` menjadi harga lain (misal `750000`).
    - Panggil kembali `GET http://localhost:3003/orders/1` dan tunjukkan harga pesanan lama **tetap tidak berubah**.
11. **Uji Service Eksternal Mati (503 Service Unavailable & Log):**
    - Matikan container katalog produk: `docker stop mi-psdku-product-services-1`.
    - Lakukan checkout: `POST http://localhost:3003/orders`.
    - Tunjukkan respon klien menghasilkan status `503 Service Unavailable`.
    - Tunjukkan log terminal order-service (`docker logs mi-psdku-order-service-1`): memperlihatkan log error terperinci lengkap dengan URL dan ID produk yang gagal dihubungi.
    - Nyalakan kembali container: `docker start mi-psdku-product-services-1`.

---

## 6. Daftar Environment Variables

Seluruh konfigurasi bersifat dinamis dan dibaca melalui Environment Variables:

| Variable | Default di Kontainer | Deskripsi |
|---|---|---|
| `APP_NAME` | `order-service` | Nama identitas aplikasi |
| `APP_ENV` | `docker` | Mode environment aplikasi (`local`, `docker`, `production`) |
| `APP_KEY` | `base64:tYMCwlIh...` | Kunci enkripsi enkapsulasi Laravel |
| `APP_DEBUG` | `true` | Menampilkan objek `debug` pada status 500 dan 503 jika aktif |
| `APP_URL` | `http://localhost:3003` | URL dasar aplikasi |
| `DB_CONNECTION` | `mysql` | Driver database relasional (wajib MySQL) |
| `DB_HOST` | `order-db` | Nama host container database MySQL milik order-service |
| `DB_PORT` | `3306` | Port internal MySQL pada jaringan Docker |
| `DB_DATABASE` | `orderdb` | Nama skema database pesanan |
| `DB_USERNAME` | `root` | Kredensial pengguna database MySQL |
| `DB_PASSWORD` | `rootpassword` | Password pengguna database MySQL |
| `PRODUCT_SERVICE_URL` | `http://product-services:3000` | Alamat endpoint API Product Service |
| `CART_SERVICE_URL` | `http://cart-service:3002` | Alamat endpoint API Cart Service |

---

## 7. Keputusan Desain & Keterbatasan Sistem

### Keputusan Desain

1. **Transaksi Atomik Database (`DB::transaction`):**
   Penyimpanan header pesanan (`orders`) dan detail item (`order_items`) dibungkus dalam satu blok transaksi database. Jika terdapat salah satu item yang gagal tersimpan atau koneksi terputus, seluruh data dibatalkan (*rollback*) secara otomatis untuk mencegah inkonsistensi data *orphan records*.
2. **Data Snapshotting Pattern:**
   Katalog produk bersifat dinamis di mana nama dan harga barang dapat berubah sewaktu-waktu. Oleh karena itu, data nama barang dan harga satuan pada saat checkout langsung disalin ke tabel `order_items`. Hal ini menjamin nilai historis pesanan akurat dan tidak terdampak oleh perubahan katalog di masa depan.
3. **Resiliensi Pengosongan Keranjang (*Non-blocking Post-Checkout Clean-up*):**
   Sesuai ketentuan, jika pesanan telah berhasil disimpan di database namun panggilan pengosongan keranjang ke `cart-service` gagal atau timeout, **pesanan tidak dibatalkan**. Klien tetap menerima respon `201 Created` dan kegagalan dicatat dalam level peringatan log (*warning log*) agar tidak mengganggu transaksi yang sah.
4. **Batas Timeout Komunikasi Jaringan (Strict 5s Timeout):**
   Setiap interaksi HTTP antar-layanan melalui `ProductClient` dan `CartClient` dibatasi secara ketat dengan timeout maksimal 5 detik. Hal ini mencegah fenomena *thread starvation* atau *cascading failure* ketika salah satu service eksternal mengalami *unresponsive*.
5. **Pemetaan Custom Business Exceptions:**
   Seluruh aturan bisnis (seperti `CartEmptyException`, `InsufficientStockException`, `ProductNotFoundException`, `InvalidOrderStatusException`, `ServiceUnavailableException`) didelegasikan ke kelas exception tersendiri. Controller hanya bertugas menangkap exception dan mengonversi menjadi status HTTP yang sesuai (`409`, `503`, `404`).

### Keterbatasan Sistem

1. **Komunikasi REST Sinkron (Synchronous REST):**
   Komunikasi antar service masih bergantung pada HTTP sinkron. Jika beban checkout melonjak drastis, latensi checkout akan berbanding lurus dengan kecepatan respon `product-service` dan `cart-service`.
2. **Ketiadaan Message Broker / Outbox Pattern:**
   Karena pengosongan keranjang yang gagal tidak membatalkan pesanan, sistem saat ini belum memiliki tabel *Transactional Outbox* atau antrean pesan asinkron (misal RabbitMQ/Kafka) untuk mencoba ulang (*retry*) pengosongan keranjang belanja secara otomatis.
3. **Pengecekan Stok Non-Pengurangan:**
   Sesuai batasan tugas, stok barang hanya diperiksa ketersediaannya dan belum dikurangkan secara langsung di `product-service` (tidak ada *two-phase commit* atau reservasi stok).

---

## 8. Struktur Direktori Repositori

```
mi-psdku/
├── docker-compose.yml           # Orkestrasi seluruh container microservices & databases
├── README.md                    # Dokumentasi lengkap proyek & panduan pengujian
├── product-service/             # Layanan katalog produk (Express.js & MySQL)
├── cart-service/                # Layanan keranjang belanja (Laravel & PostgreSQL)
├── review-service/              # Layanan review produk (Flask & MongoDB)
└── order-service/               # Layanan pesanan (Laravel 11 & MySQL)
    ├── app/
    │   ├── Clients/             # Lapisan pemanggil HTTP service eksternal
    │   │   ├── CartClient.php
    │   │   └── ProductClient.php
    │   ├── Exceptions/          # Custom exceptions aturan bisnis
    │   │   ├── CartEmptyException.php
    │   │   ├── InsufficientStockException.php
    │   │   ├── InvalidOrderStatusException.php
    │   │   ├── OrderNotFoundException.php
    │   │   ├── ProductNotFoundException.php
    │   │   └── ServiceUnavailableException.php
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   │   └── OrderController.php
    │   │   └── Requests/        # Form Request untuk validasi input
    │   │       ├── CreateOrderRequest.php
    │   │       └── GetOrdersByUserRequest.php
    │   ├── Models/              # Eloquent Model & Relasi
    │   │   ├── Order.php
    │   │   └── OrderItem.php
    │   └── Services/            # Business Logic Layer
    │       └── OrderService.php
    ├── bootstrap/
    │   └── app.php              # Konfigurasi routing & penanganan exception JSON
    ├── config/
    │   ├── database.php
    │   └── services.php         # Konfigurasi URL service eksternal
    ├── database/
    │   └── migrations/          # Migrasi skema tabel orders & order_items
    ├── routes/
    │   └── api.php              # Definisi endpoint REST API
    ├── .env.example             # Contoh environment variable
    └── Dockerfile               # Konfigurasi kontainer PHP 8.3 Order Service
```

---
*Semoga dokumentasi ini membantu proses penilaian dan evaluasi tugas UTS Microservices!*
