@extends('layouts.spectrum')

@section('title', 'Spectrum · 登入')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/card.css') }}?v={{ filemtime(public_path('css/card.css')) }}">
@endpush

@section('content')
  <div class="topbar">
    <x-brand />
    <button class="icbtn" id="theme" aria-label="切換亮暗主題">◐</button>
  </div>

  <div class="card">
    <h1 class="h1">登入</h1>
    <p class="lede">輸入帳號與密碼以登入 Spectrum。</p>

    <form method="POST" action="{{ route('login.attempt') }}">
      @csrf
      <div class="field">
        <label class="flabel" for="account">帳號</label>
        <div class="inwrap">
          <input id="account" name="account" type="text" value="{{ old('account') }}" autocomplete="username" spellcheck="false">
        </div>
        @error('account')<div class="msg err show"><span>{{ $message }}</span></div>@enderror
      </div>

      <div class="field">
        <label class="flabel" for="password">密碼</label>
        <div class="inwrap">
          <input id="password" name="password" type="password" autocomplete="current-password">
        </div>
        @error('password')<div class="msg err show"><span>{{ $message }}</span></div>@enderror
      </div>

      <button class="btn" type="submit">登入</button>
    </form>
  </div>

  <div class="foot">自架單體服務 · 資料只留在你自己的機器上</div>
@endsection
