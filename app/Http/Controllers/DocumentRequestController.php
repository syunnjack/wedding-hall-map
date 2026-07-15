<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\Venue;
use App\Support\LineMessaging;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    public function store(Request $request, Venue $venue)
    {
        $lineUserLocalId = $request->session()->get('line_user_local_id');

        if (! $lineUserLocalId) {
            return redirect()->route('line.login', ['document_venue' => $venue->id]);
        }

        $documentRequest = DocumentRequest::where('line_user_id', $lineUserLocalId)
            ->where('venue_id', $venue->id)
            ->first();

        if ($documentRequest) {
            return back()->with('success', '「' . $venue->name . '」の資料はすでに請求済みです。');
        }

        $documentRequest = DocumentRequest::create([
            'line_user_id' => $lineUserLocalId,
            'venue_id' => $venue->id,
        ]);

        $this->sendConfirmation($documentRequest);

        return back()->with('success', '「' . $venue->name . '」の資料請求を受け付けました。LINEで受付完了のお知らせをお送りします。');
    }

    public function sendConfirmation(DocumentRequest $documentRequest): void
    {
        $documentRequest->loadMissing('lineUser', 'venue');

        if (! $documentRequest->lineUser) {
            return;
        }

        $venue = $documentRequest->venue;
        $text = "「{$venue->name}」の資料請求を受け付けました。";
        if ($venue->phone) {
            $text .= " お急ぎの場合は直接お電話（{$venue->phone}）でのお問い合わせもご利用いただけます。";
        }

        LineMessaging::push($documentRequest->lineUser->line_user_id, $text);
    }
}
