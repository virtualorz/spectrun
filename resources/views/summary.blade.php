@extends('layouts.spectrum')

@section('title', 'specflow ledger · 專案摘要')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/summary.css') }}?v={{ filemtime(public_path('css/summary.css')) }}">
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

@include('partials._sync-branch-scripts')
