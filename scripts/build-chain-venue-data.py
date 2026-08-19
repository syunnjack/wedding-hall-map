"""結婚式場の運営会社の公式サイトから、会場の一覧を取り出す。

雑誌やポータル（ゼクシィなど）の掲載データは各社の権利物なので使わない。
どの会社が式場を運営しているかを手がかりに、**各社の公式サイト**に載っている
会場名・住所・電話番号だけを取る。座標は国土地理院の住所検索APIで求める。

対応している会社:
  - ベストブライダル       https://www.bestbridal.co.jp/facilities/
  - アニヴェルセル         https://www.anniversaire.co.jp/halls/

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
    venues = parse_bestbridal() + parse_anniversaire()
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
