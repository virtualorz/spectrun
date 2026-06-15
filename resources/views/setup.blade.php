@extends('layouts.spectrum')

@section('title', 'Spectrum · 首次設定')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/card.css') }}?v={{ filemtime(public_path('css/card.css')) }}">
@endpush

@section('content')
<div class="topbar">
    <x-brand />
    <button class="icbtn" id="theme" aria-label="切換亮暗主題">◐</button>
  </div>

  <div class="card">
    <h1 class="h1">首次設定</h1>
    <p class="lede">設定一組本站登入帳密,並貼上唯讀的 GitHub Personal Access Token(以 APP_KEY 加密儲存)。</p>

    <form method="POST" action="{{ route('setup.store') }}">
      @csrf

      <div class="field">
        <label class="flabel" for="account">帳號</label>
        <div class="inwrap">
          <input id="account" name="account" type="text" value="{{ old('account') }}" autocomplete="username" spellcheck="false">
        </div>
        @error('account')<div class="msg err show"><span>{{ $message }}</span></div>@enderror
      </div>

      <div class="field">
        <label class="flabel" for="password">密碼</label>
        <div class="inwrap">
          <input id="password" name="password" type="password" autocomplete="new-password">
        </div>
        @error('password')<div class="msg err show"><span>{{ $message }}</span></div>@enderror
      </div>

      <div class="field">
        <label class="flabel" for="password_confirmation">密碼確認</label>
        <div class="inwrap">
          <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
        </div>
      </div>

      <div class="field">
        <label class="flabel" for="tok">GitHub Personal Access Token</label>
        <div class="inwrap">
          <input id="tok" name="access_token" type="password" value="{{ old('access_token') }}" placeholder="github_pat_… 或 ghp_…" autocomplete="off" spellcheck="false">
          <button class="eye" id="eye" type="button" aria-label="顯示/隱藏">
            <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        @error('access_token')<div class="msg err show"><span>{{ $message }}</span></div>@enderror

        <details class="howto">
          <summary><svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>怎麼建立這個 token?(建議用 Fine-grained,最小權限)</summary>
          <div class="body">
            <ol>
              <li>到 GitHub → <span class="mono">Settings → Developer settings → Fine-grained tokens</span>。</li>
              <li><b>Repository access</b>:只勾選你要讓 Spectrum 讀的 repo(不必開放全部)。</li>
              <li><b>Permissions → Repository → Contents</b> 設為 <code>Read-only</code>,其餘維持 No access。</li>
              <li>產生後複製 <code>github_pat_…</code> 貼到上面即可。</li>
            </ol>
            <div style="margin-top:9px">前往 <a href="#" onclick="return false">github.com/settings/personal-access-tokens</a></div>
          </div>
        </details>
      </div>

      <div class="secnote">
        <svg viewBox="0 0 24 24"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M9.5 12l1.8 1.8 3.5-3.8"/></svg>
        <span>這組 token 會以 <b>AES 加密</b>後存放在你自己的伺服器(使用 Spectrum 的 APP_KEY 加密),只用於讀取,<b>不會外傳</b>到任何第三方。</span>
      </div>

      <button class="btn" id="setupBtn" type="submit">完成設定</button>
    </form>
  </div>

  <div class="foot">自架單體服務 · 資料只留在你自己的機器上</div>
@endsection

@push('scripts')
<script>
  // 顯示/隱藏 token
  (function () {
    var tok = document.getElementById('tok');
    var eye = document.getElementById('eye');
    if (eye && tok) {
      eye.onclick = function () {
        tok.type = tok.type === 'password' ? 'text' : 'password';
        tok.focus();
      };
    }
  })();

  // 送出後 loading 回饋(後端要打 GitHub 驗證,需要一點時間)
  (function () {
    var form = document.querySelector('form[action="{{ route('setup.store') }}"]');
    var btn = document.getElementById('setupBtn');
    if (form && btn) {
      form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.textContent = '驗證中…';
      });
    }
  })();
</script>
@endpush
