<div class="wizard narrow fade-in" x-show="!done && step === 3">
  <div class="wiz-head">
    <div class="wiz-kicker">Langkah 3 dari 7</div>
    <h1 class="wiz-title">Pengaturan clip</h1>
    <p class="wiz-desc">Tentukan berapa banyak clip yang AI hasilkan dan berapa panjangnya.</p>
  </div>

  <div class="fblock">
    <div class="fblock-head">
      <div><div class="fblock-title">Jumlah clip</div><div class="fblock-hint">AI memilih momen terbaik sebanyak ini.</div></div>
    </div>
    <div class="radio-cards">
      <template x-for="n in [1,3,5]" :key="n">
        <button class="radio-card" :class="{ on: clipCount === n }" @click="clipCount = n" type="button">
          <div class="radio-card-big" x-text="n"></div>
          <div class="radio-card-sub" x-text="n === 1 ? 'clip' : 'clips'"></div>
        </button>
      </template>
    </div>
  </div>

  <div class="fblock">
    <div class="fblock-head">
      <div><div class="fblock-title">Durasi per clip</div><div class="fblock-hint">Biarkan AI menentukan, atau atur sendiri.</div></div>
    </div>
    <div class="seg full">
      <button :class="{ on: durationMode === 'auto' }"   @click="durationMode = 'auto'"   type="button">Otomatis (AI · 30-90s)</button>
      <button :class="{ on: durationMode === 'manual' }" @click="durationMode = 'manual'" type="button">Manual</button>
    </div>
    <div x-show="durationMode === 'manual'" style="margin-top:18px;display:flex;flex-direction:column;gap:18px">
      <div>
        <div class="fblock-hint" style="margin-bottom:8px">Minimal durasi</div>
        <div class="sld-wrap">
          <input class="sld" type="range" :min="5" :max="maxDuration - 5" x-model.number="minDuration" />
          <span class="sld-val" x-text="minDuration + 's'"></span>
        </div>
      </div>
      <div>
        <div class="fblock-hint" style="margin-bottom:8px">Maksimal durasi</div>
        <div class="sld-wrap">
          <input class="sld" type="range" :min="minDuration + 5" :max="90" x-model.number="maxDuration" />
          <span class="sld-val" x-text="maxDuration + 's'"></span>
        </div>
      </div>
    </div>
  </div>
</div>
