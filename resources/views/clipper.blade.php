<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clipper — YouTube Shorts Maker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://www.youtube.com/iframe_api"></script>
    <style>
        :root {
            --bg:      #0a0a0c;
            --surface: #131318;
            --sf2:     #1a1a22;
            --border:  #1e1e2a;
            --text:    #f5f5f7;
            --muted:   #8a8a95;
            --teal:    #2dd4bf;
            --yellow:  #facc15;
            --purple:  #a78bfa;
            --blue:    #60a5fa;
            --red:     #f87171;
            --green:   #4ade80;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; line-height: 1.5; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 3rem 1.25rem 6rem; }

        /* Header */
        .hdr { margin-bottom: 2.5rem; }
        .hdr h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; }
        .hdr p { color: var(--muted); font-size: 0.875rem; margin-top: 0.2rem; }

        /* Card */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.5rem; margin-bottom: 1.25rem; }
        .card-title { font-size: 0.72rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 1rem; }
        .divider { height: 1px; background: var(--border); margin: 1.1rem 0; }

        /* Inputs */
        input[type=text], input[type=url], input[type=number], select {
            width: 100%; padding: 0.68rem 0.9rem;
            background: var(--bg); border: 1px solid var(--border); border-radius: 9px;
            color: var(--text); font-family: inherit; font-size: 0.9rem; outline: none; transition: border-color 0.15s;
        }
        input:focus, select:focus { border-color: var(--teal); }
        input.ok { border-color: var(--green); }
        input.bad { border-color: var(--red); }
        label { display: block; font-size: 0.73rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.35rem; }
        .field { margin-bottom: 0.9rem; }
        .field:last-child { margin-bottom: 0; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.65rem 1.2rem; border-radius: 9px; border: none; font-family: inherit; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: opacity 0.15s, transform 0.1s; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn:not(:disabled):active { transform: scale(0.98); }
        .btn-primary { background: var(--teal); color: #0a0a0c; width: 100%; padding: 0.8rem; font-size: 0.95rem; }
        .btn-primary:not(:disabled):hover { opacity: 0.88; }
        .btn-ghost { background: var(--sf2); color: var(--muted); border: 1px solid var(--border); }
        .btn-ghost:not(:disabled):hover { color: var(--text); }
        .btn-green { background: var(--green); color: #0a0a0c; }
        .btn-sm { padding: 0.4rem 0.85rem; font-size: 0.8rem; }

        /* YouTube player */
        .player-wrap { position: relative; padding-top: 56.25%; background: #000; border-radius: 9px; overflow: hidden; margin-top: 1rem; }
        .player-wrap iframe { position: absolute; inset: 0; width: 100%; height: 100%; }

        /* Layout grid */
        .layout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; }
        .lc {
            position: relative; background: var(--bg); border: 2px solid var(--border); border-radius: 12px;
            padding: 1rem; cursor: pointer; transition: border-color 0.15s, background 0.15s;
        }
        .lc:hover { border-color: #333; background: var(--sf2); }
        .lc.sel { background: var(--sf2); }
        .lc .dot { width: 9px; height: 9px; border-radius: 50%; margin-bottom: 0.55rem; }
        .lc .lname { font-size: 0.875rem; font-weight: 600; }
        .lc .ldesc { font-size: 0.75rem; color: var(--muted); margin-top: 0.15rem; }
        .lc .lbadge { position: absolute; top: 0.55rem; right: 0.55rem; font-size: 0.62rem; font-weight: 700; padding: 0.12rem 0.4rem; border-radius: 20px; letter-spacing: 0.04em; }
        .lc .lcheck { position: absolute; bottom: 0.55rem; right: 0.7rem; font-size: 0.85rem; }

        /* Count */
        .cnt-row { display: flex; gap: 0.5rem; align-items: center; }
        .cnt-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: border-color 0.15s; }
        .cnt-btn:hover { border-color: var(--teal); }
        .cnt-num { font-family: 'JetBrains Mono', monospace; font-size: 1.1rem; font-weight: 600; width: 38px; text-align: center; }

        /* Caption toggles */
        .cap-opts { display: flex; gap: 0.5rem; }
        .cap-tog { flex: 1; padding: 0.55rem; border-radius: 8px; border: 1.5px solid var(--border); background: var(--bg); color: var(--muted); font-size: 0.8rem; font-weight: 600; cursor: pointer; text-align: center; transition: border-color 0.15s, color 0.15s; }
        .cap-tog.on { border-color: var(--teal); color: var(--teal); }

        /* Analyzing */
        .an-wrap { text-align: center; padding: 1.75rem 0; }
        .spinner { width: 42px; height: 42px; border: 3px solid var(--border); border-top-color: var(--teal); border-radius: 50%; margin: 0 auto 1.1rem; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .an-label { font-size: 0.875rem; color: var(--muted); }
        .an-label strong { color: var(--text); display: block; margin-bottom: 0.2rem; }

        /* Clip cards */
        .clip-card { background: var(--bg); border: 2px solid var(--border); border-radius: 12px; padding: 1.25rem; margin-bottom: 0.75rem; transition: border-color 0.15s; }
        .clip-card.sel-c { border-color: var(--teal); }
        .clip-card.done-c { border-color: var(--green); }
        .clip-card.fail-c { border-color: var(--red); }
        .cc-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.85rem; }
        .cc-num { font-size: 0.68rem; font-weight: 700; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-bottom: 0.2rem; }
        .cc-hook { font-size: 0.975rem; font-weight: 700; color: var(--text); line-height: 1.35; flex: 1; }
        .cc-caption { font-size: 0.82rem; color: var(--muted); line-height: 1.55; margin-bottom: 0.75rem; }
        .tags { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-bottom: 0.85rem; }
        .tag { font-family: 'JetBrains Mono', monospace; font-size: 0.68rem; padding: 0.18rem 0.5rem; background: var(--sf2); border-radius: 20px; color: var(--teal); }
        .cc-times { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-bottom: 0.85rem; }
        .cc-times input[type=number] { font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; padding: 0.42rem 0.6rem; }
        .thint { font-size: 0.68rem; color: var(--muted); margin-top: 0.2rem; font-family: 'JetBrains Mono', monospace; }
        .cc-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; }
        .chk-lbl { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.83rem; }
        .chk-lbl input { width: 16px; height: 16px; accent-color: var(--teal); cursor: pointer; }

        /* Pills */
        .pill { font-size: 0.7rem; font-weight: 700; padding: 0.22rem 0.6rem; border-radius: 20px; font-family: 'JetBrains Mono', monospace; letter-spacing: 0.03em; white-space: nowrap; }
        .p-ready      { background: #12122a; color: #8888ff; }
        .p-pending    { background: #12122a; color: #8888ff; }
        .p-processing { background: #1a1400; color: var(--yellow); animation: blink 1.2s ease-in-out infinite; }
        .p-done       { background: #091809; color: var(--green); }
        .p-failed     { background: #190808; color: var(--red); }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.55} }

        /* Gen bar */
        .gen-bar { position: sticky; bottom: 1.25rem; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 0.9rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.25rem; }
        .gen-info { font-size: 0.83rem; color: var(--muted); }
        .gen-info strong { color: var(--text); }

        /* Misc */
        .err { color: var(--red); font-size: 0.8rem; margin-top: 0.4rem; }
        .reset-lnk { font-size: 0.8rem; color: var(--muted); cursor: pointer; text-decoration: underline; }
        .reset-lnk:hover { color: var(--text); }
        .results-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .results-hdr span:first-child { font-size: 0.875rem; font-weight: 600; }
        [x-cloak] { display: none !important; }
        .fade { animation: fd 0.22s ease; }
        @keyframes fd { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body>
<div class="wrap" x-data="clipper()" x-init="init()" x-cloak>

    <div class="hdr">
        <h1>Clipper</h1>
        <p>YouTube Shorts — segmen terbaik direkomendasikan Gemini AI</p>
    </div>

    <!-- URL Card -->
    <div class="card fade">
        <div class="card-title">Video URL</div>
        <div class="field">
            <input
                type="url"
                x-model="url"
                :class="url ? (videoId ? 'ok' : 'bad') : ''"
                placeholder="https://youtube.com/watch?v=..."
                @input.debounce.400ms="onUrlChange()"
            >
            <div class="err" x-show="url && !videoId">URL YouTube tidak valid.</div>
        </div>

        <template x-if="videoId">
            <div class="fade">
                <div class="player-wrap">
                    <div id="yt-player"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Layout Mode (after URL) -->
    <template x-if="videoId && step !== 'analyzing' && step !== 'results'">
        <div class="card fade">
            <div class="card-title">Layout Mode</div>
            <div class="layout-grid">
                <template x-for="l in layouts" :key="l.id">
                    <div
                        class="lc"
                        :class="{ sel: selectedLayout === l.id }"
                        :style="selectedLayout === l.id ? `border-color: ${l.color}` : ''"
                        @click="selectedLayout = l.id"
                    >
                        <div class="dot" :style="`background: ${l.color}`"></div>
                        <div class="lname" x-text="l.label"></div>
                        <div class="ldesc" x-text="l.desc"></div>
                        <template x-if="l.badge">
                            <span class="lbadge" :style="`background: ${l.color}22; color: ${l.color}`" x-text="l.badge"></span>
                        </template>
                        <template x-if="selectedLayout === l.id">
                            <span class="lcheck" :style="`color: ${l.color}`">✓</span>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- Config + Analyze (after layout) -->
    <template x-if="videoId && selectedLayout && step === 'url'">
        <div class="card fade">
            <div class="card-title">Konfigurasi</div>

            <div class="field">
                <label>Jumlah Clip</label>
                <div class="cnt-row">
                    <button class="cnt-btn" @click="clipCount = Math.max(1, clipCount - 1)">−</button>
                    <div class="cnt-num" x-text="clipCount"></div>
                    <button class="cnt-btn" @click="clipCount = Math.min(5, clipCount + 1)">+</button>
                    <span style="color: var(--muted); font-size: 0.8rem; margin-left: 0.4rem">clip (max 5)</span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="field">
                <label>Caption Otomatis</label>
                <div class="cap-opts">
                    <div class="cap-tog" :class="{ on: !globalAutoCaption }" @click="globalAutoCaption = false">Tidak ada</div>
                    <div class="cap-tog" :class="{ on: globalAutoCaption }" @click="globalAutoCaption = true">🎙️ Auto-caption AI</div>
                </div>
            </div>

            <template x-if="globalAutoCaption">
                <div class="field fade">
                    <label>Bahasa Video</label>
                    <select x-model="captionLanguage">
                        <option value="auto">Auto-detect</option>
                        <option value="id">Indonesia</option>
                        <option value="en">English</option>
                        <option value="ja">Japanese</option>
                        <option value="ko">Korean</option>
                        <option value="zh">Chinese</option>
                        <option value="es">Spanish</option>
                    </select>
                </div>
            </template>

            <div class="divider"></div>

            <button class="btn btn-primary" @click="analyze()" :disabled="analyzing">
                <span x-text="analyzing ? 'Mengirim…' : 'Analisa dengan Gemini →'"></span>
            </button>
            <div class="err" x-show="analyzeError" x-text="analyzeError"></div>
        </div>
    </template>

    <!-- Analyzing -->
    <template x-if="step === 'analyzing'">
        <div class="card fade">
            <div class="an-wrap">
                <div class="spinner"></div>
                <div class="an-label">
                    <strong x-text="batchStatusText()"></strong>
                    Gemini menganalisa video untuk menemukan segmen terbaik…
                </div>
            </div>
        </div>
    </template>

    <!-- Results -->
    <template x-if="step === 'results'">
        <div class="fade">
            <div class="results-hdr">
                <span x-text="`${clips.length} rekomendasi ditemukan`"></span>
                <span class="reset-lnk" @click="reset()">← Mulai ulang</span>
            </div>

            <template x-for="(clip, idx) in clips" :key="clip.id">
                <div
                    class="clip-card"
                    :class="{
                        'sel-c':  selected[clip.id] && clip.status === 'ready',
                        'done-c': clip.status === 'done',
                        'fail-c': clip.status === 'failed'
                    }"
                >
                    <div class="cc-head">
                        <div style="flex:1">
                            <div class="cc-num mono" x-text="`CLIP ${idx + 1}`"></div>
                            <div class="cc-hook" x-text="clip.gemini_hook || '—'"></div>
                        </div>
                        <span class="pill" :class="`p-${clip.status}`" x-text="statusLabel(clip.status)"></span>
                    </div>

                    <div class="cc-caption" x-text="clip.gemini_caption || '—'"></div>

                    <div class="tags">
                        <template x-for="tag in (clip.gemini_hashtags || [])" :key="tag">
                            <span class="tag" x-text="tag"></span>
                        </template>
                    </div>

                    <!-- Editable timestamps (only for ready/failed) -->
                    <template x-if="['ready', 'failed'].includes(clip.status)">
                        <div>
                            <div class="cc-times">
                                <div>
                                    <label>Mulai (detik)</label>
                                    <input type="number" x-model.number="clip.start_time" min="0" step="1">
                                    <div class="thint" x-text="formatTime(clip.start_time)"></div>
                                </div>
                                <div>
                                    <label>Selesai (detik)</label>
                                    <input type="number" x-model.number="clip.end_time" min="1" step="1">
                                    <div class="thint" x-text="formatTime(clip.end_time)"></div>
                                </div>
                            </div>
                            <div class="err" x-show="clip.end_time <= clip.start_time">End harus setelah start.</div>
                        </div>
                    </template>

                    <!-- Done: download -->
                    <template x-if="clip.status === 'done'">
                        <a :href="`/api/clip/${clip.id}/download`" class="btn btn-green btn-sm" style="text-decoration:none; display:inline-flex; margin-top:0.2rem">
                            ↓ Download MP4
                        </a>
                    </template>

                    <!-- Failed: error -->
                    <template x-if="clip.status === 'failed' && clip.error_msg">
                        <div class="err" x-text="clip.error_msg" style="margin-top:0.25rem"></div>
                    </template>

                    <div class="cc-foot">
                        <label class="chk-lbl" x-show="['ready', 'failed'].includes(clip.status)">
                            <input type="checkbox" :checked="selected[clip.id]" @change="selected[clip.id] = $event.target.checked">
                            <span>Pilih untuk generate</span>
                        </label>
                        <span x-show="clip.status === 'processing'" style="font-size:0.8rem; color: var(--yellow)">⏳ Memproses…</span>
                        <span x-show="clip.status === 'pending'" style="font-size:0.8rem; color: var(--muted)">Menunggu antrian…</span>
                        <span></span>
                    </div>
                </div>
            </template>

            <!-- Sticky generate bar -->
            <div class="gen-bar">
                <div class="gen-info">
                    <template x-if="anySelected()">
                        <span><strong x-text="selectedCount()"></strong> clip dipilih</span>
                    </template>
                    <template x-if="!anySelected()">
                        <span>Pilih minimal 1 clip</span>
                    </template>
                </div>
                <button class="btn btn-primary" style="width:auto; min-width:150px" @click="generateSelected()" :disabled="!anySelected() || generating">
                    <span x-text="generating ? 'Memulai…' : 'Generate →'"></span>
                </button>
            </div>
        </div>
    </template>

</div>

<script>
let ytPlayer = null;
let ytReady  = false;

window.onYouTubeIframeAPIReady = function () {
    ytReady = true;
    window.dispatchEvent(new CustomEvent('yt-ready'));
};

function clipper() {
    return {
        step: 'url',
        url: '',
        videoId: null,
        selectedLayout: null,
        clipCount: 3,
        globalAutoCaption: false,
        captionLanguage: 'id',
        analyzing: false,
        analyzeError: null,
        batchId: null,
        batchStatus: 'pending',
        batchPollTimer: null,
        clips: [],
        selected: {},
        generating: false,
        clipPollTimer: null,

        layouts: [
            { id: 'auto_magic',    label: 'Auto Magic',    desc: 'Momen terbaik AI',     color: '#2dd4bf', badge: 'Soon' },
            { id: 'auto_split',    label: 'Auto Split',    desc: 'Split-screen Shorts',  color: '#facc15', badge: 'Soon' },
            { id: 'gaussian_blur', label: 'Gaussian Blur', desc: 'Background blur',      color: '#a78bfa', badge: null  },
            { id: 'auto_reframe',  label: 'Auto Reframe',  desc: 'Crop 9:16 otomatis',  color: '#60a5fa', badge: null  },
        ],

        init() {},

        onUrlChange() {
            this.videoId = this.extractVideoId(this.url);
            if (this.videoId) {
                this.$nextTick(() => this.loadPlayer(this.videoId));
            } else if (ytPlayer) {
                ytPlayer.destroy();
                ytPlayer = null;
            }
        },

        extractVideoId(url) {
            try {
                const u = new URL(url);
                if (u.hostname.includes('youtu.be')) return u.pathname.slice(1) || null;
                if (u.pathname.startsWith('/shorts/')) return u.pathname.split('/')[2] || null;
                return u.searchParams.get('v') || null;
            } catch { return null; }
        },

        loadPlayer(id) {
            if (ytPlayer) { ytPlayer.destroy(); ytPlayer = null; }
            const create = () => {
                ytPlayer = new YT.Player('yt-player', {
                    videoId: id,
                    playerVars: { rel: 0, modestbranding: 1 },
                });
            };
            ytReady ? create() : window.addEventListener('yt-ready', create, { once: true });
        },

        async analyze() {
            this.analyzeError = null;
            this.analyzing    = true;
            this.step         = 'analyzing';
            this.batchStatus  = 'pending';

            try {
                const res  = await fetch('/api/clips', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ youtube_url: this.url, layout_mode: this.selectedLayout, clip_count: this.clipCount }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.analyzeError = data.message || JSON.stringify(data.errors || data);
                    this.step = 'url'; this.analyzing = false; return;
                }
                this.batchId = data.batch_id;
                this.startBatchPolling();
            } catch (e) {
                this.analyzeError = 'Network error: ' + e.message;
                this.step = 'url'; this.analyzing = false;
            }
        },

        startBatchPolling() {
            this.batchPollTimer = setInterval(() => this.pollBatch(), 2500);
        },

        async pollBatch() {
            try {
                const res  = await fetch(`/api/clips/${this.batchId}/status`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.batchStatus = data.status;

                if (data.status === 'analyzed') {
                    clearInterval(this.batchPollTimer);
                    this.clips    = data.clips.map(c => ({ ...c }));
                    this.selected = Object.fromEntries(data.clips.map(c => [c.id, true]));
                    this.step     = 'results';
                    this.analyzing = false;
                } else if (data.status === 'failed') {
                    clearInterval(this.batchPollTimer);
                    this.analyzeError = data.error_msg || 'Analisa Gemini gagal.';
                    this.step = 'url'; this.analyzing = false;
                }
            } catch {}
        },

        batchStatusText() {
            return { pending: 'Menunggu antrian…', analyzing: 'Gemini menganalisa…' }[this.batchStatus] || 'Memproses…';
        },

        anySelected() {
            return Object.values(this.selected).some(Boolean);
        },

        selectedCount() {
            return Object.values(this.selected).filter(Boolean).length;
        },

        async generateSelected() {
            this.generating = true;
            const ids = Object.entries(this.selected).filter(([, v]) => v).map(([k]) => k);

            for (const clipId of ids) {
                const clip = this.clips.find(c => c.id === clipId);
                if (!clip || !['ready', 'failed'].includes(clip.status)) continue;
                if (clip.end_time <= clip.start_time) continue;
                try {
                    await fetch(`/api/clips/${clipId}/generate`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({
                            start_time:       clip.start_time,
                            end_time:         clip.end_time,
                            auto_caption:     this.globalAutoCaption,
                            caption_language: this.captionLanguage,
                        }),
                    });
                    clip.status = 'pending';
                } catch {}
            }

            this.generating = false;
            this.startClipPolling();
        },

        startClipPolling() {
            if (this.clipPollTimer) clearInterval(this.clipPollTimer);
            this.clipPollTimer = setInterval(() => this.pollClips(), 3000);
        },

        async pollClips() {
            const active = this.clips.filter(c => ['pending', 'processing'].includes(c.status));
            if (!active.length) { clearInterval(this.clipPollTimer); return; }
            for (const clip of active) {
                try {
                    const res  = await fetch(`/api/clip/${clip.id}/status`, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    clip.status     = data.status;
                    clip.error_msg  = data.error_msg  || null;
                    clip.expires_at = data.expires_at || null;
                } catch {}
            }
        },

        statusLabel(s) {
            return { ready: 'SIAP', pending: 'ANTRIAN', processing: 'PROSES', done: 'SELESAI', failed: 'GAGAL' }[s] || s;
        },

        formatTime(secs) {
            const s = Math.floor(+(secs || 0));
            const h = Math.floor(s / 3600).toString().padStart(2, '0');
            const m = Math.floor((s % 3600) / 60).toString().padStart(2, '0');
            const sec = (s % 60).toString().padStart(2, '0');
            return `${h}:${m}:${sec}`;
        },

        reset() {
            if (this.batchPollTimer) clearInterval(this.batchPollTimer);
            if (this.clipPollTimer)  clearInterval(this.clipPollTimer);
            Object.assign(this, {
                step: 'url', url: '', videoId: null, selectedLayout: null,
                clipCount: 3, batchId: null, batchStatus: 'pending',
                clips: [], selected: {}, analyzeError: null, analyzing: false,
            });
            if (ytPlayer) { ytPlayer.destroy(); ytPlayer = null; }
        },
    };
}
</script>
</body>
</html>
