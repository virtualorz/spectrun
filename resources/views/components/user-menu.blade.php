<div class="usermenu" id="usermenu">
  <button class="avatar" type="button" id="userBtn" aria-label="使用者選單">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
  </button>
  <div class="menu" id="userMenu">
    <a class="mi" href="{{ route('repository') }}">我的 Repository</a>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button class="mi" type="submit" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font:inherit;color:inherit">登出</button>
    </form>
  </div>
</div>

@once
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
