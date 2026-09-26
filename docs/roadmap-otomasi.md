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
   → Transcript sekarang menyimpan `words: [{start, end, text}]` per segmen (dari `tOffsetMs` caption otomatis YouTube, atau Groq `timestamp_granularities=word`). Cue & karaoke `\k` disusun dari timestamp kata asli. Segmen tanpa `words` (caption manual / transcript lama) tetap pakai cara proporsional.
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

## Fase 2 — Upload TikTok

- TikTok Content Posting API: OAuth per akun, mode *Upload to Inbox* (draft) dulu, lalu *Direct Post*.
- Caption + hashtag dari Gemini (sudah ada di `GeminiService`).
- App yang belum diaudit TikTok hanya bisa posting private; ada batas post per hari. Hindari bot browser (risiko ban).

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
