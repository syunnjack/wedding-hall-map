"""結婚式場の運営会社の公式サイトから、会場の一覧を取り出す。

雑誌やポータル（ゼクシィなど）の掲載データは各社の権利物なので使わない。
どの会社が式場を運営しているかを手がかりに、**各社の公式サイト**に載っている
会場名・住所・電話番号だけを取る。座標は国土地理院の住所検索APIで求める。

対応している会社:
  - ベストブライダル       https://www.bestbridal.co.jp/facilities/
  - アニヴェルセル         https://www.anniversaire.co.jp/halls/
  - アイ・ケイ・ケイ       https://lalachance.ikk-wed.jp/ （ララシャンス／迎賓館など各会場の公式サイト）
  - オンザページ（旧ノバレーゼ・エスクリ）
                           https://produce.novarese.jp/novarese-bridalsalon/venue/

使い方: python scripts/build-chain-venue-data.py
  → database/data/venues-chain.json を書き出す
"""
import json
import re
import time
import urllib.parse
import urllib.request
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CACHE = ROOT / 'scripts' / '.cache'
OUTPUT = ROOT / 'database' / 'data' / 'venues-chain.json'

UA = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
      '(KHTML, like Gecko) Chrome/131.0 Safari/537.36')
GEOCODER = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q={}'
DELAY = 1.5
GEOCODE_DELAY = 1.0

PREFECTURES = (
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県', '茨城県', '栃木県',
    '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県', '新潟県', '富山県', '石川県', '福井県',
    '山梨県', '長野県', '岐阜県', '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府',
    '兵庫県', '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県',
    '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県',
    '鹿児島県', '沖縄県',
)


def get(url: str, timeout: int = 40) -> str:
    request = urllib.request.Request(url, headers={'User-Agent': UA, 'Accept-Language': 'ja'})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return response.read().decode('utf-8', 'replace')


def cached(name: str, url: str) -> str:
    CACHE.mkdir(exist_ok=True)
    path = CACHE / f'{name}.html'
    if not path.exists():
        path.write_text(get(url), encoding='utf-8')
        time.sleep(DELAY)
    return path.read_text(encoding='utf-8')


def strip_tags(html: str) -> str:
    text = re.sub(r'<[^>]+>', ' ', html)
    text = text.replace('&#12306;', '〒').replace('&nbsp;', ' ').replace('&amp;', '&')
    return re.sub(r'\s+', ' ', text).strip()


def split_address(raw: str) -> tuple[str | None, str | None, str]:
    """「〒103-0006 東京都中央区日本橋富沢町12-13」を郵便番号・都道府県・住所に分ける。"""
    postal = None
    match = re.search(r'〒?\s*(\d{3}-\d{4})', raw)
    if match:
        postal = match.group(1)
        raw = raw.replace(match.group(0), '')
    address = raw.strip()
    prefecture = next((p for p in PREFECTURES if address.startswith(p)), None)
    return postal, prefecture, address


def parse_bestbridal() -> list[dict]:
    """ベストブライダル。ウエディング（idが w_ で始まる区画）の会場だけを取る。"""
    html = cached('bestbridal', 'https://www.bestbridal.co.jp/facilities/')
    venues = []

    for block in re.findall(r'<div id="w_[a-z]+" class="p-facilities_area">(.*?)(?=<div id="|\Z)', html, re.S):
        for card in re.findall(r'<li class="p-facilities_card">(.*?)</li>', block, re.S):
            name = re.search(r'p-facilities_card_ttl[^>]*>(.*?)</p>', card, re.S)
            detail = re.search(r'p-facilities_card_detail_desc[^>]*>(.*?)</dd>', card, re.S)
            if not name or not detail:
                continue

            postal, prefecture, address = split_address(strip_tags(detail.group(1)))
            tel = re.search(r'href="tel:([0-9\-]+)"', card)

            venues.append({
                'name': strip_tags(name.group(1)),
                'address': address,
                'postalCode': postal,
                'area': prefecture,
                'phone': tel.group(1) if tel else None,
                'operator': 'ベストブライダル',
                'sourceUrl': 'https://www.bestbridal.co.jp/facilities/',
            })

    return venues


