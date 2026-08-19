<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class OsmVenueSeeder extends Seeder
{
    /**
     * OpenStreetMap から取り出した結婚式場を取り込む。
     *
     * データは scripts/build-venue-data.py が database/data/venues-osm.json に書き出す。
     * 出典は OpenStreetMap contributors（ODbL 1.0）で、表示側に明記する必要がある。
     *
     * 元データに無い項目（電話番号、説明文など）は補わずに空のままにする。
     * 利用者が投稿した会場（source が null）には触れない。
     */
    private const CHUNK = 40;

    public function run(): void
    {
        $path = database_path('data/venues-osm.json');

        if (! File::exists($path)) {
            throw new RuntimeException('database/data/venues-osm.json が見つかりません。');
        }

        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $venues = $payload['venues'] ?? [];

        if ($venues === []) {
            throw new RuntimeException('会場データが空です。');
        }

        $now = now();
        $written = 0;

        foreach (array_chunk($venues, self::CHUNK) as $chunk) {
            $rows = [];

            foreach ($chunk as $venue) {
                $rows[] = [
                    'name' => $venue['name'],
                    'area' => $venue['area'],
                    'city' => $venue['city'],
                    'address' => $venue['address'],
                    'phone' => $venue['phone'],
                    'website' => $venue['website'],
                    'lat' => $venue['lat'],
                    'lng' => $venue['lng'],
                    'source' => 'openstreetmap',
                    'source_ref' => $venue['sourceRef'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('venues')->upsert(
                $rows,
                ['source', 'source_ref'],
                ['name', 'area', 'city', 'address', 'phone', 'website', 'lat', 'lng', 'updated_at']
            );

            $written += count($rows);
        }

        $this->command?->info(number_format($written).'件を取り込みました（掲載中 '
            .number_format(Venue::count()).'件）。');
    }
}
