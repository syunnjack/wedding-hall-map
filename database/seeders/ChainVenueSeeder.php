<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ChainVenueSeeder extends Seeder
{
    /**
     * 結婚式場の運営会社の公式サイトに載っている会場を取り込む。
     *
     * データは scripts/build-chain-venue-data.py が database/data/venues-chain.json に書き出す。
     * 会場名・住所・電話番号は各社の公式サイトの記載で、座標は国土地理院の住所検索による。
     *
     * OpenStreetMap 側にも同じ会場が入っていることがあるため、
     * 名前が一致する OSM 由来の行は取り込みのあとで消す（公式の記載を優先する）。
     */
    private const CHUNK = 40;

    public function run(): void
    {
        $path = database_path('data/venues-chain.json');

        if (! File::exists($path)) {
            throw new RuntimeException('database/data/venues-chain.json が見つかりません。');
        }

        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $venues = $payload['venues'] ?? [];

        if ($venues === []) {
            throw new RuntimeException('会場データが空です。');
        }

        $now = now();
        $names = [];

        foreach (array_chunk($venues, self::CHUNK) as $chunk) {
            $rows = [];

            foreach ($chunk as $venue) {
                $names[] = $venue['name'];
                $rows[] = [
                    'name' => $venue['name'],
                    'area' => $venue['area'],
                    'address' => $venue['address'],
                    'phone' => $venue['phone'],
                    'lat' => $venue['lat'],
                    'lng' => $venue['lng'],
                    'source' => 'official',
                    'source_ref' => $venue['operator'].'/'.$venue['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('venues')->upsert(
                $rows,
                ['source', 'source_ref'],
                ['name', 'area', 'address', 'phone', 'lat', 'lng', 'updated_at']
            );
        }

        // 同じ会場が OpenStreetMap 側にもある場合は、公式の記載を残す。
        $removed = 0;
        foreach (array_chunk($names, 50) as $chunk) {
            $removed += DB::table('venues')
                ->where('source', 'openstreetmap')
                ->whereIn('name', $chunk)
                ->delete();
        }

        $this->command?->info(number_format(count($venues)).'会場を取り込みました'
            .($removed > 0 ? '（OSM側の重複 '.number_format($removed).'件を整理）' : '')
            .'。掲載中 '.number_format(Venue::count()).'件。');
    }
}
