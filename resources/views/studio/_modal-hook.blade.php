<div class="modal-bg" x-show="editHookOpen" @click="editHookOpen = false">
  <div class="modal" @click.stop>
    <h3 class="modal-title">Edit hook</h3>
    <textarea class="field" maxlength="100" x-model="draftHook" autofocus></textarea>
    <div class="field-count" x-text="draftHook.length + '/100'"></div>
    <div class="modal-actions">
      <button class="btn btn-secondary" @click="editHookOpen = false">Batal</button>
      <button class="btn" @click="resultHookText = draftHook; editHookOpen = false">Simpan & re-render</button>
    </div>
  </div>
</div>
