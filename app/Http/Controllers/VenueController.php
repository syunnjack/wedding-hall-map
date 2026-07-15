<?php

namespace App\Http\Controllers;

use App\Helpers\CongestionHelper;
use App\Models\Venue;
use App\Support\ContentModeration;
use App\Support\LineMessaging;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $query = Venue::query();

        if ($request->filled('area')) {
            $query->where('area', $request->input('area'));
        }

        $venues = $query->latest()->get();
        $areas = Venue::query()->whereNotNull('area')->distinct()->pluck('area');

        return view('venues.index', compact('venues', 'areas'));
    }

    public function create()
    {
        return view('venues.create');
    }

    public function store(Request $request)
    {
        if (! empty($request->input('website'))) {
            return redirect()->route('venues.thanks');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'area' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if (ContentModeration::containsNgWord($validated['name'] . ' ' . ($validated['description'] ?? ''))) {
            return back()->withErrors(['name' => '投稿内容に使用できない文字列が含まれています。'])->withInput();
        }

        $ipHash = ContentModeration::clientIpHash($request);
        if (ContentModeration::isTooSoon("venue-create:{$ipHash}", 30)) {
            return back()->withErrors(['name' => '投稿間隔が短すぎます。しばらく待ってから再度お試しください。'])->withInput();
        }

        Venue::create($validated);

        return redirect()->route('venues.thanks');
    }

    public function show(Venue $venue)
    {
        $venue->load(['reviews' => fn ($q) => $q->latest()]);

        $isFavorited = session('line_user_local_id')
            ? $venue->favorites()->where('line_user_id', session('line_user_local_id'))->exists()
            : false;

        $hasRequestedDocument = session('line_user_local_id')
            ? $venue->documentRequests()->where('line_user_id', session('line_user_local_id'))->exists()
            : false;

        return view('venues.show', compact('venue', 'isFavorited', 'hasRequestedDocument'));
    }

    public function reportCongestion(Request $request, Venue $venue)
    {
        $ipHash = ContentModeration::clientIpHash($request);
        if (ContentModeration::isTooSoon("congestion:{$venue->id}:{$ipHash}", 60)) {
            return response()->json(['error' => '報告間隔が短すぎます。しばらく待ってから再度お試しください。'], 429);
        }

        $validated = $request->validate([
            'level' => 'required|in:empty,slightly_crowded,crowded,very_crowded',
        ]);

        $levelMap = ['empty' => 1, 'slightly_crowded' => 2, 'crowded' => 3, 'very_crowded' => 4];
        $numericLevel = $levelMap[$validated['level']];

        $previousBucket = CongestionHelper::getText($venue->average_congestion);

        $reports = $venue->congestion_reports ?? [];
        $reports[] = $numericLevel;
        $average = array_sum($reports) / count($reports);

        $venue->congestion_reports = $reports;
        $venue->average_congestion = round($average, 2);
        $venue->save();

        $newBucket = CongestionHelper::getText($venue->average_congestion);
        if ($newBucket !== $previousBucket) {
            $this->notifyFavoritesOfCongestionChange($venue, $newBucket);
        }

        return response()->json(['average_congestion' => $venue->average_congestion]);
    }

    private function notifyFavoritesOfCongestionChange(Venue $venue, string $newBucket): void
    {
        $venue->loadMissing('favorites.lineUser');

        foreach ($venue->favorites as $favorite) {
            if (! $favorite->lineUser) {
                continue;
            }

            LineMessaging::push(
                $favorite->lineUser->line_user_id,
                "「{$venue->name}」の見学会混雑状況が「{$newBucket}」に変わりました。"
            );
        }
    }

    public function like(Request $request, Venue $venue)
    {
        $ipHash = ContentModeration::clientIpHash($request);
        if (ContentModeration::isTooSoon("like:{$venue->id}:{$ipHash}", 60)) {
            return response()->json(['error' => 'いいね！は少し時間を空けてから再度お試しください。'], 429);
        }

        $venue->increment('likes_count');
        $venue->refresh();

        return response()->json(['likes_count' => $venue->likes_count]);
    }

    public function sitemap()
    {
        $venues = Venue::select('id', 'updated_at')->get();
        $xml = view('sitemap', compact('venues'))->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
