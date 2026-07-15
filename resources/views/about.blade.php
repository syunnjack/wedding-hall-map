@extends('layouts.plain')

@section('title', 'このサイトについて | ' . config('app.name'))
@section('description', config('app.name') . 'の運営方針、データの取り扱い、口コミ・LINE通知・資料請求の仕組みについて説明しています。')

@section('content')
<div class="container my-4" style="max-width: 720px;">
  <h1 class="h4 fw-bold mb-4">このサイトについて</h1>

  <section class="mb-4">
    <h2 class="h6">サイトの目的</h2>
    <p class="text-muted small">
      「{{ config('app.name') }}」は、結婚式場の場所を地図から探せる投稿型マップです。新しい式場は誰でもログイン不要・匿名で投稿でき、
      実際に見学会に行った方が混雑状況の報告や写真付き口コミを投稿することで情報が更新されていきます。
      大手ポータルでは分からない「今の見学会の混雑具合」が分かることが特徴です。
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h6">見学会の混雑状況について</h2>
    <p class="text-muted small">
      混雑状況は、実際に見学会に参加した利用者が「空いている」「やや混雑」「混雑・満員」のいずれかを匿名で報告した値を平均して表示しています。
      リアルタイムの正確な状況を保証するものではなく、あくまで参考情報としてご利用ください。
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h6">LINE通知について</h2>
    <p class="text-muted small">
      各結婚式場のページから「🔔 見学会の混雑状況が変わったらLINEで通知」を選ぶと、LINEログインのうえその式場を通知対象として登録できます。
      登録した式場の混雑状況（空いている/やや混雑/混雑・満員）が変化すると、LINE公式アカウントからお知らせします。
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h6">資料請求について</h2>
    <p class="text-muted small">
      各結婚式場のページから「📮 LINEで資料を請求する」を選ぶと、LINEログインのうえ資料請求を受け付けます。
      受付完了はLINE公式アカウントからお知らせしますが、当サイトは資料の発送そのものは行っておりません。
      お急ぎの場合は、掲載している電話番号へ直接お問い合わせいただくか、各式場の公式サイトもあわせてご確認ください。
    </p>
  </section>

  <section class="mb-4">
    <h2 class="h6">口コミ・投稿について</h2>
    <p class="text-muted small">
      口コミ（写真を含む）や新規式場の投稿は、どなたでもログイン不要で行えます。投稿内容は運営による事前確認を行わず即時反映されますが、
      不適切な投稿を発見した場合は内容を精査のうえ削除などの対応を行います。
    </p>
  </section>

  <a href="{{ route('venues.index') }}" class="d-block text-center text-muted mt-4">トップページに戻る</a>
</div>
@endsection
