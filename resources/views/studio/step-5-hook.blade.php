<div class="wizard fade-in" x-show="!done && step === 5">
  <div class="wiz-head">
    <div class="wiz-kicker">Langkah 5 dari 7</div>
    <h1 class="wiz-title">Pengaturan hook</h1>
    <p class="wiz-desc">Hook pembuka 2–5 detik untuk menahan penonton di 3 detik pertama.</p>
  </div>
  <div class="wiz-split">
    <div>
      <div class="fblock">
        <button class="tgl" :class="{ on: hookEnabled }" @click="hookEnabled = !hookEnabled" type="button">
          <span class="tgl-track"></span>
          <span class="tgl-text">Aktifkan hook pembuka</span>
        </button>
      </div>
      <div class="settings-group" :class="{ off: !hookEnabled }">
        <div class="fblock">
          <div class="fblock-head">
            <div class="fblock-title">Teks hook</div>
            <button class="tgl" :class="{ on: hookAi }" @click="hookAi = !hookAi" type="button">
              <span class="tgl-track"></span>
              <span class="tgl-text" style="font-size:12px">Generate otomatis (AI)</span>
            </button>
          </div>
          <textarea class="field" maxlength="100" :disabled="hookAi"
                    :value="hookAi ? 'AI akan menulis hook berdasarkan isi clip…' : hookText"
                    @input="hookText = $event.target.value"
                    :style="hookAi ? 'opacity:.5' : ''"></textarea>
          <div class="field-count" x-show="!hookAi" x-text="hookText.length + '/100'"></div>
        </div>
        <div class="fblock">
          <div class="fblock-title" style="margin-bottom:10px">Durasi tampil</div>
          <div class="sld-wrap">
            <input class="sld" type="range" min="2" max="5" step="0.5" x-model.number="hookDuration" />
            <span class="sld-val" x-text="hookDuration + 's'"></span>
          </div>
        </div>
        <div class="fblock">
          <div class="fblock-title" style="margin-bottom:10px">Posisi</div>
          <div class="seg">
            <button :class="{ on: hookPos === 'top' }"    @click="hookPos = 'top'"    type="button">Atas</button>
            <button :class="{ on: hookPos === 'center' }" @click="hookPos = 'center'" type="button">Tengah</button>
            <button :class="{ on: hookPos === 'bottom' }" @click="hookPos = 'bottom'" type="button">Bawah</button>
          </div>
        </div>
        <div class="fblock">
          <div class="fblock-title" style="margin-bottom:10px">Warna teks</div>
          <div class="swatches">
            <template x-for="c in COLOR_PRESETS" :key="c">
              <button class="swatch" :class="{ on: hookTextColor === c }" :style="'background:'+c" @click="hookTextColor = c" type="button"></button>
            </template>
          </div>
        </div>
        <div class="fblock">
          <div class="fblock-title" style="margin-bottom:10px">Background</div>
          <div class="seg">
            <button :class="{ on: hookBg === 'none' }" @click="hookBg = 'none'" type="button">Transparan</button>
            <button :class="{ on: hookBg === 'semi' }" @click="hookBg = 'semi'" type="button">Semi hitam</button>
            <button :class="{ on: hookBg === 'full' }" @click="hookBg = 'full'" type="button">Kotak penuh</button>
          </div>
        </div>
      </div>
    </div>
    <div style="position:sticky;top:130px">
      <div class="preview-label"><span>Live preview · 9:16</span><span x-text="currentLayout.title"></span></div>
      @include('studio._phone-preview')
      <div style="margin-top:12px;text-align:center;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-dim);letter-spacing:.05em">↻ Hook tampil di awal loop</div>
    </div>
  </div>
</div>
