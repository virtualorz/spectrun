@extends('layouts.spectrum')

@section('title', 'specflow ledger · 專案摘要')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/summary.css') }}">
@endpush

@section('content')
<div class="wrap">
  <div class="top">
    <x-brand />
    <div class="top-right">
      <x-user-menu />
      <a class="icbtn" href="{{ route('timeline', $projectId) }}" aria-label="切換到時間軸" title="切換到時間軸">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
      </a>
      <button class="icbtn" type="button" id="syncBtn" data-project="{{ $projectId }}" aria-label="同步專案資訊" title="同步專案資訊">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
      </button>
      <button class="icbtn" id="theme" aria-label="切換亮暗主題">◐</button>
    </div>
  </div>

  <div id="syncMsg" style="display:none;margin:8px 0;padding:8px 12px;border-radius:8px;background:var(--card,#1a1a1a);font-size:13px"></div>

  <div class="shell">
    <aside class="side">
      <div class="sidelab">專案</div>
      <nav>
        @foreach ($nav as $p)
          <a class="navitem {{ $p->id === $projectId ? 'active' : '' }}" href="{{ route('summary', $p->id) }}">
            <span class="nm">{{ $p->display_name ?? $p->full_name }}</span>
            <span class="cnt">{{ $p->changes->count() }}</span>
          </a>
        @endforeach
      </nav>
    </aside>

    <main class="main">
      @if ($selected['header'])
        <div class="legend">
          <span>{{ $selected['header']['display_name'] }} · {{ $selected['header']['tech_stack'] ?? '—' }} · {{ $selected['header']['total'] }} 筆 change(已完成 {{ $selected['header']['closed'] }}、累計 {{ number_format($selected['header']['tokens']) }} tok)</span>
          <span><i style="background:var(--ph1)"></i>規格</span><span><i style="background:var(--ph2)"></i>設計</span><span><i style="background:var(--ph3)"></i>實作</span>
        </div>
      @endif

      @forelse ($selected['changes'] as $c)
        <div class="ihead">
          <span class="iid">{{ $c['number'] }}</span>
          <span class="ititle">{{ $c['title'] }}</span>
          <span class="badge {{ $c['status'] === 'closed' ? 'closed' : 'run' }}">{{ $c['status'] }}</span>
          <span class="unit">{{ number_format($c['tokens']) }} tok</span>
          <div class="segbar" title="規格/設計/實作 時間比例">
            <span class="sg sg1" style="flex:{{ $c['seg']['spec'] }}"></span>
            <span class="sg sg2" style="flex:{{ $c['seg']['design'] }}"></span>
            <span class="sg sg3" style="flex:{{ $c['seg']['impl'] }}"></span>
          </div>
        </div>
      @empty
        <div class="legend">此專案尚無 spec change 紀錄 —— 按右上「同步專案資訊」從 GitHub 拉取。</div>
      @endforelse
    </main>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var btn = document.getElementById('syncBtn');
  var msg = document.getElementById('syncMsg');
  if (!btn) return;

  btn.addEventListener('click', function () {
    var pid = btn.dataset.project;
    if (!pid) return;

    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = '…';
    msg.style.display = 'block';
    msg.textContent = '同步中…(從 GitHub 拉取,可能需要一些時間)';

    fetch('{{ route('repository.sync') }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'text/html',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({ project: pid })
    }).then(function (res) {
      if (res.status >= 400) throw new Error('sync failed');
      msg.innerHTML = '同步完成,點「重新整理」查看最新資料 ';
      var rb = document.createElement('button');
      rb.type = 'button';
      rb.className = 'icbtn';
      rb.style.width = 'auto';
      rb.style.padding = '0 10px';
      rb.textContent = '重新整理';
      rb.addEventListener('click', function () { location.reload(); });
      msg.appendChild(rb);
    }).catch(function () {
      msg.textContent = '同步失敗,請稍後再試';
    }).finally(function () {
      btn.disabled = false;
      btn.innerHTML = orig;
    });
  });
})();
</script>
@endpush
