<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessaging
{
    public static function authorizeUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.line.login_channel_id'),
            'redirect_uri' => config('services.line.login_redirect_uri'),
            'state' => $state,
            'scope' => 'profile openid',
            'bot_prompt' => 'aggressive',
        ];

        return 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params);
    }

    /**
     * @return array{id_token: string}
     */
    public static function exchangeToken(string $code): array
    {
        $response = Http::asForm()->post('https://api.line.me/oauth2/v2.1/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.line.login_redirect_uri'),
            'client_id' => config('services.line.login_channel_id'),
            'client_secret' => config('services.line.login_channel_secret'),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('LINEトークン交換に失敗しました: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * @return array{sub: string, name?: string}
     */
    public static function verifyIdToken(string $idToken): array
    {
        $response = Http::asForm()->post('https://api.line.me/oauth2/v2.1/verify', [
            'id_token' => $idToken,
            'client_id' => config('services.line.login_channel_id'),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('LINE id_tokenの検証に失敗しました: ' . $response->body());
        }

        return $response->json();
    }

    public static function push(string $lineUserId, string $text): void
    {
        try {
            Http::withToken(config('services.line.messaging_channel_access_token'))
                ->timeout(5)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $lineUserId,
                    'messages' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('LINEプッシュ通知の送信に失敗しました', ['line_user_id' => $lineUserId, 'error' => $e->getMessage()]);
        }
    }

    public static function verifyWebhookSignature(string $body, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            $body,
            (string) config('services.line.messaging_channel_secret'),
            true
        ));

        return hash_equals($expected, $signature);
    }
}
