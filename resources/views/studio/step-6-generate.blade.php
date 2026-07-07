<div class="wizard fade-in" x-show="!done && step === 6">

  {{-- Not started --}}
  <div class="processing" x-show="!generateStarted">
    <div class="proc-status" style="color:var(--text-muted)">siap dianalisis</div>
    <h2 class="proc-title">Generate clip dengan AI</h2>
    <p class="proc-sub">
      Gemini akan membaca transcript dan memilih <b style="color:var(--text)" x-text="clipCount"></b> momen paling
      berpotensi viral, lalu menyusun subtitle dan hook otomatis.
    </p>
    <button class="btn" style="margin-top:28px;font-size:15px;padding:16px 28px" @click="startGenerate()">
      Generate Clip dengan AI
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 3l4 9-4 9 14-9z"/></svg>
    </button>
    <div style="margin-top:18px;font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-dim)">
      model: gemini-2.5-flash · subtitle: groq-whisper
    </div>
  </div>

  {{-- Error --}}
  <div x-show="generateError" style="max-width:600px;margin:24px auto;padding:14px 18px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;font-size:13.5px;color:#f87171" x-text="generateError"></div>

  {{-- Running --}}
  <div class="processing" x-show="generateStarted && !generateError">
    <div class="proc-status" x-text="'analyzing · ' + generateProgress + '%'"></div>
    <h2 class="proc-title">AI sedang menganalisis video…</h2>
    <p class="proc-sub" x-text="'Memilih ' + clipCount + ' momen terbaik.'"></p>
    <div class="proc-bar-wrap"><div class="proc-bar" :style="'width:' + generateProgress + '%'"></div></div>
    <div class="proc-log">
      <template x-for="(s, i) in STAGES" :key="i">
        <div class="proc-log-row">
          {{-- Real message from backend if this stage is active/done, else label --}}
          <span class="proc-log-msg"
                :style="(i + 1) > generateLogIdx ? 'opacity:.35' : ''"
                x-text="(i + 1) === generateLogIdx && generateLogMsg
                          ? generateLogMsg
                          : s.msg.replace('N momen', clipCount + ' momen')">
          </span>
          <span class="proc-log-state"
                :class="(i + 1) < generateLogIdx ? 'ok' : (i + 1) === generateLogIdx ? 'run' : 'wait'"
                x-text="(i + 1) < generateLogIdx ? '✓ DONE' : (i + 1) === generateLogIdx ? '● RUN' : '○ WAIT'">
          </span>
        </div>
      </template>
    </div>
  </div>

</div>
