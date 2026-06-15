@extends('layouts.spectrum')

@section('title', 'spectrun · 時間軸視圖')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/timeline.css') }}?v={{ filemtime(public_path('css/timeline.css')) }}">
@endpush

@section('content')
@php $ROW = 30; @endphp
<div class="wrap">
  <div class="top">
    <x-brand />
    <div class="top-right">
      <x-user-menu />
      <a class="icbtn" href="{{ route('summary', $projectId) }}" aria-label="切換到摘要" title="切換到摘要">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
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
          <a class="navitem {{ $p->id === $projectId ? 'active' : '' }}" href="{{ route('timeline', $p->id) }}">
            <span class="nm">{{ $p->display_name ?? $p->full_name }}</span>
            <span class="cnt">{{ $p->changes->count() }}</span>
          </a>
        @endforeach
      </nav>
    </aside>

    <main class="main">
      <div class="legend">
        <div class="legend-l">
          <span>橫軸為日期(wall-clock)</span>
          <span><i style="background:var(--ph1)"></i>規格</span><span><i style="background:var(--ph2)"></i>設計</span><span><i style="background:var(--ph3)"></i>實作</span><span><i style="background:var(--run)"></i>執行中</span>
        </div>
        <select id="branchSel" data-project="{{ $projectId }}" title="specflow 分支">
          <option value="">分支…</option>
        </select>
      </div>

      @if (! empty($selected['changes']))
        <div id="chart">
          <div class="gutter">
            <div class="ghdr"></div>
            @foreach ($selected['changes'] as $c)
              <div class="gcell" style="height:{{ $ROW }}px"><b>{{ $c['number'] }}</b> {{ $c['title'] }}</div>
            @endforeach
          </div>
          <div class="lane">
            <div class="axis">
              @foreach ($selected['ticks'] as $t)
                <div class="tick" style="left:{{ $t['pct'] }}%">{{ $t['label'] }}</div>
              @endforeach
            </div>
            <div class="bands" style="height:{{ count($selected['changes']) * $ROW }}px">
              @foreach ($selected['ticks'] as $t)
                <div class="grid" style="left:{{ $t['pct'] }}%"></div>
              @endforeach
              @foreach ($selected['changes'] as $i => $c)
                @php $closed = $c['status'] === 'closed'; @endphp
                <div class="gbar {{ $closed ? '' : 'run' }}" style="left:{{ $c['left'] }}%;width:{{ $c['width'] }}%;top:{{ $i * $ROW + 7 }}px" title="{{ $c['number'] }} {{ $c['title'] }}">
                  <span class="sg sg1" style="flex:{{ $c['seg']['spec'] }}"></span>
                  <span class="sg sg2" style="flex:{{ $c['seg']['design'] }}"></span>
                  <span class="sg sg3" style="flex:{{ $c['seg']['impl'] }}"></span>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @else
        <div class="hint">此專案尚無 spec change 紀錄 —— 按右上「同步專案資訊」從 GitHub 拉取。</div>
      @endif
    </main>
  </div>
</div>
@endsection

@include('partials._sync-branch-scripts')
