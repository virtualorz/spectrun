@extends('layouts.spectrum')

@section('title', 'specflow ledger · 專案總覽層')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/overview.css') }}?v={{ filemtime(public_path('css/overview.css')) }}">
@endpush

@section('content')
<div class="wrap">
  <div class="top">
    <x-brand />
    <div class="top-right"><x-user-menu /><button class="icbtn" id="theme" aria-label="切換亮暗主題">◐</button></div>
  </div>

  <div class="crumb"><span class="cur">所有專案</span></div>

  <div class="search"><input id="q" type="text" placeholder="搜尋專案…" autocomplete="off"></div>

  <div class="pgrid">
    @include('partials._project-cards', ['projects' => $projects])
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    var grid = document.querySelector('.pgrid');

    // 卡片點擊跳轉(可重呼叫:搜尋替換 grid 後重新綁定)
    function bindCards() {
      grid.querySelectorAll('.pcard[data-href]').forEach(function (el) {
        el.addEventListener('click', function () { window.location = el.dataset.href; });
        el.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.location = el.dataset.href; }
        });
      });
    }
    bindCards();

    // 搜尋框:輸入即搜(debounce ~250ms),回傳卡片 HTML 後替換 grid
    var q = document.getElementById('q');
    if (q) {
      var timer = null;
      q.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          fetch('{{ route('project.search') }}', {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'Accept': 'text/html',
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ keyword: q.value })
          })
            .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
            .then(function (html) { grid.innerHTML = html; bindCards(); })
            .catch(function () { /* 失敗靜默,保留現有結果 */ });
        }, 250);
      });
    }
  })();
</script>
@endpush
