@extends('layouts.spectrum')

@section('title', 'Spectrum · 我的 Repository')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/card.css') }}">
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
      <div class="who"><div class="u">@@virtualorz</div><div class="r">已連接 · API 額度 4,998 / 5,000</div></div>
      <a class="change" href="{{ route('setup') }}">更換</a>
    </div>

    <div class="seclab">選擇要追蹤的 repository</div>
    <div class="secsub">已自動偵測哪些 repo 含有 <span class="mono">specflow/</span> 目錄;只有含此目錄的 repo 能被分析。</div>

    <div id="repoList"></div>

    <div class="addrow">
      <input id="addRepo" placeholder="手動加入 owner/repo" spellcheck="false">
      <button id="addBtn" type="button">加入</button>
    </div>

    <div class="interval">
      <div class="il">同步頻率<div class="s">背景排程多久去 GitHub 拉一次新記錄</div></div>
      <select id="freq">
        <option value="5">每 5 分鐘</option>
        <option value="15" selected>每 15 分鐘</option>
        <option value="30">每 30 分鐘</option>
        <option value="60">每小時</option>
      </select>
    </div>

    <div class="btnrow">
      <button class="btn" id="finish">儲存設定</button>
    </div>
  </div>

  <div class="foot">自架單體服務 · 資料只留在你自己的機器上</div>
@endsection

@push('scripts')
@verbatim
<script>
  const $ = id => document.getElementById(id);
  const CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M4 12.5l5 5 11-12"/></svg>';

  // 寫死的 demo repo 資料(GitHub API 接上後改為真實資料)
  const repos = [
    {full:'virtualorz/spectrum', priv:true,  flow:true},
    {full:'virtualorz/specflow', priv:false, flow:true},
    {full:'virtualorz/keepie',   priv:true,  flow:true},
    {full:'virtualorz/neijin-tw',priv:true,  flow:false},
    {full:'virtualorz/dotfiles', priv:false, flow:false}
  ];
  const selected = new Set(['virtualorz/spectrum','virtualorz/specflow']);

  function renderRepos(){
    $('repoList').innerHTML = repos.map(r => {
      const sel = selected.has(r.full);
      const tags = (r.priv?'<span class="tag priv">private</span>':'<span class="tag pub">public</span>')
        + (r.flow?'<span class="tag has">含 specflow/</span>':'<span style="color:var(--faint)">無 specflow/ 目錄</span>');
      return '<div class="repo '+(sel?'sel':'')+' '+(r.flow?'':'noflow')+'" data-r="'+r.full+'">'
        + '<div class="cb">'+CHECK+'</div>'
        + '<div class="info"><div class="rn">'+r.full+'</div><div class="rt">'+tags+'</div></div></div>';
    }).join('');
    document.querySelectorAll('.repo').forEach(el => {
      el.onclick = () => {
        const f = el.dataset.r; const r = repos.find(x => x.full === f);
        if(!r.flow) return; // 無 specflow/ 不可追蹤
        selected.has(f) ? selected.delete(f) : selected.add(f);
        renderRepos(); updateFinish();
      };
    });
  }
  function updateFinish(){ $('finish').disabled = selected.size === 0; }

  $('addBtn').onclick = () => {
    const v = $('addRepo').value.trim();
    if(!/^[\w.-]+\/[\w.-]+$/.test(v)) return;
    if(!repos.find(r => r.full === v)) repos.push({full:v, priv:true, flow:true});
    selected.add(v); $('addRepo').value=''; renderRepos(); updateFinish();
  };
  $('addRepo').addEventListener('keydown', e => { if(e.key === 'Enter') $('addBtn').click(); });

  renderRepos(); updateFinish();
</script>
@endverbatim
@endpush
