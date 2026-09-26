<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Autopilot — Clipper Studio</title>
<meta name="csrf-token" content="{{ csrf_token() }}" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
@vite('resources/css/studio.css')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
  .ap-wrap { max-width: 1080px; margin: 0 auto; padding: 32px 16px 80px; }
  .ap-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
  .ap-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; }
  .ap-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
  .ap-meta { font-size: 12px; color: var(--text-dim); font-family: 'JetBrains Mono', monospace; }
  .ap-video { width: 100%; aspect-ratio: 9/16; max-height: 420px; background: #000; border-radius: var(--radius-sm); object-fit: contain; }
  .ap-placeholder { width: 100%; aspect-ratio: 9/16; max-height: 420px; border-radius: var(--radius-sm); background: var(--surface-2); display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--text-muted); text-align: center; padding: 16px; }
  .ap-mini { width: auto; }
  .ap-inp { width: 100%; }
  .ap-num { width: 84px; }
  h1.ap-title { font-size: 26px; margin: 0 0 6px; }
  h2.ap-h2 { font-size: 18px; margin: 36px 0 12px; }
</style>
</head>
<body>
<div x-data="autopilot()" x-init="init()" x-cloak>

  <div class="topbar">
    <div class="brand">
      <div class="brand-mark"></div>
      Clipper <span style="color:var(--text-muted);font-weight:500">Studio</span>
    </div>
    <div class="topbar-nav">
      <a href="/">Studio</a>
      <a class="active" href="/autopilot">Autopilot</a>
    </div>
  </div>

  <div class="ap-wrap">
    <h1 class="ap-title">Autopilot</h1>
    <p style="color:var(--text-muted);margin:0;font-size:14px">
      Cari video baru dari channel atau keyword secara berkala, potong jadi klip otomatis, lalu review sebelum dikirim ke TikTok.
      Tampilan klip (layout, subtitle, hook, musik) mengikuti project manual terakhir kamu saat sumber ditambahkan.
    </p>

    {{-- ── Sources ─────────────────────────────────────────────── --}}
    <h2 class="ap-h2">Sumber video</h2>
    <div class="ap-card" style="margin-bottom:16px">
      <div class="ap-row" style="margin-bottom:12px">
        <div class="seg">
          <button :class="{ on: form.type === 'channel' }" @click="form.type = 'channel'" type="button">Channel</button>
          <button :class="{ on: form.type === 'keyword' }" @click="form.type = 'keyword'" type="button">Keyword</button>
        </div>
        <input class="field ap-inp" style="flex:1;min-width:220px" x-model="form.value"
               :placeholder="form.type === 'channel' ? '@namachannel atau https://youtube.com/@namachannel' : 'contoh: podcast bisnis indonesia'">
      </div>
      <div class="ap-row" style="font-size:13px;color:var(--text-muted)">
        <label>Cek tiap <input class="field ap-num" type="number" min="1" max="168" x-model.number="form.interval_hours"> jam</label>
        <label>Maks <input class="field ap-num" type="number" min="1" max="5" x-model.number="form.max_per_run"> video/cek</label>
        <label>Durasi video <input class="field ap-num" type="number" min="1" x-model.number="form.min_minutes"> –
          <input class="field ap-num" type="number" min="1" x-model.number="form.max_minutes"> menit</label>
        <button class="btn" @click="addSource()" :disabled="!form.value.trim()" style="margin-left:auto">Tambah sumber</button>
      </div>
      <div x-show="formError" style="margin-top:10px;font-size:13px;color:#f87171" x-text="formError"></div>
    </div>

    <div x-show="sources.length === 0" style="color:var(--text-dim);font-size:13px">Belum ada sumber.</div>
    <div class="ap-grid">
      <template x-for="s in sources" :key="s.id">
        <div class="ap-card">
          <div class="ap-row" style="justify-content:space-between">
            <span class="status-pill" x-text="s.type === 'channel' ? 'CHANNEL' : 'KEYWORD'"></span>
            <button class="tgl" :class="{ on: s.enabled }" @click="toggleSource(s)" type="button">
              <span class="tgl-track"></span><span class="tgl-text" x-text="s.enabled ? 'Aktif' : 'Nonaktif'"></span>
            </button>
          </div>
          <div style="font-weight:600;margin:10px 0 6px;word-break:break-all" x-text="s.value"></div>
          <div class="ap-meta" x-text="'tiap ' + s.interval_hours + ' jam · maks ' + s.max_per_run + ' video · ' + s.min_minutes + '–' + s.max_minutes + ' mnt'"></div>
          <div class="ap-meta" x-text="s.videos_found + ' video diproses · cek terakhir: ' + (s.last_run_at ? new Date(s.last_run_at).toLocaleString('id-ID') : 'belum')"></div>
          <div x-show="s.last_error" style="margin-top:8px;font-size:12px;color:#f87171" x-text="s.last_error"></div>
          <div class="ap-row" style="margin-top:12px">
            <button class="btn btn-secondary" @click="runSource(s)" type="button">Cek sekarang</button>
            <button class="btn btn-ghost" @click="deleteSource(s)" type="button">Hapus</button>
          </div>
        </div>
      </template>
    </div>

    {{-- ── Review queue ────────────────────────────────────────── --}}
    <h2 class="ap-h2">Antrian review</h2>
    <div class="ap-row" style="margin-bottom:12px;font-size:13px;color:var(--text-muted)">
      <template x-if="!tiktokConfigured">
        <span>Isi kredensial TikTok di <code>.env</code> untuk bisa approve ke TikTok.</span>
      </template>
      <template x-if="tiktokConfigured && accounts.length === 0">
        <span>Belum ada akun TikTok. <a href="/tiktok/connect" style="color:var(--teal)">Hubungkan akun</a></span>
      </template>
      <template x-if="accounts.length > 0">
        <div class="ap-row">
          <span>Kirim ke akun:</span>
          <div class="seg">
            <template x-for="a in accounts" :key="a.id">
              <button :class="{ on: accountId === a.id }" @click="accountId = a.id" type="button" x-text="a.display_name || ('Akun #' + a.id)"></button>
            </template>
          </div>
        </div>
      </template>
    </div>

    <div x-show="clips.length === 0" style="color:var(--text-dim);font-size:13px">Belum ada klip dari autopilot.</div>
    <div class="ap-grid">
      <template x-for="c in clips" :key="c.id">
        <div class="ap-card">
          <template x-if="c.preview_url">
            <video class="ap-video" :src="c.preview_url" controls preload="metadata"></video>
          </template>
          <template x-if="!c.preview_url">
            <div class="ap-placeholder" x-text="c.status === 'failed' ? ('Render gagal: ' + (c.error_msg || '')) : (c.review_status === 'rejected' ? 'Ditolak' : 'Sedang dirender…')"></div>
          </template>
          <div style="font-weight:600;margin:12px 0 4px" x-text="c.topic"></div>
          <div class="ap-meta" x-text="(c.video_title || c.youtube_url) + ' · ' + c.duration + ' dtk · potensi ' + c.viral_potential"></div>
          <div style="font-size:12.5px;color:var(--text-muted);margin-top:6px" x-text="c.reason"></div>

          <div class="ap-row" style="margin-top:12px" x-show="c.review_status === 'pending'">
            <button class="btn" type="button" @click="approve(c)"
                    :disabled="c.status !== 'done' || !accountId" x-text="'Approve → TikTok (draft)'"></button>
            <button class="btn btn-ghost" type="button" @click="reject(c)">Reject</button>
          </div>
          <div x-show="c.review_status !== 'pending'" style="margin-top:12px;font-size:13px"
               :style="c.tiktok_status === 'failed' ? 'color:#f87171' : 'color:var(--text-muted)'"
               x-text="reviewLabel(c)"></div>
          <div x-show="c.tiktok_status === 'failed' && c.tiktok_error" style="margin-top:4px;font-size:12px;color:var(--text-dim)" x-text="c.tiktok_error"></div>
          <div x-show="c.actionError" style="margin-top:6px;font-size:12px;color:#f87171" x-text="c.actionError"></div>
        </div>
      </template>
    </div>
  </div>
