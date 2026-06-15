@extends('layouts.spectrum')

@section('title', 'Spectrum · 我的 Repository')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/card.css') }}?v={{ filemtime(public_path('css/card.css')) }}">
@endpush

@section('content')
  <div class="topbar">
    <x-brand />
    <button class="icbtn" id="theme" aria-label="切換亮暗主題">◐</button>
  </div>

  <div class="card">
    <h1 class="h1">我的 Repository</h1>
    <p class="lede">選擇要監控的 GitHub repository(只有含 specflow/ 目錄的 repo 能被分析)。</p>

    <div class="conn">
      <div class="ava"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg></div>
      <div class="who"><div class="u">我的 GitHub</div><div class="r">已連接</div></div>
      <a class="change" href="{{ route('setup') }}">更換</a>
    </div>

    @if (! empty($error))
      <div class="msg err show" style="margin:14px 0"><span>{{ $error }}</span></div>
    @endif
    @error('repository')
      <div class="msg err show" style="margin:14px 0"><span>{{ $message }}</span></div>
    @enderror

    <div class="seclab">選擇要追蹤的 repository</div>
    <div class="secsub">已自動偵測哪些 repo 含有 <span class="mono">specflow/</span> 目錄;只有含此目錄的 repo 能被分析。</div>

    <form method="POST" action="{{ route('repository.store') }}">
      @csrf
      <div id="flowNotice" class="msg err show" style="display:none;margin:0 0 14px"><span></span></div>
      <div id="repoList">
        @forelse ($repos as $r)
          @php $flow = $r['has_specflow'] ?? null; @endphp
          <label class="repo {{ ($r['selected'] ?? false) ? 'sel' : '' }} {{ $flow === false ? 'noflow' : '' }}" data-full-name="{{ $r['full_name'] }}">
            <input type="checkbox" class="repocb" name="selected[]" value="{{ $r['full_name'] }}"
                   style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                   @checked($r['selected'] ?? false) @disabled($flow !== true)>
            <div class="cb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M4 12.5l5 5 11-12"/></svg></div>
            <div class="info">
              <div class="rn">{{ $r['full_name'] }}</div>
              <div class="rt">
                @if ($r['is_private'])
                  <span class="tag priv">private</span>
                @else
                  <span class="tag pub">public</span>
                @endif
                <span class="flowtag">
                  @if ($flow === true)
                    <span class="tag has">含 specflow/</span>
                  @elseif ($flow === false)
                    <span style="color:var(--faint)">無 specflow/ 目錄</span>
                  @else
                    <span style="color:var(--faint)">偵測中…</span>
                  @endif
                </span>
              </div>
            </div>
          </label>
        @empty
          <div class="secsub" style="padding:14px 0">沒有可顯示的 repository。</div>
        @endforelse
      </div>

      <div class="btnrow">
        <button class="btn" type="submit">儲存設定</button>
      </div>
    </form>
  </div>

  <div class="foot">自架單體服務 · 資料只留在你自己的機器上</div>
@endsection

@push('scripts')
<script>
  // 勾選時同步 .sel 視覺(無 specflow/ 的 checkbox 為 disabled,不會觸發)
  document.querySelectorAll('.repo .repocb').forEach(function (cb) {
    cb.addEventListener('change', function () {
      cb.closest('.repo').classList.toggle('sel', cb.checked);
    });
  });

  // C+ 漸進載入:頁面已秒開,這裡再 fetch 各 repo 的 specflow 旗標逐列補上
  (function () {
    var list = document.getElementById('repoList');
    var notice = document.getElementById('flowNotice');
    if (!list || !list.querySelector('.repo')) return;

    function showNotice(msg) {
      if (!notice) return;
      notice.querySelector('span').textContent = msg;
      notice.style.display = '';
    }

    fetch('{{ route('repository.specflow') }}', { headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (r.status === 401) { showNotice('GitHub token 失效,請到設定頁更換。'); return null; }
        return r.json();
      })
      .then(function (d) {
        if (!d) return;
        if (d.tokenInvalid) { showNotice('GitHub token 失效,請到設定頁更換。'); }
        var flags = d.flags || {};
        // 遍歷各列、用 dataset 比對,避免 querySelector 對含「/」名稱的轉義問題
        list.querySelectorAll('.repo[data-full-name]').forEach(function (label) {
          var name = label.getAttribute('data-full-name');
          if (!(name in flags)) return;
          var tag = label.querySelector('.flowtag');
          var cb = label.querySelector('.repocb');
          if (flags[name]) {
            if (tag) tag.innerHTML = '<span class="tag has">含 specflow/</span>';
            label.classList.remove('noflow');
            if (cb) cb.disabled = false;
          } else {
            if (tag) tag.innerHTML = '<span style="color:var(--faint)">無 specflow/ 目錄</span>';
            label.classList.add('noflow');
            if (cb) cb.disabled = true;
          }
        });
        if (d.rateLimited) { showNotice('GitHub 額度用罄,部分 repo 尚未偵測完成,請稍後重新整理。'); }
      })
      .catch(function () { /* 靜默,保留 pending 顯示 */ });
  })();
</script>
@endpush
