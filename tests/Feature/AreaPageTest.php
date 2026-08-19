<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_page_links_to_prefecture_pages(): void
    {
        $this->venue();

        $this->get('/')
            ->assertOk()
            ->assertSee('都道府県から探す')
            ->assertSee('/area/tokyo', false)
            ->assertSee('OpenStreetMap');
    }

    public function test_prefecture_page_lists_its_venues(): void
    {
        $venue = $this->venue();

        $this->get('/area/tokyo')
            ->assertOk()
            ->assertSee($venue->name)
            ->assertSee('東京都の結婚式場');
    }

    public function test_old_area_query_redirects(): void
    {
        $this->venue();

        $this->get('/?area='.urlencode('東京都'))
            ->assertRedirect(route('venues.area', ['areaSlug' => 'tokyo']));
    }

    public function test_prefecture_without_venues_is_not_found(): void
    {
        $this->venue();

        $this->get('/area/okinawa')->assertNotFound();
        $this->get('/area/atlantis')->assertNotFound();
    }

    public function test_sitemap_lists_prefecture_pages(): void
    {
        $this->venue();

        $this->get('/sitemap.xml')->assertOk()->assertSee('/area/tokyo', false);
    }

    private function venue(): Venue
    {
        return Venue::create([
            'name' => 'テスト迎賓館',
            'area' => '東京都',
            'city' => '港区',
            'lat' => 35.6654,
            'lng' => 139.7298,
            'source' => 'openstreetmap',
            'source_ref' => 'node/1',
        ]);
    }
}
