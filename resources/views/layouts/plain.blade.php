<!DOCTYPE html>
<html lang="ja">
<head>
  <meta name="google-site-verification" content="Hm1aViMm6nmP3Fy_Dk_t1x7g422GA0PAgv7jI6CyFx8" />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#9d6b8b">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', config('app.name') . ' | 地図で探す・見学会の混雑がわかる結婚式場マップ')</title>
  <meta name="description" content="@yield('description', '全国の結婚式場を地図から探せる投稿型マップです。現在地から近い会場をすぐ見つけられ、見学会の混雑状況や写真付き口コミをリアルタイムで確認できます。')">
  <link rel="canonical" href="{{ url()->current() }}">

  <meta property="og:site_name" content="{{ config('app.name') }}">
  <meta property="og:type" content="website">
  <meta property="og:title" content="@yield('title', config('app.name') . ' | 地図で探す・見学会の混雑がわかる結婚式場マップ')">
  <meta property="og:description" content="@yield('description', '全国の結婚式場を地図から探せる投稿型マップです。現在地から近い会場をすぐ見つけられ、見学会の混雑状況や写真付き口コミをリアルタイムで確認できます。')">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:locale" content="ja_JP">

  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="@yield('title', config('app.name') . ' | 地図で探す・見学会の混雑がわかる結婚式場マップ')">
  <meta name="twitter:description" content="@yield('description', '全国の結婚式場を地図から探せる投稿型マップです。現在地から近い会場をすぐ見つけられ、見学会の混雑状況や写真付き口コミをリアルタイムで確認できます。')">

  <link rel="icon" href="/favicon.ico" sizes="any">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background-color: #faf7f8; font-family: system-ui, -apple-system, sans-serif; }
    .btn { min-height: 44px; }
    .btn-line { background: #06c755; color: #fff; border: none; }
    .btn-line:hover { background: #05a848; color: #fff; }
    .btn-wedding { background: #9d6b8b; color: #fff; border: none; }
    .btn-wedding:hover { background: #85567b; color: #fff; }
  </style>
  @yield('styles')

  @stack('structured-data')
  @if(config('services.ga4.id'))
  <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.ga4.id') }}"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('services.ga4.id') }}');
  </script>
  @endif
</head>
<body>
  <nav class="navbar navbar-dark p-2" style="background-color:#5c3a4e;">
    <div class="container-fluid">
      <a href="{{ route('venues.index') }}" class="navbar-brand text-white text-decoration-none">💒 {{ config('app.name') }}</a>
      <a href="{{ route('about') }}" class="text-white small text-decoration-none">サイトについて</a>
    </div>
  </nav>

  @yield('content')

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @yield('scripts')
</body>
</html>
