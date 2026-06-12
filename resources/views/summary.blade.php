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
      @php
        // 秒數 → 人類可讀跨度
        $humanSpan = function (int $sec): string {
            if ($sec < 60) {
                return '<1m';
            }
            $d = intdiv($sec, 86400);
            $h = intdiv($sec % 86400, 3600);
            $m = intdiv($sec % 3600, 60);
            if ($d > 0) {
                return $d.'d'.($h > 0 ? ' '.$h.'h' : '');
            }
            if ($h > 0) {
                return $h.'h'.($m > 0 ? ' '.$m.'m' : '');
            }
            return $m.'m';
        };
      @endphp

      @if ($selected['header'])
        <div class="summaryline1">語言/框架:{{ $selected['header']['tech_stack'] ?? '—' }} · {{ $selected['header']['total'] }} 筆 change(已完成 {{ $selected['header']['closed'] }}、累計 {{ number_format($selected['header']['tokens']) }} tok)</div>
        <div class="legend">
          <div class="legend-l">
            <span>左側大數字 = 總跨度(wall-clock);細條為四階段比例,滑過顯各段時間</span>
            <span><i style="background:var(--ph1)"></i>規格</span><span><i style="background:var(--ph2)"></i>設計</span><span><i style="background:var(--ph3)"></i>實作</span>
          </div>
          <select id="branchSel" data-project="{{ $projectId }}" title="specflow 分支">
            <option value="">分支…</option>
          </select>
        </div>
      @endif

      @forelse ($selected['changes'] as $c)
        @php
          $total = $c['seg']['spec'] + $c['seg']['design'] + $c['seg']['impl'];
          $closed = $c['status'] === 'closed';
        @endphp
        <div class="issue">
          <div class="ihead">
            <div class="hero">
              <div class="big {{ $closed ? '' : 'run' }}">{{ $closed ? $humanSpan($total) : '進行中' }}</div>
              <div class="unit">{{ $closed ? '總跨度' : '進行中' }}</div>
              <div class="range">{{ $c['issued_at']?->format('m-d') }}{{ $closed && $c['closed_at'] ? ' → '.$c['closed_at']->format('m-d') : ($c['issued_at'] ? ' 起' : '') }}</div>
            </div>
            <div class="mbody">
              <div class="mrow1">
                <div class="iid"><b>{{ $c['number'] }}</b> · {{ $c['slug'] }}</div>
                <span class="badge {{ $closed ? 'closed' : 'running' }}">{{ $c['status'] }}</span>
              </div>
              <div class="ititle">{{ $c['title'] }}</div>
              <div class="segwrap">
                <div class="segbar" title="規格/設計/實作 時間比例">
                  <span class="sg sg1" style="flex:{{ $c['seg']['spec'] }}"></span>
                  <span class="sg sg2" style="flex:{{ $c['seg']['design'] }}"></span>
                  <span class="sg sg3" style="flex:{{ $c['seg']['impl'] }}"></span>
                </div>
                <span class="seghint">new → close</span>
              </div>
              <div class="meta">
                <span class="chip mono">{{ number_format($c['tokens']) }} tok</span>
                <span class="chip">決策 {{ $c['decisions_done'] }}/{{ $c['decisions_total'] }}</span>
                <span class="chip">任務 {{ $c['tasks_done'] }}/{{ $c['tasks_total'] }}</span>
                <span class="chip">討論 {{ $c['discussion_count'] }}</span>
              </div>
            </div>
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
  // ── specflow 分支下拉(lazy 載入 + 改選存檔)──
  var sel = document.getElementById('branchSel');
  var bmsg = document.getElementById('syncMsg');
  if (sel) {
    var pid = sel.dataset.project;
    var loaded = false;
    var load = function () {
      if (loaded) return;
      loaded = true;
      fetch('{{ url('repository') }}/' + pid + '/branches', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.branches) return;
          sel.innerHTML = '';
          d.branches.forEach(function (b) {
            var o = document.createElement('option');
            o.value = b; o.textContent = b;
            if (b === d.current) o.selected = true;
            sel.appendChild(o);
          });
        }).catch(function () { loaded = false; });
    };
    sel.addEventListener('focus', load);
    sel.addEventListener('change', function () {
      var branch = sel.value;
      if (!branch) return;
      fetch('{{ url('repository') }}/' + pid + '/branch', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ branch: branch })
      }).then(function (r) {
        if (!r.ok) throw new Error();
        bmsg.style.display = 'block';
        bmsg.textContent = 'specflow 分支已設為 ' + branch + ',按右上同步重新拉取。';
      }).catch(function () {
        bmsg.style.display = 'block';
        bmsg.textContent = '分支設定失敗,請稍後再試。';
      });
    });
  }

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
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams({ project: pid })
    }).then(function (res) {
      return res.json().then(function (d) { return { ok: res.ok, status: res.status, data: d }; });
    }).then(function (r) {
      var d = r.data || {};
      if (!r.ok) {
        msg.textContent = d.error || '同步失敗,請稍後再試';
        return;
      }
      var txt = '已同步 ' + (d.changes || 0) + ' 筆 change(成功 ' + (d.ok || 0) + '、失敗 ' + (d.failed || 0) + ')';
      if (d.aborted) {
        txt += '。' + d.aborted;
      } else if ((d.changes || 0) === 0) {
        txt += '。抓到 0 筆 —— 該 repo 的 specflow/changes 可能不在預設分支、尚未推上 GitHub、或還沒有 change。';
      }
      msg.textContent = txt + ' ';
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
