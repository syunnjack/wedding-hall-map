<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Support\ContentModeration;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Venue $venue)
    {
        if (! empty($request->input('website'))) {
            return back();
        }

        $validated = $request->validate([
            'nickname' => 'nullable|string|max:30',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string|min:5|max:1000',
            'photo' => 'nullable|image|max:5120',
        ]);

        if (ContentModeration::containsNgWord($validated['comment'])) {
            return back()->withErrors(['comment' => '投稿内容に使用できない文字列が含まれています。'])->withInput();
        }

        $ipHash = ContentModeration::clientIpHash($request);
        if (ContentModeration::isTooSoon("review:{$venue->id}:{$ipHash}", 30)) {
            return back()->withErrors(['comment' => '投稿間隔が短すぎます。しばらく待ってから再度お試しください。'])->withInput();
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('review-photos', 'public');
        }

        $venue->reviews()->create([
            'nickname' => ($validated['nickname'] ?? '') !== '' ? $validated['nickname'] : '匿名',
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'photo_path' => $photoPath,
            'ip_hash' => $ipHash,
        ]);

        return back()->with('success', '口コミを投稿しました。');
    }
}
