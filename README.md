# 🎮 Big Small Game — Laravel + Railway

Game teka BIG atau SMALL dengan duit kredit palsu (RM).

---

## 🚀 Deploy ke Railway (Cara Mudah)

### Langkah 1 — Upload ke GitHub
1. Buat repo baru di GitHub
2. Upload semua fail dari ZIP ini
3. Push ke GitHub

### Langkah 2 — Buat projek Railway
1. Pergi ke [railway.app](https://railway.app)
2. New Project → Deploy from GitHub repo
3. Pilih repo awak

### Langkah 3 — Tambah MySQL
1. Dalam projek Railway → klik **+ New**
2. Pilih **Database → MySQL**
3. Railway akan auto-link MySQL variables

### Langkah 4 — Set Variables dalam Laravel Service
Pergi ke service Laravel → **Variables** → tambah:

| Variable | Value |
|----------|-------|
| `APP_KEY` | (kosongkan dulu, akan auto-generate) |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-app.railway.app` |

> ⚠️ Variables MySQL (MYSQLHOST, MYSQLPORT, dll) **JANGAN** masuk manual — Railway auto-inject dari MySQL service.

### Langkah 5 — Deploy!
Railway akan build Dockerfile dan deploy automatik.
Migrations & seeder akan run sendiri masa startup. ✅

---

## ⚙️ Setup Local (untuk development)

```bash
# 1. Install dependencies
composer install

# 2. Copy env
cp .env.example .env

# 3. Edit .env — ganti dengan DB local awak
DB_HOST=127.0.0.1
DB_DATABASE=bigsmall_game
DB_USERNAME=root
DB_PASSWORD=your_password

# 4. Generate key
php artisan key:generate

# 5. Migrate & seed
php artisan migrate
php artisan db:seed

# 6. Jalankan
php artisan serve
```

---

## 🎯 Cara Main
- **Masuk terus** — tiada login
- Pilih jumlah bet: RM1 hingga RM100
- Klik **BIG** (jika rasa nombor 5-9) atau **SMALL** (nombor 0-4)
- Tunggu 30 saat — nombor diundi rawak
- **Menang** = duit × 2 | **Kalah** = bet hilang
- Klik **Isi Kredit** untuk tambah RM500

