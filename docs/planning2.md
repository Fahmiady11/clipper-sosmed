# Clipper — YouTube Shorts Maker

Prototype hi-fi untuk aplikasi web Clipper yang mengubah video YouTube panjang
menjadi Shorts 9:16 secara otomatis dengan bantuan Gemini AI.

> **Catatan:** File ini adalah prototype frontend interaktif (React + Tailwind-less CSS).
> Backend Laravel 11 / PHP belum diimplementasikan di project ini — hanya UI dan
> alur interaksi yang dimockup penuh.

---

## 📦 Tech Stack (target final)

| Layer        | Teknologi                                    |
|--------------|----------------------------------------------|
| Backend      | Laravel 11 (PHP 8.2+)                        |
| Frontend     | Blade + Alpine.js + Tailwind CSS             |
| AI           | Google Gemini API (`gemini-2.0-flash`)       |
| Queue        | Laravel Queue (database driver)              |
| Storage      | Laravel Storage (local/public disk)          |

Prototype ini ditulis dengan **React 18 + Babel standalone** sebagai
representasi visual setia dari UI yang akan diimplementasikan di Blade.

---

## 🎨 Sistem Desain

**Aesthetic:** *strict minimal, dark mode only*.

| Token            | Value         | Penggunaan                       |
|------------------|---------------|----------------------------------|
| `--bg`           | `#0a0a0c`    | Canvas utama                     |
| `--surface`      | `#131318`    | Card / input background          |
| `--text`         | `#f5f5f7`    | Foreground                       |
| `--text-muted`   | `#8a8a95`    | Sekunder                         |
| `--teal`         | `#2dd4bf`    | Auto Magic + badge DISARANKAN    |
| `--yellow`       | `#facc15`    | Auto Split + badge BARU          |
| `--purple`       | `#a78bfa`    | Gaussian Blur                    |
| `--blue`         | `#60a5fa`    | Auto Reframe                     |

Typography: **Inter** (UI) + **JetBrains Mono** (metadata, labels, kode).

---

## 🗂️ Struktur File Prototype

```
clipper/
├── Clipper.html         # Entry point — load fonts, React, semua script
├── app.css              # Design tokens + semua styling
├── app.jsx              # State machine + 3 screen (Home / Processing / Result)
├── illustrations.jsx    # SVG mini-illustrations 3 gaya untuk layout cards
├── preview.jsx          # 9:16 phone preview + rendering per layout mode
└── tweaks-panel.jsx     # Floating Tweaks control panel
```

---

## 🛣️ Alur Layar

```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│ 01 · URL     │ →  │ 02 · Layout  │ →  │ 03 · Proses  │ →  │ 04 · Hasil   │
│ Input field  │    │ Pilih 1/4    │    │ Stage log    │    │ Preview 9:16 │
└──────────────┘    └──────────────┘    └──────────────┘    └──────────────┘
```

1. **Home** (`screen: 'home'`)
   - Input URL YouTube dengan validasi live (regex untuk
     `watch?v=`, `youtu.be/`, `/shorts/`).
   - Setelah URL valid, 4 kartu layout fade in dengan dashed border default.
   - Klik kartu → solid colored border + `✓ Dipilih` pill (bottom-left).
   - Tombol `Buat Clip →` aktif setelah URL valid + 1 layout terpilih.

2. **Processing** (`screen: 'processing'`)
   - Status pill animasi (pulse dot) + progress bar gradient.
   - Stage log monospaced 6 baris (metadata → keyframes → Gemini call →
     extract → apply layout → render).
   - Otomatis lanjut ke Result setelah selesai.

3. **Result** (`screen: 'result'`)
   - Kolom kiri: video card + hook / caption / hashtags / timestamps,
     dengan tombol salin ke clipboard per section.
   - Kolom kanan: phone frame 9:16 sticky dengan rendering layout
     spesifik (lihat tabel di bawah).
   - Aksi: Download Preview, Render MP4 final, Buat clip lain.

---

## 🎬 Mode Layout

| Mode            | Warna  | Badge       | Visual treatment di preview                          |
|-----------------|--------|-------------|------------------------------------------------------|
| `auto_magic`    | teal   | Cooming Soon  | Gradient teal di bagian atas + hook highlighted      |
| `auto_split`    | yellow | Cooming Soon        | Dua thumbnail placeholder ditumpuk vertikal          |
| `gaussian_blur` | purple | —           | Background blur 18px + frame tajam di tengah         |
| `auto_reframe`  | blue   | —           | Zoom 1.5× + panning + dashed corner crop indicators  |

---

## ⚙️ Tweaks (panel toolbar)

Aktifkan dari toolbar atas. Pilihan yang tersedia:

- **Illustration style**: `geometric` · `diagram` · `glyph` — 3 gaya berbeda
  untuk SVG mini di kartu layout.
- **Top stepper** — toggle indikator langkah di topbar.
- **Fast processing (0.25×)** — percepat animasi processing untuk demo.
- **Jump to screen** — tombol pintas ke Home / Processing / Result tanpa
  menjalani flow normal.

---

## 📄 Data Contoh (mock dari Gemini)

```json
{
  "hook":     "Negosiasi gaji jangan asal jawab.",
  "caption":  "Tiga frasa singkat yang bikin HR langsung naikkan tawaran.",
  "hashtags": ["#KarirHack", "#NegosiasiGaji", "#TipsKerja"],
  "timestamps": { "start_seconds": 723, "end_seconds": 768 }
}
```

---

## 🔌 Kontrak Backend yang Direncanakan

### Routes
```
GET  /                          → ClipController@index
POST /clips                     → ClipController@store
GET  /clips/{clip}              → ClipController@show
POST /clips/{clip}/generate     → ClipController@generate
GET  /clips/{clip}/status       → ClipController@status (JSON polling)
```

### Schema `clips`
- `id` (ULID, primary)
- `youtube_url`, `video_id`
- `layout_mode` enum: `auto_magic` · `auto_split` · `gaussian_blur` · `auto_reframe`
- `status` enum: `pending` · `processing` · `done` · `failed`
- `gemini_hook`, `gemini_caption`, `gemini_hashtags` (json), `gemini_timestamps` (json)
- `thumbnail_url`
- timestamps + index pada `status`

### `.env`
```
GEMINI_API_KEY=your_key_here
GEMINI_API_URL=https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent
```

---

## 📋 To-do untuk Implementasi Laravel

- [ ] Migration `clips` + ULID trait di model `Clip`.
- [ ] `app/Helpers/YoutubeHelper.php` — `extractVideoId()` static method.
- [ ] `StoreClipRequest` form request untuk validasi.
- [ ] `app/Services/GeminiService.php` — `generateClipContent(Clip $clip): array`.
- [ ] `app/Jobs/ProcessClipJob.php` — dispatch ke queue, set status processing → done.
- [ ] Blade views: `layouts/app`, `clips/index`, `clips/show` — port dari prototype ini.
- [ ] Alpine.js polling 3s pada `clips/show` untuk status `pending` / `processing`.
- [ ] Download preview button — screenshot via `html-to-image` atau server-side render.
  

## Alur 
- user salin link
- user memilih tipe content
- user menginputkan jumlah berapa vidio yang akan diambil
- gemini memilihkan content yang paling bagus dengan waktu 60 detik sebanyak vidio yang 
- user bisa memilih content yang didowload
- sekalian buatkan caption dari vidio

## larangan
- jangan ubah function processWithAutoCaption
