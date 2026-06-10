@extends('layouts.spectrum')

@section('title', 'Spectrum · 首次設定')

@push('styles')
<style>
  :root{
    --bg:#0E131B;--panel:#151C26;--panel-2:#1A2230;--inset:#10161F;
    --line:#283341;--line-2:#1E2630;--ink:#E9EEF5;--dim:#97A3B4;--faint:#5E6A7B;
    --accent:#56B6E6;--accent-soft:rgba(86,182,230,.14);
    --run:#E0A33E;--run-soft:rgba(224,163,62,.22);
    --ok:#3FB984;--ok-soft:rgba(63,185,132,.15);--err:#E06B6B;--err-soft:rgba(224,107,107,.14);
    --ph1:#6C7CA8;--ph2:#9B7DD6;--ph3:#3FB984;
    --radius:14px;--radius-sm:9px;
    --mono:"SF Mono","JetBrains Mono","Fira Code",ui-monospace,Menlo,Consolas,monospace;
    --sans:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans TC","PingFang TC","Microsoft JhengHei",sans-serif;
  }
  html.light{--bg:#F4F6F8;--panel:#FFF;--panel-2:#FAFBFC;--inset:#EFF2F5;
    --line:#E1E5EB;--line-2:#EBEEF2;--ink:#19212E;--dim:#5A6676;--faint:#96A0AF;
    --accent:#1E84C7;--accent-soft:rgba(30,132,199,.10);--run:#B9791A;--run-soft:rgba(185,121,26,.20);
    --ok:#1E9E6E;--ok-soft:rgba(30,158,110,.12);--err:#C0392B;--err-soft:rgba(192,57,43,.10);
    --ph1:#7888B5;--ph2:#8A66C9;--ph3:#1E9E6E;}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:14px;line-height:1.55;-webkit-font-smoothing:antialiased;
    display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 18px}
  .mono{font-family:var(--mono);font-variant-numeric:tabular-nums}
  .topbar{width:100%;max-width:540px;display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
  .brand{display:flex;align-items:center;gap:10px}
  .spectrum-mark{display:flex;gap:2px;align-items:flex-end;height:18px}
  .spectrum-mark span{width:4px;border-radius:1px;display:block}
  .spectrum-mark span:nth-child(1){height:10px;background:var(--ph1)}
  .spectrum-mark span:nth-child(2){height:16px;background:var(--ph2)}
  .spectrum-mark span:nth-child(3){height:13px;background:var(--ph3)}
  .spectrum-mark span:nth-child(4){height:18px;background:var(--accent)}
  .brand b{font-family:var(--mono);font-size:18px;font-weight:600;letter-spacing:.01em}
  .brand .by{font-family:var(--mono);font-size:11px;color:var(--faint);margin-left:2px}
  .icbtn{width:34px;height:34px;border-radius:99px;background:var(--panel);border:1px solid var(--line);color:var(--dim);cursor:pointer;font-size:15px}
  .icbtn:hover{color:var(--ink);border-color:var(--accent)}

  .card{width:100%;max-width:540px;background:var(--panel);border:1px solid var(--line-2);border-radius:var(--radius);padding:28px 30px 26px;box-shadow:0 10px 40px rgba(0,0,0,.18)}
  html.light .card{box-shadow:0 8px 30px rgba(20,30,50,.07)}
  .h1{font-size:21px;font-weight:600;margin:0 0 4px}
  .lede{font-size:13.5px;color:var(--dim);margin-bottom:22px}

  /* stepper */
  .steps{display:flex;align-items:center;gap:10px;margin-bottom:24px}
  .step{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--faint)}
  .step .n{width:22px;height:22px;border-radius:99px;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-family:var(--mono);font-size:11px;flex:none}
  .step.active{color:var(--ink)}
  .step.active .n{border-color:var(--accent);background:var(--accent-soft);color:var(--accent)}
  .step.done .n{border-color:var(--ok);background:var(--ok-soft);color:var(--ok)}
  .step.done{color:var(--dim)}
  .steps .bar{flex:1;height:1px;background:var(--line)}

  .field{margin-bottom:14px}
  .flabel{display:block;font-size:12.5px;color:var(--ink);font-weight:600;margin-bottom:7px}
  .inwrap{position:relative;display:flex}
  .inwrap input{width:100%;height:44px;background:var(--inset);border:1px solid var(--line);border-radius:var(--radius-sm);color:var(--ink);font-family:var(--mono);font-size:13.5px;padding:0 44px 0 13px;letter-spacing:.02em}
  .inwrap input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
  .inwrap input::placeholder{color:var(--faint);letter-spacing:0}
  .inwrap input.bad{border-color:var(--err)}
  .eye{position:absolute;right:6px;top:6px;width:32px;height:32px;border:none;background:none;color:var(--faint);cursor:pointer;border-radius:7px;display:flex;align-items:center;justify-content:center}
  .eye:hover{color:var(--ink);background:var(--panel-2)}
  .eye svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7}

  .help{font-size:12px;color:var(--dim);margin-top:9px;line-height:1.6}
  .help a,.linklike{color:var(--accent);cursor:pointer;text-decoration:none}
  .help a:hover,.linklike:hover{text-decoration:underline}
  .howto{margin-top:10px;border:1px solid var(--line-2);border-radius:var(--radius-sm);overflow:hidden}
  .howto summary{list-style:none;cursor:pointer;padding:10px 13px;font-size:12.5px;color:var(--dim);display:flex;align-items:center;gap:8px;user-select:none}
  .howto summary::-webkit-details-marker{display:none}
  .howto summary:hover{color:var(--ink)}
  .howto[open] summary{border-bottom:1px solid var(--line-2);color:var(--ink)}
  .howto .chev{transition:transform .15s}
  .howto[open] .chev{transform:rotate(90deg)}
  .howto .body{padding:12px 14px;font-size:12.5px;color:var(--dim);line-height:1.7}
  .howto ol{margin:0;padding-left:18px}
  .howto code{font-family:var(--mono);font-size:11.5px;background:var(--inset);color:var(--ink);padding:1px 6px;border-radius:5px}

  .secnote{display:flex;gap:9px;align-items:flex-start;font-size:12px;color:var(--dim);background:var(--inset);border-radius:var(--radius-sm);padding:11px 13px;margin:16px 0 20px;line-height:1.6}
  .secnote svg{width:16px;height:16px;flex:none;margin-top:1px;fill:none;stroke:var(--ok);stroke-width:1.7}
  .secnote b{color:var(--ink);font-weight:600}

  .btn{width:100%;height:44px;border:none;border-radius:var(--radius-sm);background:var(--accent);color:#06121C;font-family:var(--sans);font-size:14.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px}
  html.light .btn{color:#fff}
  .btn:hover{filter:brightness(1.06)}
  .btn:disabled{opacity:.55;cursor:not-allowed;filter:none}
  .btn.ghost{background:var(--inset);color:var(--ink);border:1px solid var(--line)}
  .btn .spin{width:16px;height:16px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite}
  html.light .btn .spin{border-color:rgba(255,255,255,.5);border-top-color:#fff}
  @keyframes sp{to{transform:rotate(360deg)}}

  .msg{font-size:12.5px;border-radius:var(--radius-sm);padding:10px 13px;margin-top:12px;display:none;align-items:center;gap:9px}
  .msg.show{display:flex}
  .msg.err{background:var(--err-soft);color:var(--err)}
  .msg svg{width:16px;height:16px;flex:none;fill:none;stroke:currentColor;stroke-width:1.8}

  /* connected bar */
  .conn{display:flex;align-items:center;gap:11px;background:var(--ok-soft);border:1px solid rgba(63,185,132,.3);border-radius:var(--radius-sm);padding:11px 14px;margin-bottom:22px}
  .conn .ava{width:30px;height:30px;border-radius:99px;background:var(--panel-2);display:flex;align-items:center;justify-content:center;color:var(--ok);flex:none}
  .conn .ava svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7}
  .conn .who{flex:1;min-width:0}
  .conn .who .u{font-family:var(--mono);font-size:13px;color:var(--ink);font-weight:600}
  .conn .who .r{font-family:var(--mono);font-size:11px;color:var(--dim)}
  .conn .change{font-size:12px;color:var(--dim);cursor:pointer;background:none;border:none;text-decoration:underline}
  .conn .change:hover{color:var(--ink)}

  /* repo list */
  .seclab{font-size:12.5px;color:var(--ink);font-weight:600;margin-bottom:4px}
  .secsub{font-size:12px;color:var(--dim);margin-bottom:12px}
  .repo{display:flex;align-items:center;gap:12px;border:1px solid var(--line-2);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:8px;cursor:pointer}
  .repo:hover{border-color:var(--line)}
  .repo.sel{border-color:var(--accent);background:var(--accent-soft)}
  .repo .cb{width:18px;height:18px;border:1.5px solid var(--line);border-radius:5px;flex:none;display:flex;align-items:center;justify-content:center}
  .repo.sel .cb{background:var(--accent);border-color:var(--accent)}
  .repo .cb svg{width:12px;height:12px;fill:none;stroke:#06121C;stroke-width:2.6;opacity:0}
  html.light .repo.sel .cb svg{stroke:#fff}
  .repo.sel .cb svg{opacity:1}
  .repo .info{flex:1;min-width:0}
  .repo .rn{font-family:var(--mono);font-size:13px;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .repo .rt{font-size:11.5px;color:var(--faint);margin-top:1px;display:flex;gap:8px;align-items:center}
  .tag{font-size:10.5px;padding:1px 7px;border-radius:99px;font-weight:600}
  .tag.priv{color:var(--run);background:var(--run-soft)}
  .tag.pub{color:var(--dim);background:var(--inset)}
  .tag.has{color:var(--ph3);background:var(--ok-soft)}
  .repo.noflow{opacity:.5}
  .addrow{display:flex;gap:8px;margin:12px 0 4px}
  .addrow input{flex:1;height:40px;background:var(--inset);border:1px solid var(--line);border-radius:var(--radius-sm);color:var(--ink);font-family:var(--mono);font-size:12.5px;padding:0 12px}
  .addrow input:focus{outline:none;border-color:var(--accent)}
  .addrow button{height:40px;padding:0 16px;border:1px solid var(--line);background:var(--inset);color:var(--ink);border-radius:var(--radius-sm);cursor:pointer;font-size:13px;white-space:nowrap}
  .addrow button:hover{border-color:var(--accent);color:var(--accent)}

  .interval{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:18px 0 22px;padding:13px 14px;border:1px solid var(--line-2);border-radius:var(--radius-sm)}
  .interval .il{font-size:13px}.interval .il .s{font-size:11.5px;color:var(--faint)}
  .interval select{background:var(--inset);border:1px solid var(--line);color:var(--ink);font-family:var(--mono);font-size:12.5px;padding:7px 10px;border-radius:7px;cursor:pointer}
  .interval select:focus{outline:none;border-color:var(--accent)}

  .btnrow{display:flex;gap:10px}.btnrow .btn{flex:1}

  /* done */
  .done{text-align:center;padding:8px 0 2px}
  .done .seal{width:62px;height:62px;border-radius:99px;background:var(--ok-soft);display:flex;align-items:center;justify-content:center;margin:4px auto 16px}
  .done .seal svg{width:30px;height:30px;fill:none;stroke:var(--ok);stroke-width:2}
  .done h2{font-size:19px;margin:0 0 6px}
  .done p{font-size:13px;color:var(--dim);margin:0 0 18px}
  .summary{text-align:left;background:var(--inset);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:20px;font-size:12.5px}
  .summary .sr{display:flex;justify-content:space-between;padding:5px 0;color:var(--dim)}
  .summary .sr b{color:var(--ink);font-family:var(--mono);font-weight:600}
  .syncing{display:flex;align-items:center;gap:8px;justify-content:center;font-size:12px;color:var(--accent);margin-bottom:18px}
  .syncing .spin{width:13px;height:13px;border:2px solid var(--accent-soft);border-top-color:var(--accent);border-radius:50%;animation:sp .7s linear infinite}

  .foot{max-width:540px;width:100%;text-align:center;font-size:11.5px;color:var(--faint);margin-top:18px}
  .hidden{display:none!important}
</style>
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

      <button class="btn" type="submit">完成設定</button>
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
</script>
@endpush