</div>

<script>
function autopilot() {
  const headers = () => ({
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
  });
  const api = async (url, opts = {}) => {
    const res = await fetch(url, { headers: headers(), ...opts });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
    return data;
  };

  return {
    sources: [], clips: [], accounts: [], accountId: null, tiktokConfigured: false,
    form: { type: 'channel', value: '', interval_hours: 6, max_per_run: 1, min_minutes: 5, max_minutes: 120 },
    formError: null, timer: null,

    async init() {
      await Promise.all([this.loadSources(), this.loadClips(), this.loadAccounts()]);
      // Rendering and uploads progress in the background; keep the page fresh
      this.timer = setInterval(() => { this.loadClips(); this.loadSources(); }, 10000);
    },
    async loadSources() { try { this.sources = await api('/api/autopilot/sources'); } catch (e) { console.error(e); } },
    async loadClips() {
      try {
        const errors = Object.fromEntries(this.clips.filter(c => c.actionError).map(c => [c.id, c.actionError]));
        this.clips = (await api('/api/autopilot/review')).map(c => ({ ...c, actionError: errors[c.id] || null }));
      } catch (e) { console.error(e); }
    },
    async loadAccounts() {
      try {
        const data = await api('/api/tiktok/accounts');
        this.tiktokConfigured = data.configured;
        this.accounts = data.accounts || [];
        this.accountId = this.accounts[0]?.id ?? null;
      } catch (e) { console.error(e); }
    },
    async addSource() {
      this.formError = null;
      try {
        await api('/api/autopilot/sources', { method: 'POST', body: JSON.stringify(this.form) });
        this.form.value = '';
        await this.loadSources();
      } catch (e) { this.formError = e.message; }
    },
    async toggleSource(s) {
      await api(`/api/autopilot/sources/${s.id}`, { method: 'PATCH', body: JSON.stringify({ enabled: !s.enabled }) });
      s.enabled = !s.enabled;
    },
    async runSource(s) {
      await api(`/api/autopilot/sources/${s.id}/run`, { method: 'POST' });
      await this.loadSources();
    },
    async deleteSource(s) {
      if (!confirm('Hapus sumber "' + s.value + '"? Klip yang sudah dibuat tetap ada.')) return;
      await api(`/api/autopilot/sources/${s.id}`, { method: 'DELETE' });
      await this.loadSources();
    },
    async approve(c) {
      c.actionError = null;
      try {
        await api(`/api/autopilot/review/${c.id}/approve`, { method: 'POST', body: JSON.stringify({ account_id: this.accountId }) });
        await this.loadClips();
      } catch (e) { c.actionError = e.message; }
    },
    async reject(c) {
      c.actionError = null;
      try {
        await api(`/api/autopilot/review/${c.id}/reject`, { method: 'POST' });
        await this.loadClips();
      } catch (e) { c.actionError = e.message; }
    },
    reviewLabel(c) {
      if (c.review_status === 'rejected') return '✕ Ditolak';
      return {
        queued: 'Disetujui · menunggu antrian upload…',
        uploading: 'Disetujui · mengupload ke TikTok…',
        processing: 'Disetujui · TikTok sedang memproses…',
        inbox: '✓ Terkirim ke inbox TikTok',
        published: '✓ Sudah diposting',
        failed: 'Upload gagal',
      }[c.tiktok_status] || 'Disetujui';
    },
  };
}
</script>
</body>
</html>
