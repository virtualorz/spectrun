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
      <div id="repoList">
        @forelse ($repos as $r)
          <label class="repo {{ ($r['selected'] ?? false) ? 'sel' : '' }} {{ $r['has_specflow'] ? '' : 'noflow' }}">
            <input type="checkbox" class="repocb" name="selected[]" value="{{ $r['full_name'] }}"
                   style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                   @checked($r['selected'] ?? false) @disabled(! $r['has_specflow'])>
            <div class="cb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M4 12.5l5 5 11-12"/></svg></div>
            <div class="info">
              <div class="rn">{{ $r['full_name'] }}</div>
              <div class="rt">
                @if ($r['is_private'])
                  <span class="tag priv">private</span>
                @else
                  <span class="tag pub">public</span>
                @endif
                @if ($r['has_specflow'])
                  <span class="tag has">含 specflow/</span>
                @else
                  <span style="color:var(--faint)">無 specflow/ 目錄</span>
                @endif
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
</script>
@endpush
