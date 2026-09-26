<div class="result2 fade-in" x-show="done">

  {{-- LEFT: phone preview sticky --}}
  <div class="result2-aside">
    <div class="preview-label"><span>Hasil render · 9:16</span><span>1080×1920</span></div>

    <div class="phone-frame">
      <div class="phone-notch"></div>
      <div x-html="layoutBg(layout)"></div>
      <div style="position:absolute;top:52px;left:12px;z-index:5;display:inline-flex;align-items:center;gap:6px;background:rgba(0,0,0,.5);backdrop-filter:blur(6px);padding:4px 10px 4px 4px;border-radius:999px;font-size:9px;font-weight:600;color:#fff">
        <span :style="'width:13px;height:13px;border-radius:50%;background:'+currentLayout.accent"></span>
        <span x-text="sampleMeta.channel"></span>
      </div>
      <div x-show="subtitleEnabled && !pvInHook" :style="pvSubStyle()">
        <div :style="pvSubBoxStyle()">
          <template x-for="w in pvSegWords" :key="w.idx">
            <span :style="'color:'+(w.active ? subtitleHighlight : subtitleTextColor)+';transition:color .08s'" x-text="w.w + ' '"></span>
          </template>
        </div>
      </div>
      <div x-show="pvInHook && hookEnabled" style="position:absolute;inset:0;z-index:7">
        <div :style="'position:absolute;left:20px;right:20px;text-align:center;'+pvHookPosStyle()">
          <div :style="pvHookBoxStyle()" x-text="resultHookText"></div>
        </div>
      </div>
      <div style="position:absolute;left:14px;right:14px;bottom:16px;z-index:5;height:2px;background:rgba(255,255,255,.22);border-radius:999px;overflow:hidden">
        <div :style="'width:'+pvProgress+'%;height:100%;background:#fff'"></div>
      </div>
    </div>

    <div class="dl-row" style="justify-content:center;margin-top:16px">
      <a :href="downloadUrl || '#'" :download="downloadUrl ? 'clip.mp4' : null"
         class="btn" :style="!downloadUrl ? 'opacity:.5;pointer-events:none' : ''">
        Download MP4
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v14m0 0l-5-5m5 5l5-5M5 21h14"/></svg>
      </a>
      <button class="btn btn-secondary" @click="done = false; step = 7">Pilih clip lain</button>
    </div>
    <div style="margin-top:14px;text-align:center;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-dim);letter-spacing:.05em"
         x-text="'H.264 · AAC · ' + (selectedClipObj ? Math.round(selectedClipObj.end_seconds - selectedClipObj.start_seconds) : 0) + 's · ' + currentLayout.title.toUpperCase()"></div>
  </div>

  {{-- RIGHT: caption & hashtag --}}
  <div>
    <div class="done-banner">
      <div class="done-banner-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M20 6L9 17l-5-5"/></svg>
      </div>
      <div class="done-banner-txt">
        <h3>Clip berhasil dirender</h3>
        <p x-text="selectedClipObj ? '“' + selectedClipObj.topic + '” · ranking #' + selectedClipObj.ranking + ' · potensi viral ' + selectedClipObj.viral_potential : ''"></p>
      </div>
      <span class="status-pill">DONE</span>
    </div>

    {{-- Caption & Hashtag (AI-generated) --}}
    <div class="res-sec">
      <div class="res-sec-head">
        <span class="res-sec-title">Caption & hashtag</span>
        <button class="res-edit"
          x-show="!loadingCaption && clipCaption"
          @click="copyText('cap', clipCaption + '\n\n' + clipHashtags.join(' '))"
          x-text="copied === 'cap' ? '✓ tersalin' : 'Copy semua'">
        </button>
      </div>

      <div x-show="loadingCaption" style="display:flex;align-items:center;gap:10px;padding:20px 0;color:var(--text-dim);font-size:13px">
        <div class="proc-status" style="margin:0">Membuat caption dengan AI…</div>
      </div>

      <div x-show="!loadingCaption && clipCaption" class="caption-box" x-text="clipCaption"></div>

      <div x-show="!loadingCaption && clipHashtags.length > 0" class="hashtags" style="margin-top:12px">
        <template x-for="h in clipHashtags" :key="h">
          <span class="hashtag" @click="copyText(h, h)" x-text="h"></span>
        </template>
      </div>

      <div x-show="!loadingCaption && !clipCaption" style="padding:16px 0;color:var(--text-dim);font-size:13px">
        Caption belum tersedia.
        <button style="background:none;border:0;color:var(--teal);cursor:pointer;font-family:inherit;font-size:13px;padding:0;margin-left:6px"
          @click="fetchCaption()">Coba lagi →</button>
      </div>
    </div>

    {{-- Upload ke TikTok (draft / inbox) --}}
    <div class="res-sec">
      <div class="res-sec-head">
        <span class="res-sec-title">Upload ke TikTok</span>
        <a class="res-edit" href="/tiktok/connect" x-show="tiktokConfigured && tiktokAccounts.length > 0">+ Akun lain</a>
      </div>

      <div x-show="!tiktokConfigured" style="padding:8px 0;color:var(--text-dim);font-size:13px">
        Isi <code>TIKTOK_CLIENT_KEY</code>, <code>TIKTOK_CLIENT_SECRET</code>, dan <code>TIKTOK_REDIRECT_URI</code> di <code>.env</code> untuk mengaktifkan upload.
      </div>

      <div x-show="tiktokConfigured && tiktokAccounts.length === 0" style="padding:8px 0">
        <a class="btn btn-secondary" href="/tiktok/connect">Hubungkan akun TikTok</a>
      </div>

      <div x-show="tiktokConfigured && tiktokAccounts.length > 0">
        <div class="seg" style="flex-wrap:wrap;margin-bottom:12px">
          <template x-for="a in tiktokAccounts" :key="a.id">
            <button :class="{ on: tiktokAccountId === a.id }" @click="tiktokAccountId = a.id" type="button" x-text="a.display_name || ('Akun #' + a.id)"></button>
          </template>
        </div>
        <button class="btn" @click="uploadToTiktok()"
                :disabled="tiktokBusy || !tiktokAccountId"
                :style="(tiktokBusy || !tiktokAccountId) ? 'opacity:.5;pointer-events:none' : ''"
                x-text="tiktokBusy ? 'Mengupload…' : 'Kirim ke TikTok (draft)'"></button>
        <div x-show="tiktokStatus" style="margin-top:12px;font-size:13px"
             :style="tiktokStatus === 'failed' ? 'color:#f87171' : 'color:var(--text-muted)'"
             x-text="tiktokStatusLabel"></div>
        <div x-show="tiktokStatus === 'failed' && tiktokError" style="margin-top:6px;font-size:12px;color:var(--text-dim)" x-text="tiktokError"></div>
      </div>
    </div>

    <div class="dl-row">
      <button class="btn btn-ghost" @click="restart()">← Buat project baru</button>
    </div>
  </div>

</div>
