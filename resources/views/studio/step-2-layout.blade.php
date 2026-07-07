<div class="wizard fade-in" x-show="!done && step === 2">
  <div class="wiz-head">
    <div class="wiz-kicker">Langkah 2 dari 7</div>
    <h1 class="wiz-title">Pilih tipe layout</h1>
    <p class="wiz-desc">Tiap layout menentukan bagaimana video horizontal diubah jadi vertikal 9:16.</p>
  </div>
  <div class="layout-grid">
    <template x-for="L in LAYOUTS" :key="L.id">
      <button class="layout-card" :class="['c-'+L.color, layout === L.id ? 'lsel' : '']"
              @click="layout = L.id" type="button">
        <div class="layout-card-meta">
          <div class="layout-card-badges">
            <span x-show="L.badge" :class="['badge', L.badge ? L.badge.cls : '']" x-text="L.badge ? L.badge.text : ''"></span>
            <span style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-dim);letter-spacing:.06em;text-transform:uppercase" x-text="L.sub"></span>
          </div>
          <div class="layout-card-title" x-text="L.title"></div>
          <div class="layout-card-desc" x-text="L.desc"></div>
          <div style="font-size:11.5px;color:var(--text-dim);margin-top:2px">
            <span :style="'color:'+L.accent">Cocok:</span> <span x-text="L.good"></span>
          </div>
        </div>
        <div class="layout-card-illu" x-html="layoutIllu(L.id, L.accent)"></div>
        <span class="layout-card-check">✓ Dipilih</span>
      </button>
    </template>
  </div>
</div>
