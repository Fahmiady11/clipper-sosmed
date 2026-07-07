{{-- Reusable phone preview widget. Requires Alpine context from studio(). --}}
<div class="phone-frame">
  <div class="phone-notch"></div>
  <div x-html="layoutBg(layout)"></div>
  {{-- channel chip --}}
  <div style="position:absolute;top:52px;left:12px;z-index:5;display:inline-flex;align-items:center;gap:6px;background:rgba(0,0,0,.5);backdrop-filter:blur(6px);padding:4px 10px 4px 4px;border-radius:999px;font-size:9px;font-weight:600;color:#fff">
    <span :style="'width:13px;height:13px;border-radius:50%;background:'+currentLayout.accent"></span>
    <span x-text="sampleMeta.channel"></span>
  </div>
  {{-- karaoke subtitle --}}
  <div x-show="subtitleEnabled && !pvInHook" :style="pvSubStyle()">
    <div :style="pvSubBoxStyle()">
      <template x-for="w in pvSegWords" :key="w.idx">
        <span :style="'color:'+(w.active ? subtitleHighlight : subtitleTextColor)+';transition:color .08s'" x-text="w.w + ' '"></span>
      </template>
    </div>
  </div>
  {{-- hook overlay --}}
  <div x-show="pvInHook && hookEnabled" style="position:absolute;inset:0;z-index:7;animation:fadeIn .25s ease both">
    <div :style="'position:absolute;left:20px;right:20px;text-align:center;'+pvHookPosStyle()">
      <div :style="pvHookBoxStyle()" x-text="hookText"></div>
      <div style="margin-top:10px;font-family:ui-monospace,monospace;font-size:9px;letter-spacing:.1em;color:rgba(255,255,255,.5)" x-text="'HOOK · '+hookDuration+'s'"></div>
    </div>
  </div>
  {{-- progress bar --}}
  <div style="position:absolute;left:14px;right:14px;bottom:16px;z-index:5;height:2px;background:rgba(255,255,255,.22);border-radius:999px;overflow:hidden">
    <div :style="'width:'+pvProgress+'%;height:100%;background:#fff'"></div>
  </div>
</div>
