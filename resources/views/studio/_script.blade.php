<script>
function studio() {
  const SESSION_KEY = 'clipper_studio_v1';
  const PERSIST_KEYS = [
    'step','maxReached','done','locked',
    'url','meta',
    'layout',
    'clipCount','durationMode','minDuration','maxDuration',
    'subtitleEnabled','subtitleFont','subtitleSize','subtitleTextColor','subtitleHighlight','subtitlePos','subtitleBg',
    'hookEnabled','hookText','hookAi','hookDuration','hookPos','hookTextColor','hookBg',
    'musicEnabled','musicMood','musicVolume',
    'generateStarted','generateProgress','generateLogIdx',
    'projectId','clips','selectedClip',
    'downloadUrl','resultHookText',
  ];

  const SAMPLE_SEGS = [
    { start: 0,   end: 2.2,  text: 'Setiap momen adalah kesempatan' },
    { start: 2.2, end: 4.4,  text: 'untuk mengubah segalanya' },
    { start: 4.4, end: 6.8,  text: 'mulai dari sekarang' },
    { start: 6.8, end: 9.0,  text: 'itu saja sudah cukup' },
  ];

  function buildWords(segs) {
    const out = [];
    let idx = 0;
    segs.forEach(seg => {
      const ws = seg.text.split(' ');
      const per = (seg.end - seg.start) / ws.length;
      ws.forEach((w, i) => out.push({ idx: idx++, w, start: seg.start + i * per, end: seg.start + (i + 1) * per, segStart: seg.start, segEnd: seg.end }));
    });
    return out;
  }

  const ALL_WORDS = buildWords(SAMPLE_SEGS);

  return {
    // ── state ──────────────────────────────────────────────────────────────
    step: 1, maxReached: 1, done: false, locked: false,
    sessionId: null, historyOpen: false, history: [],

    url: '', meta: null, loadingMeta: false, metaError: null,
    layout: 'reframe',
    clipCount: 3, durationMode: 'auto', minDuration: 20, maxDuration: 45,
    subtitleEnabled: true, subtitleFont: 'Montserrat', subtitleSize: 42,
    subtitleTextColor: '#ffffff', subtitleHighlight: '#facc15',
    subtitlePos: 'bottom', subtitleBg: 'semi',
    hookEnabled: true, hookText: 'Ingin lebih baik? Coba ini.', hookAi: false,
    hookDuration: 3, hookPos: 'center', hookTextColor: '#ffffff', hookBg: 'semi',
    musicEnabled: false, musicMood: '', musicVolume: 15,
    generateStarted: false, generateProgress: 0, generateLogIdx: 0, generateTimer: null,
    generateError: null, generateLogMsg: null,
    projectId: null,
    clips: [], selectedClip: null,
    renderingClipId: null, renderError: null, downloadUrl: null,
    resultHookText: 'Ingin lebih baik? Coba ini.',
    editHookOpen: false, draftHook: '', copied: '',
    clipCaption: '', clipHashtags: [], loadingCaption: false,
    tiktokConfigured: false, tiktokAccounts: [], tiktokAccountId: null,
    tiktokStatus: null, tiktokError: null, tiktokTimer: null,
    previewT: 0, previewTick: null,

    // ── constants ──────────────────────────────────────────────────────────
    STEPS: [
      { n:1, key:'link',     label:'Link' },
      { n:2, key:'layout',   label:'Layout' },
      { n:3, key:'clips',    label:'Clip' },
      { n:4, key:'subtitle', label:'Subtitle' },
      { n:5, key:'hook',     label:'Hook' },
      { n:6, key:'generate', label:'Generate' },
      { n:7, key:'pick',     label:'Pilih' },
    ],
    LAYOUTS: [
      { id:'reframe',  title:'Reframe Wajah', sub:'Face tracking',      color:'teal',   accent:'#2dd4bf', badge:{ text:'DISARANKAN', cls:'badge-teal' },   desc:'Video di-crop 9:16, wajah selalu di tengah lewat deteksi AI.',        good:'Talking head, vlog, monolog, edukasi' },
      { id:'gaussian', title:'Gaussian',      sub:'Blurred background', color:'purple', accent:'#a78bfa', badge:null,                                        desc:'Video 16:9 di tengah, area kosong diisi duplikat blur + zoom.',      good:'Gameplay, screen recording, tutorial, olahraga' },
    ],
    FONTS: ['Arial','Roboto','Montserrat','Oswald','Bebas Neue'],
    COLOR_PRESETS: ['#ffffff','#facc15','#2dd4bf','#a78bfa','#60a5fa','#fb7185','#f97316','#000000'],
    MUSIC_MOODS: [
      { id:'energetic', label:'Energik' }, { id:'chill', label:'Santai' }, { id:'inspiring', label:'Inspiratif' },
      { id:'dramatic', label:'Dramatis' }, { id:'funny', label:'Lucu' }, { id:'sad', label:'Sedih' },
    ],
    HL_PRESETS:    ['#facc15','#2dd4bf','#a78bfa','#60a5fa','#fb7185','#22c55e','#ffffff','#f97316'],
    STAGES: [
      { msg:'AnalyzeVideoJob di-dispatch ke queue',           dur:600  },
      { msg:'Mengunduh video via yt-dlp → storage/temp/',     dur:1400 },
      { msg:'Mengambil transcript YouTube (bahasa: id)',       dur:1000 },
      { msg:'Mengirim transcript ke Gemini · pilih N momen',  dur:1500 },
      { msg:'Menyusun segmen subtitle per kata',              dur:1100 },
      { msg:'Menyimpan hasil ke generated_clips',             dur:700  },
    ],
    SAMPLE_CLIPS: [
      { ranking:1, start_seconds:723,  end_seconds:768,  topic:'Aturan 2 menit yang mengubah segalanya',  reason:'Insight kuat + langsung bisa dipraktikkan.',  viral_potential:'tinggi', hook_text:'Kebiasaan gagal? Coba aturan 2 menit ini.' },
      { ranking:2, start_seconds:1490, end_seconds:1528, topic:'Kenapa motivasi selalu mengkhianatimu',    reason:'Momen emosional dengan pernyataan kontroversial.', viral_potential:'tinggi', hook_text:'Berhenti menunggu motivasi datang.' },
      { ranking:3, start_seconds:305,  end_seconds:340,  topic:'Lingkungan mengalahkan niat',             reason:'Tips praktis yang relatable.',                viral_potential:'sedang', hook_text:'Ubah ruanganmu, bukan dirimu.' },
      { ranking:4, start_seconds:1980, end_seconds:2012, topic:'Identitas di atas hasil',                 reason:'Pesan reflektif, engagement bagus.',          viral_potential:'sedang', hook_text:'Jangan kejar hasil, kejar identitas.' },
      { ranking:5, start_seconds:88,   end_seconds:116,  topic:'Mitos 21 hari membentuk kebiasaan',       reason:'Membongkar mitos populer.',                   viral_potential:'rendah', hook_text:'Lupakan angka 21 hari.' },
    ],
    SAMPLE_SEGS,
    SAMPLE_CAPTION: 'Aturan 2 menit yang bikin kebiasaanmu akhirnya nempel. Simpan biar nggak lupa 🔖\n\n#KebiasaanBaik #ProduktivitasHarian #SelfImprovement #MindsetJuara #TipsDisiplin',
    SAMPLE_HASHTAGS: ['#KebiasaanBaik','#ProduktivitasHarian','#SelfImprovement','#MindsetJuara','#TipsDisiplin'],
    sampleMeta: { channel:'Pikiran Jernih' },

    // ── getters ────────────────────────────────────────────────────────────
    get isValidUrl() {
      return /(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)[A-Za-z0-9_-]{6,}/.test(this.url);
    },
    get canNext() {
      if (this.step === 1) return !!this.meta;
      if (this.step === 2) return !!this.layout;
      if (this.step === 7) return !!this.selectedClip;
      return true;
    },
    get currentLayout() {
      return this.LAYOUTS.find(l => l.id === this.layout) || this.LAYOUTS[0];
    },
    get selectedClipObj() {
      return this.clips.find(c => c.ranking === this.selectedClip) || null;
    },
    get pvHookDur()   { return this.hookEnabled && this.hookText ? this.hookDuration : 0; },
    get pvSubTotal()  { return SAMPLE_SEGS.length ? SAMPLE_SEGS[SAMPLE_SEGS.length-1].end : 10.2; },
    get pvLoopTotal() { return this.pvHookDur + this.pvSubTotal; },
    get pvLt()        { return this.pvLoopTotal > 0 ? this.previewT % this.pvLoopTotal : 0; },
    get pvInHook()    { return this.pvLt < this.pvHookDur; },
    get pvSubT()      { return Math.max(0, this.pvLt - this.pvHookDur); },
    get pvActiveWord() {
      if (this.pvInHook) return null;
      const t = this.pvSubT;
      return ALL_WORDS.find(w => t >= w.start && t < w.end) || ALL_WORDS[0];
    },
    get pvSegWords() {
      const aw = this.pvActiveWord;
      if (!aw) return [];
      return ALL_WORDS.filter(w => w.segStart === aw.segStart).map(w => ({ ...w, active: w.start === aw.start }));
    },
    get pvSubWordsAlways() {
      const t = this.pvSubT;
      const aw = ALL_WORDS.find(w => t >= w.start && t < w.end) || ALL_WORDS[0];
      if (!aw) return [];
      return ALL_WORDS.filter(w => w.segStart === aw.segStart).map(w => ({ ...w, active: w.start === aw.start }));
    },
    get pvProgress() {
      return this.pvLoopTotal > 0 ? (this.pvLt / this.pvLoopTotal) * 100 : 0;
    },

    // ── preview style helpers ──────────────────────────────────────────────
    pvSubStyle() {
      const base = 'position:absolute;left:14px;right:14px;z-index:6;text-align:center;';
      const pos  = this.subtitlePos;
      if (pos === 'top')    return base + 'top:14%;';
      if (pos === 'center') return base + 'top:50%;transform:translateY(-50%);';
      return base + 'bottom:13%;';
    },
    pvSubBoxStyle() {
      const bgMap = { none:'transparent', semi:'rgba(0,0,0,.6)', full:'rgba(0,0,0,.9)' };
      const bg = bgMap[this.subtitleBg] || 'transparent';
      const fontStack = { Arial:'Arial,Helvetica,sans-serif', Roboto:"'Roboto',sans-serif", Montserrat:"'Montserrat',sans-serif", Oswald:"'Oswald',sans-serif", 'Bebas Neue':"'Bebas Neue',sans-serif" };
      const ff = fontStack[this.subtitleFont] || 'sans-serif';
      const sz = Math.round(this.subtitleSize * 0.34);
      const bns = this.subtitleBg === 'none';
      return `display:inline-block;background:${bg};padding:${bns ? '0' : '6px 10px'};border-radius:${this.subtitleBg === 'full' ? '0' : '8px'};font-family:${ff};font-size:${sz}px;font-weight:800;line-height:1.2;letter-spacing:-.01em;text-shadow:${bns ? '0 2px 8px rgba(0,0,0,.85)' : 'none'};text-transform:${this.subtitleFont === 'Bebas Neue' ? 'uppercase' : 'none'}`;
    },
    pvHookPosStyle() {
      const pos = this.hookPos;
      if (pos === 'top')    return 'top:18%;';
      if (pos === 'bottom') return 'bottom:20%;';
      return 'top:50%;transform:translateY(-50%);';
    },
    pvHookBoxStyle() {
      const bg = { none:'transparent', semi:'rgba(0,0,0,.6)', full:'transparent' }[this.hookBg] || 'transparent';
      const ts = this.hookBg === 'none' ? '0 2px 10px rgba(0,0,0,.8)' : 'none';
      return `display:inline-block;background:${bg};padding:${this.hookBg === 'none' ? '0' : '10px 14px'};border-radius:10px;color:${this.hookTextColor};font-weight:800;font-size:23px;line-height:1.15;letter-spacing:-.02em;text-shadow:${ts}`;
    },

    // ── persist: session ───────────────────────────────────────────────────
    saveSession() {
      const state = { sessionId: this.sessionId };
      PERSIST_KEYS.forEach(k => state[k] = this[k]);
      try { localStorage.setItem(SESSION_KEY, JSON.stringify(state)); } catch {}
    },
    loadSession() {
      try {
        const raw = localStorage.getItem(SESSION_KEY);
        if (!raw) { this.sessionId = this._uuid(); return; }
        const s = JSON.parse(raw);
        PERSIST_KEYS.forEach(k => { if (k in s) this[k] = s[k]; });
        this.sessionId = s.sessionId || this._uuid();
      } catch { this.sessionId = this._uuid(); }
    },
    clearSession() {
      try { localStorage.removeItem(SESSION_KEY); } catch {}
      this.sessionId = this._uuid();
    },
    watchAll() {
      PERSIST_KEYS.forEach(k => this.$watch(k, () => this.saveSession()));
    },

    // ── history: DB-backed ─────────────────────────────────────────────────
    async loadHistory() {
      try {
        const res = await fetch('/api/projects', {
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
        if (!res.ok) return;
        const data = await res.json();
        this.history = data
          .filter(p => p.status !== 'failed')
          .map(p => {
            const st = p.status === 'done'
              ? (p.rendered_clip_id ? 'rendered' : 'generated')
              : 'processing';
            return {
              id:             p.project_id,
              projectId:      p.project_id,
              status:         st,
              savedAt:        p.created_at,
              title:          p.title || 'Menganalisis…',
              thumbnailUrl:   p.thumbnail_url || '',
              layout:         p.layout_type,
              renderedClipId: p.rendered_clip_id || null,
              downloadUrl:    p.rendered_clip_id ? `/api/clips/${p.rendered_clip_id}/download` : null,
            };
          });
      } catch {}
    },
    async deleteHistoryItem(id) {
      this.history = this.history.filter(h => h.id !== id);
      try {
        await fetch(`/api/projects/${id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
      } catch {}
    },
    async clearAllHistory() {
      const ids = this.history.map(h => h.id);
      this.history = [];
      await Promise.all(ids.map(id =>
        fetch(`/api/projects/${id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        }).catch(() => {})
      ));
    },
    async restoreFromHistory(item) {
      if (this.generateTimer) clearTimeout(this.generateTimer);
      Object.assign(this, {
        step: 6, maxReached: 6, done: false, locked: true,
        generateStarted: true, generateProgress: 100,
        generateLogIdx: this.STAGES.length,
        generateError: null, generateLogMsg: null,
        projectId: item.projectId,
        meta: { title: item.title, thumbnailUrl: item.thumbnailUrl, durationSeconds: 0, channel: '', language: '' },
        clips: [], selectedClip: null,
        downloadUrl: null, renderingClipId: null,
        clipCaption: '', clipHashtags: [],
      });
      this.historyOpen = false;

      if (item.status === 'processing') {
        this.resumeGeneratePolling(item.projectId);
        return;
      }

      try {
        const res = await fetch(`/api/projects/${item.projectId}/status`, {
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
        const data = await res.json();
        this.clips = (data.clips || []).map(c => ({
          ranking: c.ranking, start_seconds: c.start_seconds, end_seconds: c.end_seconds,
          topic: c.topic, reason: c.reason, viral_potential: c.viral_potential,
          hook_text: c.hook_text, id: c.id,
        }));
      } catch {}

      // Auto-select the rendered clip so caption can be fetched on re-entry
      if (item.renderedClipId) {
        const rendered = this.clips.find(c => c.id === item.renderedClipId);
        if (rendered) this.selectedClip = rendered.ranking;
      }

      this.step = 7;
      this.maxReached = 7;

      if (item.status === 'rendered' && item.downloadUrl) {
        this.downloadUrl = item.downloadUrl;
        this.done = true;
      }

      this.saveSession();
    },

    // ── resume on refresh ──────────────────────────────────────────────────
    resumeIfNeeded() {
      if (this.generateStarted && this.projectId && this.clips.length === 0 && this.step === 6) {
        this.resumeGeneratePolling(this.projectId);
      }
    },

    // ── navigation ─────────────────────────────────────────────────────────
    goStep(n) {
      if (this.locked && n < this.step) return;
      this.done = false;
      this.step = n;
      this.maxReached = Math.max(this.maxReached, n);
    },
    next() { this.goStep(Math.min(7, this.step + 1)); },
    back() {
      if (this.done) { this.done = false; return; }
      if (this.locked) return;
      this.step = Math.max(1, this.step - 1);
    },

    // ── step 1: metadata ───────────────────────────────────────────────────
    async processLink() {
      if (!this.isValidUrl) return;
      this.loadingMeta = true;
      this.metaError = null;
      try {
        const res = await fetch('/api/meta?' + new URLSearchParams({ url: this.url }), {
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (!res.ok) { this.metaError = data.error || data.message || `HTTP ${res.status} — Gagal mengambil metadata.`; return; }
        this.meta = {
          title: data.title,
          durationSeconds: data.duration_seconds,
          channel: data.channel,
          language: data.language,
          thumbnailUrl: data.thumbnail_url,
        };
      } catch (e) {
        this.metaError = 'Koneksi gagal. Coba lagi.';
      } finally {
        this.loadingMeta = false;
      }
    },

    // ── step 6: generate + polling ─────────────────────────────────────────
    async startGenerate() {
      this.generateStarted = true;
      this.generateProgress = 5;
      this.generateLogIdx = 1;
      this.generateError = null;

      let projectId;
      try {
        const res = await fetch('/api/projects', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
          },
          body: JSON.stringify({
            youtube_url: this.url,
            layout_type: this.layout,
            clip_count: this.clipCount,
            duration_mode: this.durationMode,
            min_duration: this.durationMode === 'manual' ? this.minDuration : null,
            max_duration: this.durationMode === 'manual' ? this.maxDuration : null,
            subtitle: {
              enabled: this.subtitleEnabled,
              font_family: this.subtitleFont,
              font_size: this.subtitleSize,
              text_color: this.subtitleTextColor,
              highlight_color: this.subtitleHighlight,
              position: this.subtitlePos,
              background_style: this.subtitleBg,
            },
            hook: {
              enabled: this.hookEnabled,
              hook_text: this.hookAi ? null : this.hookText,
              is_ai_generated: this.hookAi,
              duration_seconds: this.hookDuration,
              position: this.hookPos,
              text_color: this.hookTextColor,
              background_style: this.hookBg,
            },
            music: {
              enabled: this.musicEnabled,
              mood: this.musicMood || null,
              volume: this.musicVolume,
            },
          }),
        });
        const data = await res.json();
        if (!res.ok) { this.generateError = data.message || 'Gagal membuat project.'; this.generateStarted = false; return; }
        projectId = data.project_id;
        this.projectId = projectId;
        this.locked = true;
      } catch (e) {
        this.generateError = 'Koneksi gagal saat membuat project.';
        this.generateStarted = false;
        return;
      }

      this.resumeGeneratePolling(projectId);
    },

    resumeGeneratePolling(projectId) {
      const TOTAL_STAGES = this.STAGES.length;
      const poll = async () => {
        try {
          const res = await fetch(`/api/projects/${projectId}/status`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
          });

          if (res.status === 404) {
            // Project hilang dari DB — reset session
            this.restart();
            return;
          }

          if (!res.ok) {
            this.generateError = `Server error (${res.status}). Coba refresh.`;
            this.generateStarted = false;
            return;
          }

          const data = await res.json();

          if (data.status === 'failed') {
            this.generateError = data.error_msg || 'Analisis gagal.';
            this.generateStarted = false;
            return;
          }

          if (data.progress_stage > 0) {
            this.generateLogIdx  = data.progress_stage;
            this.generateLogMsg  = data.progress_message || null;
            this.generateProgress = Math.round((data.progress_stage / TOTAL_STAGES) * 100);
          }

          if (data.status === 'done') {
            this.generateProgress = 100;
            this.generateLogIdx = TOTAL_STAGES;
            this.clips = (data.clips || []).map(c => ({
              ranking:         c.ranking,
              start_seconds:   c.start_seconds,
              end_seconds:     c.end_seconds,
              topic:           c.topic,
              reason:          c.reason,
              viral_potential: c.viral_potential,
              hook_text:       c.hook_text,
              id:              c.id,
            }));
            setTimeout(() => this.goStep(7), 600);
            return;
          }

          this.generateTimer = setTimeout(poll, 3000);
        } catch (e) {
          this.generateTimer = setTimeout(poll, 3000);
        }
      };
      this.generateTimer = setTimeout(poll, 3000);
    },

    // ── step 7: render + polling ───────────────────────────────────────────
    async renderClip() {

      if (!this.selectedClipObj) return;
      const clipId = this.selectedClipObj.id;
      this.renderingClipId = clipId;
      this.renderError = null;

      try {
        const res = await fetch(`/api/clips/${clipId}/render`, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
        const startData = await res.json();
        if (!res.ok) {
          this.renderError = startData.message || `Gagal memulai render (HTTP ${res.status}).`;
          this.renderingClipId = null;
          return;
        }
        // // Clip already done — set downloadUrl langsung tanpa polling
        // if (startData.status === 'done') {
        //   const stRes = await fetch(`/api/clips/${clipId}/render-status`, {
        //     headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        //   });
        //   const stData = await stRes.json();
        //   this.downloadUrl = stData.download_url;
        //   this.done = true;
        //   this.renderingClipId = null;
        //   return;
        // }
      } catch (e) {
        this.renderError = 'Koneksi gagal.'; return;
      }

      this.resultHookText = this.hookAi ? (this.selectedClipObj.hook_text || this.hookText) : this.hookText;

      const pollRender = async () => {
        try {
          const res = await fetch(`/api/clips/${clipId}/render-status`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
          });
          console.log(res);
          const data = await res.json();
          if (data.status === 'done') {
            this.downloadUrl = data.download_url;
            this.done = true;
            this.renderingClipId = null;
            return;
          }
          if (data.status === 'failed') {
            this.renderError = data.error_msg || 'Render gagal.';
            this.renderingClipId = null;
            return;
          }
          setTimeout(pollRender, 3000);
        } catch (e) {
          setTimeout(pollRender, 3000);
        }
      };
      setTimeout(pollRender, 3000);
    },

    // ── utilities ──────────────────────────────────────────────────────────
    fmtClock(s) {
      s = Math.round(s);
      return String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
    },
    fmtDate(iso) {
      const d = new Date(iso), now = new Date(), diff = now - d;
      if (diff < 60000)    return 'Baru saja';
      if (diff < 3600000)  return Math.floor(diff / 60000) + ' menit lalu';
      if (diff < 86400000) return Math.floor(diff / 3600000) + ' jam lalu';
      return Math.floor(diff / 86400000) + ' hari lalu';
    },
    copyText(key, text) {
      try { navigator.clipboard?.writeText(text); } catch {}
      this.copied = key;
      setTimeout(() => { this.copied = ''; }, 1200);
    },
    // ── TikTok upload (inbox / draft) ──────────────────────────────────────
    get tiktokBusy() { return ['queued', 'uploading', 'processing'].includes(this.tiktokStatus); },
    get tiktokStatusLabel() {
      return {
        queued:     'Menunggu antrian…',
        uploading:  'Mengupload video ke TikTok…',
        processing: 'TikTok sedang memproses video…',
        inbox:      '✓ Terkirim ke inbox TikTok — buka app TikTok untuk menambah sound & posting.',
        published:  '✓ Sudah diposting di TikTok.',
        failed:     'Upload gagal.',
      }[this.tiktokStatus] || '';
    },
    async loadTiktokAccounts() {
      try {
        const res = await fetch('/api/tiktok/accounts', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        this.tiktokConfigured = data.configured;
        this.tiktokAccounts = data.accounts || [];
        if (!this.tiktokAccounts.some(a => a.id === this.tiktokAccountId)) {
          this.tiktokAccountId = this.tiktokAccounts[0]?.id ?? null;
        }
      } catch (e) { console.error('loadTiktokAccounts', e); }
    },
    async uploadToTiktok() {
      const clipId = this.selectedClipObj?.id;
      if (!clipId || !this.tiktokAccountId) return;
      this.tiktokError = null;
      try {
        const res = await fetch(`/api/clips/${clipId}/tiktok`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json', 'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
          },
          body: JSON.stringify({ account_id: this.tiktokAccountId }),
        });
        const data = await res.json();
        if (!res.ok) { this.tiktokStatus = 'failed'; this.tiktokError = data.message || 'Gagal memulai upload.'; return; }
        this.applyTiktokStatus(data);
      } catch (e) {
        this.tiktokStatus = 'failed'; this.tiktokError = 'Koneksi gagal.';
      }
    },
    async fetchTiktokStatus() {
      const clipId = this.selectedClipObj?.id;
      if (!clipId) return;
      try {
        const res = await fetch(`/api/clips/${clipId}/tiktok-status`, { headers: { 'Accept': 'application/json' } });
        if (res.ok) this.applyTiktokStatus(await res.json());
      } catch (e) { console.error('fetchTiktokStatus', e); }
    },
    applyTiktokStatus(data) {
      if (data.clip_id !== this.selectedClipObj?.id) return;
      this.tiktokStatus = data.status;
      this.tiktokError = data.error;
      clearTimeout(this.tiktokTimer);
      if (this.tiktokBusy) this.tiktokTimer = setTimeout(() => this.fetchTiktokStatus(), 4000);
    },
    async fetchCaption() {
      const clipId = this.selectedClipObj?.id;
      if (!clipId) return;
      this.loadingCaption = true;
      try {
        const res = await fetch(`/api/clips/${clipId}/caption`, {
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
        const data = await res.json();
        if (!res.ok) {
          console.error('fetchCaption error', res.status, data);
          return;
        }
        this.clipCaption  = data.caption  || '';
        this.clipHashtags = data.hashtags || [];
      } catch (e) {
        console.error('fetchCaption exception', e);
      }
      finally { this.loadingCaption = false; }
    },
    _uuid() {
      return crypto?.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now().toString(36);
    },
    restart() {
      if (this.generateTimer) clearTimeout(this.generateTimer);
      this.clearSession();
      Object.assign(this, {
        step:1, maxReached:1, done:false, locked:false,
        url:'', meta:null, metaError:null,
        layout:'reframe',
        clipCount:3, durationMode:'auto', minDuration:20, maxDuration:45,
        generateStarted:false, generateProgress:0, generateLogIdx:0, generateError:null, generateLogMsg:null,
        projectId:null,
        clips:[], selectedClip:null,
        renderingClipId:null, renderError:null, downloadUrl:null,
        clipCaption:'', clipHashtags:[],
      });
      this.previewT = 0;
    },

    // ── layout illustrations ───────────────────────────────────────────────
    layoutIllu(id, color) {
      const c = color;
      const wrap = (inner) => `<svg viewBox="0 0 96 96" width="100" height="100"><rect x="33" y="6" width="30" height="84" rx="6" fill="none" stroke="#34343f" stroke-width="1"/>${inner}</svg>`;
      if (id === 'reframe')  return wrap(`<rect x="35" y="8" width="26" height="80" rx="4" fill="#1a1a21"/><rect x="26" y="40" width="44" height="16" rx="1" fill="none" stroke="#34343f" stroke-width=".8" stroke-dasharray="2 2"/><rect x="40" y="34" width="16" height="28" rx="2" fill="none" stroke="${c}" stroke-width="1.5"/><path d="M40 34 L37 34 M40 34 L40 37" stroke="${c}" stroke-width="1.5"/><path d="M56 34 L59 34 M56 34 L56 37" stroke="${c}" stroke-width="1.5"/><circle cx="48" cy="44" r="4" fill="${c}"/><path d="M43 56 Q48 50 53 56" fill="none" stroke="${c}" stroke-width="1.4"/>`);
      if (id === 'gaussian') return wrap(`<defs><filter id="gf"><feGaussianBlur stdDeviation="3.5"/></filter><clipPath id="gc"><rect x="35" y="8" width="26" height="80" rx="4"/></clipPath><clipPath id="gcs"><rect x="35" y="40" width="26" height="16"/></clipPath></defs><rect x="35" y="8" width="26" height="80" rx="4" fill="#0d0d12"/><g clip-path="url(#gc)"><circle cx="41" cy="15" r="15" fill="${c}" opacity=".75" filter="url(#gf)"/><circle cx="59" cy="26" r="11" fill="${c}" opacity=".5" filter="url(#gf)"/><circle cx="46" cy="76" r="15" fill="${c}" opacity=".7" filter="url(#gf)"/><circle cx="59" cy="67" r="10" fill="${c}" opacity=".45" filter="url(#gf)"/></g><rect x="35" y="40" width="26" height="16" fill="#0a0a10"/><g clip-path="url(#gcs)"><path d="M35 56 L40 49 L44 52.5 L48.5 43 L53 50 L57 46 L61 56Z" fill="${c}" opacity=".35"/><rect x="35" y="40" width="26" height="6" fill="${c}" opacity=".07"/></g><rect x="35" y="40" width="26" height="16" fill="none" stroke="${c}" stroke-width="1" opacity=".85"/><line x1="35" y1="40" x2="61" y2="40" stroke="rgba(255,255,255,.2)" stroke-width=".7" stroke-dasharray="2 1.5"/><line x1="35" y1="56" x2="61" y2="56" stroke="rgba(255,255,255,.2)" stroke-width=".7" stroke-dasharray="2 1.5"/>`);
      return '';
    },

    // ── layout background for phone preview ────────────────────────────────
    layoutBg(id) {
      const thumb = (v) => {
        const hues = [205, 280, 160, 30];
        const h = hues[v % 4], h2 = (h + 50) % 360;
        const l1 = `oklch(0.34 0.07 ${h})`, l2 = `oklch(0.18 0.04 ${h2})`;
        return `<div style="width:100%;height:100%;background:linear-gradient(135deg,${l1},${l2});position:relative"><div style="position:absolute;inset:0;background:repeating-linear-gradient(-22deg,transparent,transparent 11px,rgba(255,255,255,.035) 11px,rgba(255,255,255,.035) 22px)"></div></div>`;
      };
      if (id === 'reframe')  return `<div style="position:absolute;inset:0;overflow:hidden"><div style="width:100%;height:100%;transform:scale(1.35);transform-origin:center 42%">${thumb(0)}</div></div>`;
      if (id === 'gaussian') return `<div style="position:absolute;inset:0;filter:blur(22px) saturate(2);transform:scale(1.5)">${thumb(1)}</div><div style="position:absolute;inset:0;background:rgba(0,0,0,.3)"></div><div style="position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);aspect-ratio:16/9;overflow:hidden;outline:1px solid rgba(255,255,255,.28);box-shadow:0 0 0 1px rgba(0,0,0,.6),0 8px 30px rgba(0,0,0,.75)">${thumb(1)}</div>`;
      return '';
    },

    // ── init ───────────────────────────────────────────────────────────────
    async init() {
      this.loadSession();
      this.watchAll();
      this.resumeIfNeeded();
      this.previewTick = setInterval(() => { this.previewT += 0.1; }, 100);
      await this.loadHistory();
      // Clear caption when done resets (new generation started)
      this.$watch('done', val => {
        if (!val) { this.clipCaption = ''; this.clipHashtags = []; return; }
        if (this.selectedClipObj) this.fetchCaption();
      });
      // Re-fetch caption when user switches to a different clip
      this.$watch('selectedClip', () => {
        this.clipCaption = ''; this.clipHashtags = [];
        this.tiktokStatus = null; this.tiktokError = null;
        if (this.done && this.selectedClipObj) { this.fetchCaption(); this.fetchTiktokStatus(); }
      });
      this.$watch('done', val => { if (val && this.selectedClipObj) this.fetchTiktokStatus(); });

      // Back from TikTok OAuth: /?tiktok=connected|denied|error|…
      const tiktokResult = new URLSearchParams(location.search).get('tiktok');
      if (tiktokResult) {
        history.replaceState(null, '', location.pathname);
        if (tiktokResult !== 'connected') alert('Gagal menghubungkan TikTok (' + tiktokResult + ').');
      }
      await this.loadTiktokAccounts();
      if (this.done && this.selectedClipObj) this.fetchTiktokStatus();
      // If restored from session and already done
      if (this.done && this.selectedClipObj && !this.clipCaption) this.fetchCaption();
    },
  };
}
</script>
