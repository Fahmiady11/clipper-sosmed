<div class="wizbar" x-show="!done">
  <div class="wizbar-inner">
    <template x-for="(s, i) in STEPS" :key="s.key">
      <div style="display:contents">
        <div class="wstep"
             :class="{ 'ws-active': step === s.n, 'ws-done': step > s.n, 'clickable': s.n <= maxReached }"
             @click="s.n <= maxReached && goStep(s.n)">
          <span class="wstep-dot" x-text="step > s.n ? '✓' : s.n"></span>
          <span class="wstep-label" x-text="s.label"></span>
        </div>
        <span x-show="i < STEPS.length - 1" class="wstep-line" :class="{ done: step > s.n }"></span>
      </div>
    </template>
  </div>
</div>
