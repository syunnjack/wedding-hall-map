@extends('layouts.plain')

@php
    $pageTitle = $area
        ? $area . 'の結婚式場' . number_format($total) . '会場｜' . config('app.name', '結婚式場マップ')
        : config('app.name', '結婚式場マップ') . ' | 全国の結婚式場を地図から探す';
    $pageDescription = $area
        ? $area . 'の結婚式場' . number_format($total) . '会場を、地図と一覧から探せます。見学会の混雑状況と口コミは利用者の投稿です。'
        : '全国' . number_format($total) . '会場の結婚式場を地図から探せます。現在地から近い会場を調べたり、見学会の混雑状況を確認できます。';
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
  '@@context' => 'https://schema.org',
  '@type' => 'WebSite',
  'name' => config('app.name'),
  'url' => url('/'),
  'description' => '全国の結婚式場を地図から検索できる投稿型マップ。見学会の混雑状況・写真付き口コミを確認できる。',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
{{-- 投稿が0件のときは itemListElement が空になる。空のItemListはGoogleに
     無効な項目として扱われるため、1件以上あるときだけ出力する。 --}}
@if ($venues->isNotEmpty())
<script type="application/ld+json">
{!! json_encode([
  '@@context' => 'https://schema.org',
  '@type' => 'ItemList',
  'itemListElement' => $venues->take(50)->values()->map(function ($venue, $i) {
      return [
          '@type' => 'ListItem',
          'position' => $i + 1,
          'url' => url("/venues/{$venue->id}"),
          'name' => $venue->name,
      ];
  })->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endif
@endpush

@section('content')
<div class="container my-4">
  <div class="text-center mb-4">
    @if($area)
      <nav aria-label="パンくず" class="small mb-2">
        <a href="{{ route('venues.index') }}">結婚式場マップ</a>
        <span class="text-muted mx-1">/</span><span class="text-muted">{{ $area }}</span>
      </nav>
      <h1 class="fw-bold h3">{{ $area }}の結婚式場</h1>
      <p class="text-muted">{{ number_format($total) }}会場を掲載しています。見学会の混雑と口コミは利用者の投稿です。</p>
    @else
      <h1 class="fw-bold h3">💒 結婚式場マップ</h1>
      <p class="text-muted">全国{{ number_format($total) }}会場から、現在地や都道府県で探せます。</p>
    @endif
    <a href="{{ route('venues.create') }}" class="btn btn-wedding shadow-sm px-4">➕ 結婚式場を投稿</a>
  </div>

  <div class="d-flex justify-content-center mb-3">
    <button id="locateButton" class="btn btn-outline-primary">📍 現在地から近い順に探す</button>
  </div>
  <p id="locateMessage" class="text-center text-muted small mb-3"></p>

  @php
      $mapVenues = $venues->getCollection()->map(fn ($v) => [
          'id' => $v->id, 'name' => $v->name, 'area' => $v->area, 'lat' => $v->lat, 'lng' => $v->lng,
      ])->values();
  @endphp
  <div id="map" data-venues="{{ $mapVenues->toJson() }}" style="height: 360px;" class="rounded shadow-sm border mb-4"></div>

  @if($areaCounts->isNotEmpty())
    <h2 class="h6">都道府県から探す</h2>
    <p class="d-flex flex-wrap gap-2 mb-4">
      @foreach($areaCounts as $row)
        <a href="{{ route('venues.area', $row['slug']) }}"
           class="btn btn-sm {{ $areaSlug === $row['slug'] ? 'btn-primary' : 'btn-outline-secondary' }}">
          {{ $row['area'] }} <span class="text-muted">{{ number_format($row['total']) }}</span>
        </a>
      @endforeach
    </p>
  @endif

  <div class="row" id="venueList">
    @forelse($venues as $venue)
      <div class="col-md-6 col-lg-4 mb-3" data-venue-card data-lat="{{ $venue->lat }}" data-lng="{{ $venue->lng }}">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h2 class="h6 card-title">
              <a href="{{ route('venues.show', $venue) }}" class="text-decoration-none">{{ $venue->name }}</a>
              <span class="badge bg-secondary float-end">{{ $venue->area ?? '未設定' }}</span>
            </h2>
            <p class="card-text text-muted small">{{ $venue->description }}</p>
            <small class="text-muted d-block">見学会の混雑状況：{{ \App\Helpers\CongestionHelper::getText($venue->average_congestion) }}</small>
            <small class="text-muted d-block distance-label"></small>
          </div>
        </div>
      </div>
    @empty
      <p class="text-muted">該当する結婚式場がありません。</p>
    @endforelse
  </div>

  <div class="d-flex justify-content-center my-3">
    {{ $venues->onEachSide(1)->links() }}
  </div>

  <p class="text-muted small">
    会場の名称・位置は OpenStreetMap のデータをもとにしています（© OpenStreetMap contributors、ODbL 1.0）。
    見学会の混雑状況と口コミは利用者の投稿で、当サイトでは内容を確認していません。
    実際の挙式の可否、料金、空き状況は各会場へお問い合わせください。
  </p>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const mapEl = document.getElementById('map');
    const venues = JSON.parse(mapEl.dataset.venues || '[]');

    const map = L.map('map').setView([35.6812, 139.7671], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    venues.forEach(function (v) {
      L.marker([v.lat, v.lng]).addTo(map)
        .bindPopup('<a href="/venues/' + v.id + '">' + v.name + '</a><br><small>' + (v.area || '') + '</small>');
    });

    function haversineKm(lat1, lng1, lat2, lng2) {
      const R = 6371;
      const dLat = (lat2 - lat1) * Math.PI / 180;
      const dLng = (lng2 - lng1) * Math.PI / 180;
      const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    const locateButton = document.getElementById('locateButton');
    const locateMessage = document.getElementById('locateMessage');

    locateButton.addEventListener('click', function () {
      if (!navigator.geolocation) {
        locateMessage.textContent = 'このブラウザは現在地取得に対応していません。';
        return;
      }

      locateMessage.textContent = '現在地を取得しています…';

      navigator.geolocation.getCurrentPosition(function (position) {
        const userLat = position.coords.latitude;
        const userLng = position.coords.longitude;

        map.setView([userLat, userLng], 11);
        L.marker([userLat, userLng], { title: '現在地' })
          .addTo(map)
          .bindPopup('現在地')
          .openPopup();

        const cards = Array.from(document.querySelectorAll('[data-venue-card]'));
        cards.forEach(function (card) {
          const lat = parseFloat(card.dataset.lat);
          const lng = parseFloat(card.dataset.lng);
          const distance = haversineKm(userLat, userLng, lat, lng);
          card.dataset.distance = distance;
          const label = card.querySelector('.distance-label');
          if (label) label.textContent = '現在地から約' + distance.toFixed(1) + 'km';
        });

        cards.sort(function (a, b) {
          return parseFloat(a.dataset.distance) - parseFloat(b.dataset.distance);
        });

        const list = document.getElementById('venueList');
        cards.forEach(function (card) { list.appendChild(card); });

        locateMessage.textContent = '現在地から近い順に並び替えました。';
      }, function () {
        locateMessage.textContent = '現在地を取得できませんでした。ブラウザの位置情報許可をご確認ください。';
      });
    });
  });
</script>
@endsection
