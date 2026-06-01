app/
├── Http/Controllers/
│   └── ClipController.php       ← terima request, return status
├── Jobs/
│   └── ClipVideoJob.php         ← logic utama: download + cut
├── Models/
│   └── ClipJob.php
routes/
├── web.php                      ← halaman utama
└── api.php                      ← /api/clip, /api/clip/{id}/status
storage/app/clips/               ← output hasil potongan

CREATE TABLE clip_jobs (
    id          UUID PRIMARY KEY,
    youtube_url VARCHAR(500) NOT NULL,
    start_time  INT NOT NULL,          -- detik
    end_time    INT NOT NULL,          -- detik
    status      ENUM('pending','processing','done','failed') DEFAULT 'pending',
    file_path   VARCHAR(500) NULL,
    error_msg   TEXT NULL,
    created_at  TIMESTAMP,
    expires_at  TIMESTAMP              -- untuk auto-delete
);

// app/Jobs/ClipVideoJob.php
public function handle() {
    // 1. Update status → processing
    // 2. yt-dlp download ke /tmp/{job_id}_raw.mp4
    // 3. FFmpeg cut: -ss {start} -to {end} -c copy
    // 4. Pindah hasil ke storage/app/clips/{job_id}.mp4
    // 5. Update status → done, simpan file_path
    // 6. Hapus file raw /tmp
}

public function failed(Throwable $e) {
    // Update status → failed, simpan error_msg
}

API Endpoints
MethodEndpointFungsi
POST/api/clipSubmit URL + timestamp
GET/api/clip/{id}/statusCek status job
GET/api/clip/{id}/downloadDownload file hasil

Tech Stack Detail
KomponenPilihanAlasanFrameworkLaravel 11Familiar, Queue built-inQueue driverDatabase (MVP) → Redis laterSimple setup duluVideo downloaderyt-dlp (binary)Aktif maintained, lebih stabil dari youtube-dlVideo cutterFFmpeg via shell_exec-c copy = tanpa re-encode, sangat cepatFrontendBlade + Alpine.jsRingan, polling sederhanaDBMySQL / PostgreSQLBebas pilih

Batasan MVP

Durasi clip maksimal 10 menit
Output format: MP4 saja
File auto-delete: 30 menit setelah selesai
Tidak perlu login/auth
Max file size output: ~500MB

Urutan Development

Setup project → laravel new clipper, install queue, buat migration
ClipController → validasi input, buat record, dispatch job
ClipVideoJob → shell yt-dlp → shell FFmpeg → update status
API status endpoint → polling setiap 3 detik dari frontend
Frontend (Blade) → form input + progress indicator + tombol download
Scheduler auto-delete → php artisan schedule:run tiap menit
Error handling & retry → $tries = 2, timeout job

1. Pemilihan Waktu (Timestamp Selector)
Ini murni Frontend — Blade + JS.
Pilihannya ada dua:
Option A — Input manual (MVP paling simpel)

User ketik 00:01:30 dan 00:02:45 di input field
Kirim ke backend sebagai detik integer

Option B — Video player dengan range selector

Embed YouTube iframe player
Pakai YouTube IFrame API untuk baca currentTime
User klik "Set Start" dan "Set End" sambil memutar video
Lebih UX-friendly tapi butuh lebih banyak JS

Untuk MVP rekomendasi Option A dulu, lalu upgrade ke B setelah core flow jalan.

2. Menambahkan Text / Watermark
Ini masuk ke FFmpeg layer — di dalam ClipVideoJob.
FFmpeg punya filter drawtext untuk overlay teks ke video:

ffmpeg -i input.mp4 \
  -vf "drawtext=text='Hello World':fontsize=36:fontcolor=white:x=10:y=10" \
  -c:a copy output.mp4

Berarti alurnya:

User isi form tambahan: teks, posisi (pojok kanan bawah, dll), ukuran font, warna
Backend terima parameter ini, masukkan ke command FFmpeg
Karena pakai drawtext, harus re-encode (tidak bisa -c copy lagi) → proses lebih lama

Form Input:
├── YouTube URL
├── Start time / End time       ← Timestamp selector (frontend)
├── Text overlay (opsional)     ← Teks + posisi + warna
└── Submit

ClipVideoJob:
├── yt-dlp download
├── FFmpeg:
│   ├── Tanpa text → -c copy (cepat, tanpa re-encode)
│   └── Dengan text → -vf drawtext (re-encode, lebih lama)
└── Simpan hasil

Tech Stack — YouTube Clipper MVP
Backend
Laravel 11
Framework PHP utama. Dipilih karena memiliki sistem queue, scheduler, dan routing yang sudah built-in tanpa perlu library tambahan. Cocok untuk mengelola job async seperti proses download dan encoding video yang bisa memakan waktu lama.
Laravel Queue (driver: Database → Redis)
Menangani proses berat secara asynchronous. User tidak perlu menunggu proses selesai — mereka submit job, lalu polling status. Dimulai dengan driver database agar setup awal simpel, bisa migrasi ke Redis saat traffic meningkat.
Laravel Scheduler
Menjalankan task otomatis via php artisan schedule:run. Digunakan untuk auto-delete file clip dan record DB yang sudah melewati batas waktu 30 menit, menjaga storage tetap bersih tanpa intervensi manual.

Video Processing
yt-dlp
Command-line tool untuk mendownload video dari YouTube. Dipilih dibanding youtube-dl karena lebih aktif dikembangkan, lebih stabil menghadapi perubahan API YouTube, dan mendukung lebih banyak format. Dipanggil via shell_exec() dari dalam job.
FFmpeg
Engine utama untuk memotong video dan menambahkan text overlay. Dua mode penggunaan:

-c copy — stream copy tanpa re-encode, dipakai ketika tidak ada overlay. Prosesnya sangat cepat karena tidak memproses ulang frame video.
-vf drawtext + -c:v libx264 — re-encode dengan filter teks, dipakai ketika user menambahkan overlay. Lebih lambat tapi menghasilkan teks yang ter-render langsung di video.

Frontend
Blade (Laravel templating)
Template engine bawaan Laravel untuk merender halaman HTML. Digunakan untuk struktur halaman utama, form input, dan tampilan status clip.
Alpine.js
Framework JavaScript ringan (~15kb) untuk interaktivitas di sisi client. Menangani polling status job setiap 3 detik, update tampilan progress, dan logika form tanpa perlu setup build tool seperti Vite atau Webpack.
YouTube IFrame API
API resmi dari Google untuk meng-embed player YouTube yang bisa dikontrol via JavaScript. Digunakan untuk fitur timestamp selector — user memutar video langsung di halaman, lalu klik tombol "Set Start" dan "Set End" untuk mengambil currentTime dari player sebagai nilai detik yang dikirim ke backend.

Database
PostgreSQL
Menyimpan data job clip beserta statusnya (pending, processing, done, failed), parameter input user (URL, timestamp, overlay), path file output, dan waktu kadaluarsa file. Juga digunakan sebagai queue driver pada tahap awal MVP.

Storage
Local Filesystem (storage/app/clips/)
File hasil clip disimpan sementara di server. Setiap file diberi nama berdasarkan UUID job agar tidak bentrok. File otomatis dihapus setelah 30 menit melalui scheduler. Pada fase scale-up, s
