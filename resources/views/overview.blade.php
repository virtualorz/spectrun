@extends('layouts.spectrum')

@section('title', 'specflow ledger · 專案總覽層')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/overview.css') }}">
@endpush

@section('content')
<div class="wrap">
  <div class="top">
    <x-brand />
    <div class="top-right"><x-user-menu /><button class="icbtn" id="theme" aria-label="切換亮暗主題">◐</button></div>
  </div>

  <div class="crumb"><span class="cur">所有專案</span></div>

  <div class="pgrid">
    @forelse ($projects as $p)
      @php
        $changes = $p['changes'] ?? [];
        $total = count($changes);
        $closed = collect($changes)->where('status', 'closed')->count();
        $tokens = collect($changes)->sum(fn ($c) => (int) ($c['tokens_at_close'] ?? 0));
      @endphp
      <div class="pcard" role="link" tabindex="0" data-href="{{ route('summary', $p['id']) }}" style="cursor:pointer">
        <div class="pn">{{ $p['display_name'] }}</div>
        <div class="pt">{{ $p['tech_stack'] ?? $p['full_name'] }}</div>

        <div class="pstats">
          <div class="ps"><div class="l">spec 總數</div><div class="v">{{ $total }}</div></div>
          <div class="ps"><div class="l">已完成</div><div class="v g">{{ $closed }}</div></div>
          <div class="ps"><div class="l">累計 token</div><div class="v">{{ number_format($tokens) }}</div></div>
          <div class="ps"><div class="l">specflow</div><div class="v">{{ $p['has_specflow'] ? '✓' : '—' }}</div></div>
        </div>

        @if ($total > 0)
          <div class="clist">
            @foreach ($changes as $c)
              <div class="citem">
                <span class="iid">{{ $c['number'] }}</span>
                {{ $c['title'] }}
                <span class="badge {{ $c['status'] === 'closed' ? 'closed' : 'run' }}">{{ $c['status'] ?? '—' }}</span>
                <span class="sep">·</span>決策 {{ $c['decisions_done'] }}/{{ $c['decisions_total'] }}
                <span class="sep">·</span>任務 {{ $c['tasks_done'] }}/{{ $c['tasks_total'] }}
              </div>
            @endforeach
          </div>
        @endif

        <div class="act">
          <span>{{ $p['last_synced_at'] ? '最後同步 '.$p['last_synced_at']->format('Y-m-d H:i') : '尚未同步' }}</span>
        </div>
      </div>
    @empty
      <div class="psum" style="padding:48px;text-align:center">
        <div class="big">尚未追蹤任何 repository</div>
        <div class="unit">到 <a href="{{ route('repository') }}">Repository 設定頁</a> 勾選要追蹤的專案,這裡就會出現它們的 specflow 紀錄。</div>
      </div>
    @endforelse
  </div>
</div>
@endsection

@push('scripts')
<script>
  // 卡片點擊跳轉(用事件處理,避免 <a> 的連結配色影響卡片內文)
  document.querySelectorAll('.pcard[data-href]').forEach(function (el) {
    el.addEventListener('click', function () { window.location = el.dataset.href; });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.location = el.dataset.href; }
    });
  });
</script>
@endpush
