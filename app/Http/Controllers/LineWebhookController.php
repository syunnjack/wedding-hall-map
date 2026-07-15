<?php

namespace App\Http\Controllers;

use App\Support\LineMessaging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('X-Line-Signature');

        if (! LineMessaging::verifyWebhookSignature($request->getContent(), $signature)) {
            return response('invalid signature', 400);
        }

        foreach ($request->input('events', []) as $event) {
            Log::info('LINE webhook event', [
                'type' => $event['type'] ?? null,
                'line_user_id' => $event['source']['userId'] ?? null,
            ]);
        }

        return response('ok', 200);
    }
}
