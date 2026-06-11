<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Spectrum')</title>
<link rel="stylesheet" href="{{ asset('css/spectrum.css') }}">
@stack('styles')
</head>
<body>
@yield('content')

<script>
  // shared theme toggle —— localStorage `sf-theme` ↔ html.light(4 頁共用)
  (function(){try{if(localStorage.getItem('sf-theme')==='light')document.documentElement.classList.add('light');}catch(e){}
    var t=document.getElementById('theme');
    if(t){t.onclick=()=>{document.documentElement.classList.toggle('light');
      try{localStorage.setItem('sf-theme',document.documentElement.classList.contains('light')?'light':'dark');}catch(e){}};}})();
</script>
@stack('scripts')
</body>
</html>
