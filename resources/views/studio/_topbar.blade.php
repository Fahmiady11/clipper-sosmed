<div class="topbar">
  <div class="brand">
    <div class="brand-mark"></div>
    Clipper <span style="color:var(--text-muted);font-weight:500">Studio</span>
  </div>
  <div class="topbar-nav">
    <a class="active" href="#" @click.prevent="restart()">Project baru</a>
    <a href="/autopilot">Autopilot</a>
    <a href="#" @click.prevent="historyOpen = true; loadHistory()" style="position:relative">
      Riwayat
      <span x-show="history.length > 0" x-text="history.length"
        style="position:absolute;top:-4px;right:-6px;background:var(--teal);color:#042f2c;border-radius:999px;font-size:9px;font-weight:700;padding:1px 5px;font-family:'JetBrains Mono',monospace;line-height:1.4">
      </span>
    </a>
    <form method="POST" action="{{ route('logout') }}" style="display:contents">
      @csrf
      <button type="submit" style="background:none;border:0;cursor:pointer;display:flex;align-items:center;gap:7px;font-family:inherit;padding:6px 12px;border-radius:7px;font-size:13px;color:var(--text-muted);transition:color .15s,background .15s" onmouseover="this.style.color='var(--text)';this.style.background='var(--surface)'" onmouseout="this.style.color='var(--text-muted)';this.style.background='none'">
        <span class="nav-avatar">{{ Auth::user()->initials() }}</span>
        {{ Auth::user()->name }}
      </button>
    </form>
  </div>
</div>
