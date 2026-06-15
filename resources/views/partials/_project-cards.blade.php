{{-- 共用:overview 初始渲染 + /project/search 搜尋結果。依賴 $projects(LedgerService::build 輸出)。 --}}
@forelse ($projects as $p)
  @php
    $bars = $p['spark'] ?? [];
    $maxTok = collect($bars)->max('tokens') ?: 1;
  @endphp
  <div class="pcard" role="link" tabindex="0" data-href="{{ route('summary', $p['id']) }}" style="cursor:pointer">
    <div class="pn">{{ $p['display_name'] }}</div>
    <div class="pt">{{ $p['tech_stack'] ?? $p['full_name'] }}</div>

    <div class="pstats">
      <div class="ps"><div class="l">spec 總數</div><div class="v">{{ $p['stats']['total'] }}</div></div>
      <div class="ps"><div class="l">已完成</div><div class="v g">{{ $p['stats']['closed'] }}</div></div>
      <div class="ps"><div class="l">累計 token</div><div class="v">{{ number_format($p['stats']['tokens']) }}</div></div>
      <div class="ps"><div class="l">累計跨度</div><div class="v">{{ $p['stats']['span_human'] }}</div></div>
    </div>

    <div class="spark">
      @foreach ($bars as $b)
        <div class="b {{ $b['running'] ? 'run' : '' }}" style="height:{{ max(3, (int) round($b['tokens'] / $maxTok * 30)) }}px"></div>
      @endforeach
    </div>

    <div class="act">
      <span>{{ $p['last_synced_at'] ? '最後同步 '.$p['last_synced_at']->format('Y-m-d H:i') : '尚未同步' }}</span>
    </div>
  </div>
@empty
  <div class="psum" style="padding:48px;text-align:center">
    <div class="big">{{ ($q ?? '') !== '' ? '查無符合的專案' : '尚未追蹤任何 repository' }}</div>
    <div class="unit">到 <a href="{{ route('repository') }}">Repository 設定頁</a> 勾選要追蹤的專案,這裡就會出現它們的 specflow 紀錄。</div>
  </div>
@endforelse