def parse_anniversaire() -> list[dict]:
    """アニヴェルセル。会場ページのJSON-LDに住所と緯度経度が入っている。"""
    index = cached('anniversaire', 'https://www.anniversaire.co.jp/halls/')
    urls = sorted({
        url for url in re.findall(r'href="(https://www\.anniversaire\.co\.jp/wedding/[a-z0-9_-]+/)"', index)
    })

    venues = []
    for url in urls:
        slug = url.rstrip('/').rsplit('/', 1)[-1]
        html = cached(f'anniversaire-{slug}', url)

        for block in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', html, re.S):
            try:
                data = json.loads(block)
            except json.JSONDecodeError:
                continue

            entries = data if isinstance(data, list) else [data]
            for entry in entries:
                types = entry.get('@type')
                types = types if isinstance(types, list) else [types]
                if 'LocalBusiness' not in types:
                    continue

                address = entry.get('address') or {}
                geo = entry.get('geo') or {}
                venues.append({
                    'name': entry.get('name'),
                    'address': ''.join(filter(None, [
                        address.get('addressRegion'), address.get('addressLocality'),
                        address.get('streetAddress'),
                    ])),
                    'postalCode': address.get('postalCode'),
                    'area': address.get('addressRegion'),
                    'phone': (entry.get('telephone') or '').replace('+81-', '') or None,
                    'lat': float(geo['latitude']) if geo.get('latitude') else None,
                    'lng': float(geo['longitude']) if geo.get('longitude') else None,
                    'operator': 'アニヴェルセル',
                    'sourceUrl': url,
                })
                break

    return venues


BRAND_WORDS = ('ララシャンス', 'ラ・シャンス', 'アーククラブ', '迎賓館')
# 名前の前に付く宣伝文を切るための助詞など
STOP_CHARS = set('なのがではをにへとやもら、。「」（）()｜|！？-‐―–—')


def venue_name_from(title: str) -> str | None:
    """会場ページのタイトルから会場名を取り出す。

    「【公式】ララシャンス博多の森 | 福岡市博多区の…」のように公式表記があるものは
    その直後、無いものは宣伝文が前に付くので、ブランド名を含む区切りを選ぶ。
    """
    official = title.startswith('【公式】')
    heading = re.sub(r'^【公式】', '', title).strip()
    segments = [segment.strip() for segment in re.split(r'[|｜]', heading) if segment.strip()]

    if not segments:
        return None

    if official:
        chosen = segments[0]
    else:
        branded = [segment for segment in segments if any(word in segment for word in BRAND_WORDS)]
        chosen = branded[0] if branded else segments[-1]

    # 「…結婚式場ならララシャンスKOBE」のように前に宣伝文が付く場合は、そこを落とす
    positions = [chosen.find(word) for word in BRAND_WORDS if word in chosen]
    if positions:
        start = min(positions)
        while start > 0 and chosen[start - 1] not in STOP_CHARS:
            start -= 1
        chosen = chosen[start:]

    # 「The 迎賓館 偕楽園 別邸 茨城県水戸市の結婚式場・ウエディング」のように、
    # 区切り記号なしで説明が続く場合は、その手前で切る。
    chosen = re.split(r'\s+(?=[^\s]*(?:結婚式|ウエディング|ウェディング))', chosen)[0]
    chosen = chosen.strip(' 　-‐―–—')
    return chosen or None


def parse_ikk() -> list[dict]:
    """アイ・ケイ・ケイ。ブランドサイトから会場サイトをたどり、各サイトの住所を読む。"""
    index = cached('ikk', 'https://lalachance.ikk-wed.jp/')
    urls = sorted({
        url for url in re.findall(
            r'<a[^>]+href="(https?://www\.ikk-wed\.jp/[^"]+)"[^>]*>(?:(?!</a>).)*?会場WEBサイトを見る',
            index, re.S)
    })

    venues = []
    for url in urls:
        slug = re.sub(r'[^a-z0-9]+', '-', url.split('ikk-wed.jp/')[-1]).strip('-') or 'top'
        try:
            html = cached(f'ikk-{slug}', url)
        except Exception as error:
            print(f'  会場サイト取得に失敗 {url} {error}', flush=True)
            continue

        title = re.search(r'<title>(.*?)</title>', html, re.S)
        if not title:
            continue

        # タイトルから会場名を取り出す（宣伝文を施設名にしない）。
        name = venue_name_from(strip_tags(title.group(1)))

        text = strip_tags(re.sub(r'<script.*?</script>', '', html, flags=re.S))
        address_match = re.search(r'(〒\s?\d{3}-?\d{4}\s*[^\s]+)', text)

        # トップに住所が無い会場は、アクセスのページを見る
        if not address_match:
            for suffix in ('access/', 'access.php', 'wedding-access.php'):
                access_url = re.sub(r'[^/]*$', '', url) + suffix
                try:
                    access_html = cached(f'{slug}-{suffix.replace("/", "").replace(".", "-")}', access_url)
                except Exception:
                    continue
                access_text = strip_tags(re.sub(r'<script.*?</script>', '', access_html, flags=re.S))
                address_match = re.search(r'(〒\s?\d{3}-?\d{4}\s*[^\s]+)', access_text)
                if address_match:
                    text = access_text
                    break
        if not name or not address_match:
            continue

        postal, prefecture, address = split_address(address_match.group(1))
        phone = re.search(r'(0120-[\d-]+|0\d{1,4}-\d{1,4}-\d{4})', text)

        venues.append({
            'name': name,
            'address': address,
            'postalCode': postal,
            'area': prefecture,
            'phone': phone.group(1) if phone else None,
            'operator': 'アイ・ケイ・ケイ',
            'sourceUrl': url,
        })

    return venues


