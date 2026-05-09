Project ini dikembangkan untuk memenuhi tugas mata kuliah **Perangkat Lunak Telekomunikasi (TE2026)**. Aplikasi BEMS dirancang untuk memonitoring dan mengelola sistem infrastruktur energi baterai dengan fitur manajemen gedung, ruangan, dan autentikasi multi-peran (Admin, Client, Operator).

---

## 🛠️ Tech Stack

Aplikasi ini dibangun menggunakan arsitektur modern untuk memastikan performa dan kemudahan pengembangan:
*   **Backend:** Laravel 
*   **Frontend:** Livewire & Mary UI (Tailwind CSS)
*   **Database:** MySQL
*   **Environment:** Docker (Laravel Sail) di atas WSL2 (Ubuntu)

---

## 📋 Persyaratan Sistem

Sebelum menjalankan project ini di laptop masing-masing, pastikan sudah menginstal:
*   Git
*   Docker Desktop (sudah berjalan)
*   WSL2 (Jika menggunakan Windows)

---

## 🚀 Cara Instalasi & Menjalankan Project (Untuk Tim)

Karena project ini menggunakan **Docker (Laravel Sail)**, kamu **TIDAK PERLU** menginstal PHP, Composer, atau MySQL secara manual di komputer lokal. Cukup ikuti langkah-langkah berikut di terminal (WSL/Linux/Mac):

**1. Clone Repository**
Unduh project ke komputer lokal dan masuk ke foldernya:
```bash
git clone [https://github.com/naufalalghazali28-creator/PerangkatLunakTelekomunikasi-Chaka.git](https://github.com/naufalalghazali28-creator/PerangkatLunakTelekomunikasi-Chaka.git)
cd PerangkatLunakTelekomunikasi-Chaka
```

**2. Setup File Environment**
Gandakan file konfigurasi:
cp .env.example .env

**3. Install Dependencies (Composer)**
Jalankan perintah ini untuk mengunduh semua package Laravel melalui Docker container kecil:
```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

**4. Nyalakan Mesin Docker (Sail)**
Jalankan aplikasi di background:
```bash
./vendor/bin/sail up -d
```

**5. Setup Aplikasi Laravel**
Buat key keamanan, lalu bangun struktur database beserta data bohongan (seeder):
```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

**6. Akses Aplikasi**
Buka browser dan kunjungi:
```bash
http://localhost
```

Developoed by Muhammad Naufal Al Ghazali
