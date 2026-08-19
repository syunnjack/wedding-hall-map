@extends('layouts.plain')

@section('title', $venue->name . ' の見学会混雑状況・口コミ | ' . config('app.name'))
@section('description', $venue->name . '（' . ($venue->area ?? '結婚式場') . '）の場所・見学会の混雑状況・利用者の写真付き口コミを確認できます。')

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
  '@@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
      ['@type' => 'ListItem', 'position' => 2, 'name' => $venue->name, 'item' => url("/venues/{$venue->id}")],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
<script type="application/ld+json">
{!! json_encode(array_filter([
  '@@context' => 'https://schema.org',
  '@type' => 'EventVenue',
  'name' => $venue->name,
  'description' => $venue->description,
  'geo' => [
      '@type' => 'GeoCoordinates',
      'latitude' => $venue->lat,
      'longitude' => $venue->lng,
  ],
  'address' => $venue->address ?? $venue->area,
  'telephone' => $venue->phone,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h3 fw-bold mb-3">{{ $venue->name }}</h1>
      <p class="text-muted mb-2">{{ $venue->description }}</p>
      @if($venue->area)
        <p class="text-secondary small mb-1">エリア: {{ $venue->area }}</p>
      @endif
      @if($venue->address)
        <p class="text-secondary small mb-1">住所: {{ $venue->address }}</p>
      @endif
      @if($venue->phone)
        <p class="text-secondary small mb-4">電話: {{ $venue->phone }}</p>
      @endif

      <div class="mb-3">
        <a href="{{ route('venues.index') }}" class="btn btn-secondary">トップページに戻る</a>
      </div>

      @if (session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
      @endif
      @if ($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
      @endif

      <h2 class="h5 mb-2">
        見学会の混雑状況: <span id="currentAverageCongestion" class="text-primary fw-bold">
          {{ \App\Helpers\CongestionHelper::getText($venue->average_congestion) }}
        </span>
      </h2>

      <h3 class="h6 mt-4 mb-2">見学会の混雑状況を報告する</h3>
      <div id="congestionButtons" data-venue-id="{{ $venue->id }}" class="d-flex gap-2 mb-4 flex-wrap">
        <button data-level="empty" class="btn btn-success">空いている</button>
        <button data-level="slightly_crowded" class="btn btn-warning">やや混雑</button>
        <button data-level="crowded" class="btn btn-danger">混雑・満員</button>
      </div>
      <p id="congestionMessage" class="text-success small"></p>

      <form method="POST" action="{{ route('venues.favorite.toggle', $venue) }}" class="mb-3">
        @csrf
        @if ($isFavorited)
          <button type="submit" class="btn btn-outline-secondary">🔕 通知をやめる</button>
        @else
          {{-- LINEの認証情報が未設定のうちは、押すとLINE側でエラーになるので出さない --}}
          @if (config('services.line.login_channel_id'))
          <button type="submit" class="btn btn-line">🔔 見学会の混雑状況が変わったらLINEで通知</button>
          @else
            <button type="button" class="btn btn-secondary" disabled>🔔 見学会の混雑状況が変わったらLINEで通知（準備中）</button>
          @endif
        @endif
      </form>

      <form method="POST" action="{{ route('venues.document-request.store', $venue) }}" class="mb-4">
        @csrf
        @if ($hasRequestedDocument)
          <button type="submit" class="btn btn-outline-secondary" disabled>📮 資料請求済みです</button>
        @else
          {{-- LINEの認証情報が未設定のうちは、押すとLINE側でエラーになるので出さない --}}
          @if (config('services.line.login_channel_id'))
          <button type="submit" class="btn btn-line">📮 LINEで資料を請求する</button>
          @else
            <button type="button" class="btn btn-secondary" disabled>📮 LINEで資料を請求する（準備中）</button>
          @endif
        @endif
      </form>

      <div class="d-flex align-items-center mt-4 mb-4">
        <button id="likeButton" data-venue-id="{{ $venue->id }}" class="btn btn-primary me-2">いいね！</button>
        <span id="likesCount" class="h4 fw-bold mb-0">{{ $venue->likes_count }}</span> <span class="text-muted ms-1">件のいいね！</span>
      </div>

      <h3 class="h6 mt-4 mb-2">写真付き口コミを投稿する</h3>
      <form action="{{ route('venues.reviews.store', $venue) }}" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded shadow-sm">
        @csrf
        <div style="position:absolute; left:-9999px;" aria-hidden="true">
          <label>ウェブサイト<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <div class="mb-2">
          <label class="form-label small">ニックネーム（任意）</label>
          <input type="text" name="nickname" class="form-control form-control-sm" maxlength="30">
        </div>
        <div class="mb-2">
          <label class="form-label small">評価</label>
          <select name="rating" class="form-select form-select-sm" required>
            <option value="">選択してください</option>
            <option value="5">★★★★★</option>
            <option value="4">★★★★☆</option>
            <option value="3">★★★☆☆</option>
            <option value="2">★★☆☆☆</option>
            <option value="1">★☆☆☆☆</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small">口コミ</label>
          <textarea name="comment" class="form-control form-control-sm" rows="3" minlength="5" maxlength="1000" required></textarea>
        </div>
        <div class="mb-2">
          <label class="form-label small">会場・仕上がり写真（任意）</label>
          <input type="file" name="photo" accept="image/*" class="form-control form-control-sm">
        </div>
        <button type="submit" class="btn btn-dark">投稿する</button>
      </form>

      <h3 class="h6 mt-5 mb-3">口コミ</h3>
      <div id="reviewList">
        @forelse($venue->reviews as $review)
          <div class="card mb-3 bg-light">
            @if($review->photo_path)
              <img src="{{ \Illuminate\Support\Facades\Storage::url($review->photo_path) }}" class="card-img-top" style="max-height:320px;object-fit:cover;" alt="{{ $venue->name }}の口コミ写真">
            @endif
            <div class="card-body">
              <div>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }} <strong>{{ $review->nickname }}</strong></div>
              <p class="mb-1">{{ $review->comment }}</p>
              <small class="text-muted">投稿日: {{ $review->created_at->format('Y/m/d H:i') }}</small>
            </div>
          </div>
        @empty
          <p class="text-muted">まだ口コミはありません。</p>
        @endforelse
      </div>
    </div>
  </div>

  <p class="text-muted small mt-4">
    @if($venue->is_from_osm)
      この会場の名称・位置は <a href="https://www.openstreetmap.org/{{ $venue->source_ref }}" target="_blank" rel="noopener">OpenStreetMap</a> のデータをもとにしています（© OpenStreetMap contributors、ODbL 1.0）。
    @else
      この会場は利用者の投稿です。内容は投稿時点のもので、当サイトでは確認していません。
    @endif
    見学会の混雑状況と口コミは利用者の投稿です。挙式の可否や料金は、必ず会場へ直接ご確認ください。
  </p>
</div>
@endsection

@section('scripts')
<script>
  function getCongestionText(avg) {
    if (avg === null || isNaN(avg)) return '報告なし';
    if (avg >= 2.5) return '混雑';
    if (avg >= 1.5) return 'やや混雑';
    return '空いている';
  }

  document.addEventListener('DOMContentLoaded', function() {
    const congestionButtonsDiv = document.getElementById('congestionButtons');
    const congestionMessage = document.getElementById('congestionMessage');
    const currentAverageCongestionSpan = document.getElementById('currentAverageCongestion');

    if (congestionButtonsDiv) {
      const venueId = congestionButtonsDiv.dataset.venueId;
      congestionButtonsDiv.addEventListener('click', async function(event) {
        if (event.target.tagName === 'BUTTON') {
          const level = event.target.dataset.level;
          try {
            const response = await fetch(`/venues/${venueId}/congestion`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
              },
              body: JSON.stringify({ level: level })
            });
            if (!response.ok) {
              const errorData = await response.json();
              throw new Error(errorData.error || '報告に失敗しました。');
            }
            const data = await response.json();
            currentAverageCongestionSpan.textContent = getCongestionText(data.average_congestion);
            congestionMessage.textContent = '混雑状況を報告しました！';
            setTimeout(() => congestionMessage.textContent = '', 3000);
          } catch (error) {
            congestionMessage.textContent = 'エラー: ' + error.message;
            congestionMessage.classList.add('text-danger');
          }
        }
      });
    }

    const likeButton = document.getElementById('likeButton');
    const likesCountSpan = document.getElementById('likesCount');
    if (likeButton) {
      likeButton.addEventListener('click', async function() {
        const venueId = likeButton.dataset.venueId;
        try {
          const response = await fetch(`/venues/${venueId}/like`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
          });
          if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'いいね！に失敗しました。');
          }
          const data = await response.json();
          likesCountSpan.textContent = data.likes_count;
        } catch (error) {
          alert('エラー: ' + error.message);
        }
      });
    }
  });
</script>
@endsection
