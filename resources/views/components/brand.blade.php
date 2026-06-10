<div class="brand">
    <div class="spectrum-mark"><span></span><span></span><span></span><span></span></div>
    <b>Spectrum</b><span class="by">/ specflow family</span>
</div>

@once
@push('styles')
<style>
  /* shared Spectrum brand mark — canonical 樣式,覆蓋各頁原本的 .brand 規則 */
  .brand{display:flex;align-items:center;gap:10px}
  .spectrum-mark{display:flex;gap:2px;align-items:flex-end;height:18px}
  .spectrum-mark span{width:4px;border-radius:1px;display:block}
  .spectrum-mark span:nth-child(1){height:10px;background:var(--ph1)}
  .spectrum-mark span:nth-child(2){height:16px;background:var(--ph2)}
  .spectrum-mark span:nth-child(3){height:13px;background:var(--ph3)}
  .spectrum-mark span:nth-child(4){height:18px;background:var(--accent)}
  .brand b{font-family:var(--mono);font-size:18px;font-weight:600;letter-spacing:.01em}
  .brand .by{font-family:var(--mono);font-size:11px;color:var(--faint);margin-left:2px}
</style>
@endpush
@endonce
