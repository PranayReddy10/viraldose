<?php

namespace App\Services\Google;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Minimal Google service-account client (JWT bearer flow, RS256) so we need no
 * SDK. The JSON key uploaded in Settings → Google is stored on the private disk.
 */
class GoogleClient
{
    public const KEY_PATH = 'google/service-account.json';

    public const SCOPE_INDEXING = 'https://www.googleapis.com/auth/indexing';

    public const SCOPE_WEBMASTERS = 'https://www.googleapis.com/auth/webmasters';

    public const SCOPE_ANALYTICS = 'https://www.googleapis.com/auth/analytics.readonly';

    /** @var array<string, mixed>|null */
    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    public function clientEmail(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }
        $disk = Storage::disk('local');
        if (! $disk->exists(self::KEY_PATH)) {
            return null;
        }
        $data = json_decode((string) $disk->get(self::KEY_PATH), true);
        if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            return null;
        }

        return $this->credentials = $data;
    }

    public static function storeKey(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ($data['type'] ?? '') !== 'service_account' || empty($data['client_email']) || empty($data['private_key'])) {
            throw new RuntimeException('This is not a Google service account JSON key.');
        }
        Storage::disk('local')->put(self::KEY_PATH, $json);
        Cache::forget('google.token.*');

        return $data;
    }

    public static function removeKey(): void
    {
        Storage::disk('local')->delete(self::KEY_PATH);
    }

    public function accessToken(string ...$scopes): string
    {
        $creds = $this->credentials();
        if (! $creds) {
            throw new RuntimeException('Google service account is not configured.');
        }
        sort($scopes);
        $cacheKey = 'google.token.'.md5(implode(' ', $scopes).$creds['client_email']);

        return Cache::remember($cacheKey, 3300, function () use ($creds, $scopes) {
            $now = time();
            $jwt = $this->jwt([
                'iss' => $creds['client_email'],
                'scope' => implode(' ', $scopes),
                'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $creds['private_key']);

            $response = Http::asForm()->timeout(20)->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Google token request failed: '.($response->json('error_description') ?? $response->body()));
            }

            return (string) $response->json('access_token');
        });
    }

    public function http(string ...$scopes): PendingRequest
    {
        return Http::withToken($this->accessToken(...$scopes))->acceptJson()->timeout(30);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function jwt(array $claims, string $privateKey): string
    {
        $segments = [
            $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->b64(json_encode($claims)),
        ];
        $signature = '';
        $key = openssl_pkey_get_private($privateKey);
        if (! $key || ! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Google JWT. Is the private key valid?');
        }
        $segments[] = $this->b64($signature);

        return implode('.', $segments);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
