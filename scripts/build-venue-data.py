"""OpenStreetMap から、結婚式場としてはっきり分かる施設を取り出す。

出典: OpenStreetMap contributors（ODbL 1.0）。表示側に出典の明記が必要。
      https://www.openstreetmap.org/copyright

取り方:
  1. Overpass API で、名前に婚礼系の語を含む施設と、イベント会場（events_venue）を取る
  2. 交差点名やバス停など、施設ではないものを除く
  3. 都道府県・市区町村は、国土地理院の逆ジオコーダで座標から求める
     （OSMの住所タグは記入率が低いため）

書いていないことは補わない。電話番号やWebサイトは、OSMにある場合だけ入れる。

使い方: python scripts/build-venue-data.py
  → database/data/venues-osm.json を書き出す
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
OUTPUT = ROOT / 'database' / 'data' / 'venues-osm.json'

OVERPASS = 'https://overpass-api.de/api/interpreter'
GSI_REVERSE = 'https://mreversegeocoder.gsi.go.jp/reverse-geocoder/LonLatToAddress?lat={}&lon={}'
GSI_MUNI = 'https://maps.gsi.go.jp/js/muni.js'
UA = 'wedding-hall-map-data/1.0 (+https://kekkonshikijo-map.jp)'
GEOCODE_DELAY = 1.0

QUERY = """
[out:json][timeout:600];
area["ISO3166-1"="JP"][admin_level=2]->.jp;
(
  nwr["name"~"ウエディング|ウェディング|ブライダル|結婚式場|マリアージュ|迎賓館"](area.jp);
  nwr["amenity"="events_venue"]["name"](area.jp);
);
out tags center;
"""

# 婚礼施設だとはっきり分かる名前
WEDDING_WORDS = re.compile('ウエディング|ウェディング|ブライダル|結婚式場|マリアージュ|迎賓館')
# イベント会場のうち、婚礼に使われることが名前から分かるもの
VENUE_WORDS = re.compile('チャペル|ゲストハウス')
# 施設ではないもの（交差点名、バス停、駅名など）
NOT_A_VENUE = ('highway', 'railway', 'public_transport', 'aeroway', 'barrier', 'junction')
# 婚礼施設ではない業種（貸衣装店、美容室、葬儀社、学校など）
NOT_A_HALL_AMENITY = {
    'school', 'college', 'university', 'kindergarten', 'community_centre',
    'brothel', 'hairdresser', 'cafe', 'fast_food', 'bar', 'pub', 'place_of_worship',
}
SHOP_LIKE = ('shop', 'office', 'craft', 'healthcare', 'historic')
# 式場ではないもの（案内板、史跡、衣裳店、写真スタジオ、国の迎賓施設）
DENY_NAME = re.compile(
    '案内図|案内板|跡地|記念碑|入口$|跡$|サロン|美容室|衣裳|衣装|コスチューム|ドレス'
    '|フォトスタジオ|写真館|[A-Z]?棟$|カレッジ|専門学校|[東西南北]門|参観')
# それだけでは施設を特定できない名前と、国の迎賓施設
DENY_EXACT = {'迎賓館', '京都迎賓館', '赤坂迎賓館', '迎賓館赤坂離宮', 'マリアージュ', 'ウェディング', 'ウエディング'}
# 住居や事務所の建物
DENY_BUILDING = {'apartments', 'residential', 'house', 'dormitory', 'office'}


def get(url: str, data: bytes | None = None, timeout: int = 60) -> str:
    request = urllib.request.Request(url, data=data, headers={'User-Agent': UA})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return response.read().decode('utf-8', 'replace')


def fetch_elements() -> list[dict]:
    cache = CACHE / 'overpass.json'
    if cache.exists():
        return json.loads(cache.read_text(encoding='utf-8'))

    body = urllib.parse.urlencode({'data': QUERY}).encode()
    payload = json.loads(get(OVERPASS, body, timeout=640))
    elements = payload['elements']

    CACHE.mkdir(exist_ok=True)
    cache.write_text(json.dumps(elements, ensure_ascii=False), encoding='utf-8')
    return elements


def is_wedding_venue(element: dict) -> bool:
    tags = element.get('tags', {})
    name = tags.get('name', '')

    if not name:
        return False
    if any(key in tags for key in NOT_A_VENUE):
        return False
    if name in DENY_EXACT or DENY_NAME.search(name):
        return False
    if tags.get('tourism') == 'information':
        return False
    if tags.get('building') in DENY_BUILDING:
        return False

    amenity = tags.get('amenity')

    # イベント会場として登録されているものは、名前が婚礼系なら採用する
    if amenity == 'events_venue':
        return bool(WEDDING_WORDS.search(name) or VENUE_WORDS.search(name))

    # 貸衣装店・美容室・葬儀社・事務所など、式を挙げる場所ではないものを除く
    if any(key in tags for key in SHOP_LIKE):
        return False
    if amenity in NOT_A_HALL_AMENITY:
        return False

    # 飲食店は「レストランウェディング」と分かる名前のときだけ
    if amenity == 'restaurant':
        return bool(WEDDING_WORDS.search(name))

    return bool(WEDDING_WORDS.search(name))


def municipalities() -> dict[str, tuple[str, str]]:
    """国土地理院の市区町村コード表（コード → 都道府県名, 市区町村名）。"""
    cache = CACHE / 'muni.json'
    if cache.exists():
        return {k: tuple(v) for k, v in json.loads(cache.read_text(encoding='utf-8')).items()}

    text = get(GSI_MUNI)
    table = {}
    for code, value in re.findall(r'GSI\.MUNI_ARRAY\["(\d+)"\]\s*=\s*\'([^\']+)\'', text):
        parts = value.split(',')
        if len(parts) >= 4:
            table[str(int(code))] = (parts[1], parts[3])

    CACHE.mkdir(exist_ok=True)
    cache.write_text(json.dumps(table, ensure_ascii=False), encoding='utf-8')
    return table


def main() -> None:
    CACHE.mkdir(exist_ok=True)
    elements = [element for element in fetch_elements() if is_wedding_venue(element)]
    print(f'OSMから{len(elements)}件を選びました', flush=True)

    muni = municipalities()
    cache_path = CACHE / 'reverse.json'
    reverse = json.loads(cache_path.read_text(encoding='utf-8')) if cache_path.exists() else {}

    records = []
    for index, element in enumerate(elements, 1):
        center = element.get('center') or element
        lat, lng = center.get('lat'), center.get('lon')
        if lat is None or lng is None:
            continue

        key = f'{lat:.6f},{lng:.6f}'
        if key not in reverse:
            try:
                found = json.loads(get(GSI_REVERSE.format(lat, lng), timeout=30))
                reverse[key] = found.get('results', {}).get('muniCd')
            except Exception as error:
                print(f'  逆ジオコード失敗 {key} {error}', flush=True)
                reverse[key] = None
            time.sleep(GEOCODE_DELAY)
            if index % 25 == 0:
                cache_path.write_text(json.dumps(reverse, ensure_ascii=False), encoding='utf-8')
                print(f'  {index}/{len(elements)}', flush=True)

        code = reverse.get(key)
        prefecture, city = muni.get(str(int(code)), (None, None)) if code else (None, None)
        city = city.replace('　', '') if city else None  # 「京都市　北区」→「京都市北区」

        tags = element['tags']
        address = tags.get('addr:full') or ''.join(filter(None, [
            tags.get('addr:province'), tags.get('addr:city'), tags.get('addr:suburb'),
            tags.get('addr:neighbourhood'), tags.get('addr:block_number'), tags.get('addr:housenumber'),
        ]))

        records.append({
            'name': tags['name'],
            'area': prefecture,
            'city': city,
            'address': address or None,
            'phone': tags.get('phone') or tags.get('contact:phone'),
            'website': tags.get('website') or tags.get('contact:website'),
            'lat': round(float(lat), 7),
            'lng': round(float(lng), 7),
            'sourceRef': f"{element['type']}/{element['id']}",
        })

    cache_path.write_text(json.dumps(reverse, ensure_ascii=False), encoding='utf-8')

    # 同じ施設が点と建物の両方で登録されていることがある。名前とおおよその位置で1件にまとめる。
    unique = {}
    for record in records:
        if not record['area']:
            continue
        key = (record['name'], round(record['lat'], 3), round(record['lng'], 3))
        unique.setdefault(key, record)
    records = list(unique.values())
    records.sort(key=lambda record: (record['area'], record['name']))

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps({
        'confirmedOn': date.today().isoformat(),
        'sourceLabel': 'OpenStreetMap contributors（ODbL 1.0）',
        'sourceUrl': 'https://www.openstreetmap.org/copyright',
        'venues': records,
    }, ensure_ascii=False), encoding='utf-8')

    print(f'{len(records)}件を書き出しました')


main()
