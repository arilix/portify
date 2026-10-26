# Portify — Praktikum Docker pada VM

Proyek Laravel ini dikonfigurasi untuk berjalan pada satu VM menggunakan Docker Compose. Aplikasi dipublikasikan melalui HTTP port `80`, sedangkan MySQL hanya dapat diakses melalui network internal Docker dan tidak memakai SSL.

## Menjalankan pada VM Saat Ini

File `.env` pada VM sudah berisi konfigurasi lokal dan tidak disimpan ke repository. Jalankan:

```bash
docker compose up -d
```

Saat container aplikasi dimulai, entrypoint menjalankan migrasi database dan `TemplateSeeder` secara otomatis. Seeder template bersifat idempoten sehingga restart tidak menggandakan data.

Upgrade Free ke Pro menggunakan `PAYMENT_MODE=demo`. Mode ini mencatat transaksi simulasi dan mengaktifkan Pro tanpa pembayaran uang nyata atau key Midtrans.

Periksa service:

```bash
docker compose ps
docker compose logs --tail=100 app
```

Akses aplikasi melalui:

```text
http://70.153.140.37/login
```

Gunakan `http://`, bukan `https://`, karena modul praktikum ini tidak memasang SSL atau membuka port `443`.

## Persiapan pada VM Baru

```bash
cp .env.example .env
chmod 600 .env
```

Isi `APP_KEY`, `APP_URL`, `DB_PASSWORD`, dan `MYSQL_ROOT_PASSWORD` pada `.env`, kemudian jalankan `docker compose up -d`.

## Modul Lengkap

Penjelasan arsitektur, konsep cloud computing, konfigurasi environment, operasi container, persistensi, backup, troubleshooting, eksperimen kelas, dan rubrik praktikum tersedia di [DOCKER_VM.md](DOCKER_VM.md).

Pembahasan baris dan blok konfigurasi pada Dockerfile, seluruh file dalam direktori `docker/`, serta `compose.yaml` tersedia di [DOCKER_FILES_EXPLAINED.md](DOCKER_FILES_EXPLAINED.md).