NOVARESE_LIST = 'https://produce.novarese.jp/novarese-bridalsalon/venue/'


def find_address(html: str) -> str | None:
    text = strip_tags(re.sub(r'<script.*?</script>', '', html, flags=re.S))
    match = re.search(r'(〒\s?\d{3}-?\d{4}\s*[^\s]+)', text)
    return match.group(1) if match else None


def parse_novarese() -> list[dict]:
    """オンザページ（旧ノバレーゼ・エスクリ）。一覧に会場名と都道府県、各会場サイトへのリンクがある。"""
    index = cached('novarese', NOVARESE_LIST)
    entries = re.findall(
        r'<li><a href="(https?://[^"]+)"[^>]*>.*?<p><span>\[([^\]]+)\]</span>([^<]+)</p>',
        index, re.S)

    venues = []
    for url, prefecture_short, name in entries:
        name = name.strip()
        slug = re.sub(r'[^a-z0-9]+', '-', url.split('//')[-1]).strip('-')[:40]

        address = None
        for candidate in (url, url.rstrip('/') + '/access/'):
            try:
                address = find_address(cached(f'novarese-{slug}', candidate)
                                       if candidate == url else cached(f'novarese-{slug}-access', candidate))
            except Exception:
                address = None
            if address:
                break

        if not address:
            print(f'  住所が見つからない会場: {name}', flush=True)
            continue

        postal, prefecture, plain = split_address(address)
        venues.append({
            'name': name,
            'address': plain,
            'postalCode': postal,
            'area': prefecture,
            'phone': None,
            'operator': 'オンザページ（旧ノバレーゼ・エスクリ）',
            'sourceUrl': url,
        })

    return venues


def geocode(venues: list[dict]) -> None:
    cache_path = CACHE / 'geocode-chain.json'
    cache = json.loads(cache_path.read_text(encoding='utf-8')) if cache_path.exists() else {}

    pending = [venue for venue in venues if venue.get('address')]
    for index, venue in enumerate(pending, 1):
        address = venue['address']
        if address not in cache:
            try:
                found = json.loads(get(GEOCODER.format(urllib.parse.quote(address)), timeout=30))
                cache[address] = found[0]['geometry']['coordinates'] if found else None
            except Exception as error:
                print(f'  住所検索 失敗 {address} {error}', flush=True)
                cache[address] = None
            time.sleep(GEOCODE_DELAY)
            if index % 20 == 0:
                cache_path.write_text(json.dumps(cache, ensure_ascii=False), encoding='utf-8')
                print(f'  住所検索 {index}/{len(pending)}', flush=True)

        coordinates = cache.get(address)
        if coordinates:
            venue['lng'], venue['lat'] = round(coordinates[0], 7), round(coordinates[1], 7)

    CACHE.mkdir(exist_ok=True)
    cache_path.write_text(json.dumps(cache, ensure_ascii=False), encoding='utf-8')


def main() -> None:
    venues = parse_bestbridal() + parse_anniversaire() + parse_ikk() + parse_novarese()
    print(f'公式サイトから{len(venues)}会場', flush=True)

    geocode(venues)

    records = [venue for venue in venues if venue.get('lat') and venue.get('area')]
    records.sort(key=lambda venue: (venue['area'], venue['name']))

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps({
        'confirmedOn': date.today().isoformat(),
        'venues': records,
    }, ensure_ascii=False), encoding='utf-8')

    print(f'{len(records)}会場を書き出しました（座標か都道府県が取れず除外: {len(venues) - len(records)}）')


main()
