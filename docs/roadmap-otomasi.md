# Roadmap: Clipper End-to-End Otomatis

Target: dari mencari video YouTube → potong jadi beberapa klip → subtitle → musik → auto upload TikTok, tanpa langkah manual (dengan opsi approval).

```
[Discovery] → [Download+Transcript] → [AI pilih klip] → [Render: cut+layout+subtitle+hook]
     → [Music] → [QC / Approval] → [Upload TikTok] → [Tracking performa]
```

## Fase 0 — Subtitle akurat (selesai)

Penyebab subtitle kadang cepat / pas / lambat:

1. **Cut tidak presisi** — `ffmpeg -ss … -c copy` mundur ke keyframe sebelumnya (0–5 dtk di video YouTube), jadi klip mulai lebih awal dari `start_seconds` dan subtitle telat dengan selisih berbeda-beda per klip.
   → Cut sekarang digabung ke pass `applyLayout` (re-encode, seek frame-accurate).
2. **Timing per kata ditebak rata** — durasi segmen dibagi rata ke jumlah kata, padahal ada jeda di tengah segmen.
   → Transcript sekarang menyimpan `words: [{start, end, text}]` per segmen (dari `tOffsetMs` caption otomatis YouTube, atau Groq `timestamp_granularities=word`). Cue disusun dari timestamp kata asli; hanya kata yang sedang diucapkan diberi warna highlight. Segmen tanpa `words` (caption manual / transcript lama) tetap pakai cara proporsional.
3. **Bug pembulatan waktu ASS** — `1.996s` jadi `0:00:01.100`. Diperbaiki.

Catatan: project yang transcript-nya sudah tersimpan sebelum perubahan ini tidak punya `words`; buat project baru untuk dapat timing per kata.

Opsional berikutnya: transkripsi ulang per klip + forced alignment (WhisperX / stable-ts) untuk akurasi maksimal.

## Fase 1 — Musik otomatis (selesai)

- Library musik bebas royalti di disk, satu folder per mood:
  ```
  storage/app/music/
    energetic/  chill/  inspiring/  dramatic/  funny/  sad/
  ```
  Format: mp3, m4a, aac, wav, ogg. Lokasi bisa diganti lewat `MUSIC_LIBRARY_PATH` di `.env`.
  Folder mood kosong → diambil dari track mana saja di library. Library kosong → musik dilewati (tercatat di log).
- Gemini mengisi `music_mood` per klip; user bisa pilih "Otomatis (AI)" atau paksa satu mood di langkah 5 studio.
- Track dipilih stabil per klip (render ulang = lagu yang sama), tersebar antar klip.
- `FFmpegService::mixMusic`: musik di-loop, fade in 1 dtk / fade out 1.5 dtk, di-*duck* (~10 dB) saat ada suara orang, video tidak di-encode ulang.
- Hanya pakai lagu yang kamu punya haknya (royalty-free / berlisensi). Sound trending TikTok **tidak bisa** dipasang via API.

Setelah pull: `php artisan migrate`.

## Fase 2 — Upload TikTok (draft / inbox, selesai)

Alur: klip selesai dirender → pilih akun TikTok → **Kirim ke TikTok (draft)** → video masuk inbox/notifikasi app TikTok → tambah sound trending & posting dari app.

Setup:
1. Buat app di developers.tiktok.com, aktifkan **Login Kit** dan **Content Posting API**, scope `user.info.basic` + `video.upload`.
2. TikTok mewajibkan redirect URI **HTTPS**. Di lokal pakai tunnel (mis. `ngrok http 8000` / `cloudflared tunnel`), lalu daftarkan `https://<tunnel>/tiktok/callback` di app TikTok dan buka studio lewat URL tunnel itu juga (supaya cookie login sama).
3. Isi `.env`:
   ```
   TIKTOK_CLIENT_KEY=...
   TIKTOK_CLIENT_SECRET=...
   TIKTOK_REDIRECT_URI=https://<tunnel>/tiktok/callback
   ```
4. Selama app masih Sandbox: tambahkan akun TikTok penguji sebagai *target user* di pengaturan sandbox.
5. `php artisan migrate` lalu `php artisan queue:restart`.

Teknis:
- `TikTokService`: OAuth v2 (`/v2/oauth/token/`), refresh otomatis sebelum access token habis, upload `inbox/video/init` + PUT per chunk (≤64 MB satu chunk, lebih besar dipecah 10 MB), cek `status/fetch`.
- Token disimpan terenkripsi (`tiktok_accounts`), satu user bisa menghubungkan beberapa akun.
- `UploadToTikTokJob` tidak di-retry otomatis (retry = draft dobel). Status: queued → uploading → processing → inbox / failed.
- Endpoint & format API ditulis dari dokumentasi v2 tanpa bisa dicek ulang dari environment ini; kalau TikTok mengembalikan error, pesan lengkapnya tampil di UI & log `clipper_jobs`.

Mode **Posting langsung (Direct Post)** — selesai:
- Aktifkan scope `video.publish` di app TikTok, set `TIKTOK_SCOPES=user.info.basic,video.upload,video.publish` di `.env`, lalu hubungkan ulang akun.
- Form mengikuti syarat TikTok: nama akun dari `creator_info`, pilihan privasi tanpa default, toggle komentar/duet/stitch (mati jika dimatikan creator), caption (diisi dari caption Gemini, bisa diedit), teks persetujuan musik.
- Sebelum app lolos audit TikTok, hanya privasi **Hanya saya** (`SELF_ONLY`) yang diterima.

## Fase 3 — Discovery otomatis + approval

- YouTube Data API: search per niche/keyword, whitelist channel, filter durasi, views/jam, ada caption.
- Scheduler Laravel tiap X jam; Gemini beri skor potensi.
- Antrian approval (approve/reject satu klik) sebelum upload.
- Perhatikan hak cipta: pakai channel yang mengizinkan clipping atau konten Creative Commons.

## Fase 4 — Autopilot & tracking

- Auto-approve jika skor di atas threshold.
- Tarik statistik views/likes → umpan balik ke prompt & pemilihan sumber.

## Infrastruktur

- SQLite → MySQL/Postgres (sudah ada workaround `database is locked`).
- Redis + Horizon untuk queue.
- State machine per klip: `discovered → analyzed → rendered → approved → uploaded`, retry per tahap.
