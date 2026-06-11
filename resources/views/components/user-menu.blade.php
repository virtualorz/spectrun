<div class="usermenu" id="usermenu">
  <button class="avatar" type="button" id="userBtn" aria-label="使用者選單">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
  </button>
  <div class="menu" id="userMenu">
    <a class="mi" href="{{ route('repository') }}">我的 Repository</a>
    <a class="mi" href="#">登出</a>
  </div>
</div>

@once
@push('styles')
<style>
  /* 共用使用者選單(頭像 + 下拉),avatar_url 接 GitHub API 後可換成真實頭像 */
  .usermenu{position:relative}
  .usermenu .avatar{width:34px;height:34px;border-radius:99px;background:var(--panel-2,#1A2230);border:1px solid var(--line,#283341);color:var(--dim,#97A3B4);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
  .usermenu .avatar:hover{color:var(--ink);border-color:var(--accent)}
  .usermenu .avatar svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7}
  .usermenu .menu{position:absolute;right:0;top:42px;min-width:158px;background:var(--panel,#151C26);border:1px solid var(--line,#283341);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);padding:6px;display:none;z-index:50}
  .usermenu.open .menu{display:block}
  .usermenu .mi{display:block;padding:9px 11px;font-size:13px;color:var(--ink,#E9EEF5);text-decoration:none;border-radius:7px;white-space:nowrap}
  .usermenu .mi:hover{background:var(--inset,#10161F);color:var(--accent)}
</style>
@endpush
@push('scripts')
<script>
  (function () {
    var wrap = document.getElementById('usermenu');
    var btn = document.getElementById('userBtn');
    if (!wrap || !btn) return;
    btn.addEventListener('click', function (e) { e.stopPropagation(); wrap.classList.toggle('open'); });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) wrap.classList.remove('open'); });
  })();
</script>
@endpush
@endonce
