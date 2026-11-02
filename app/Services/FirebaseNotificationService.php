<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseNotificationService
{
    /** @return array{message_id:?string,invalid:bool,error:?string} */
    public function send(DeviceToken $token, array $data, string $title, string $body): array
    {
        $project = config('services.firebase.project_id');
        $email = config('services.firebase.client_email');
        $key = str_replace('\\n', "\n", (string) config('services.firebase.private_key'));
        if (! $project || ! $email || ! $key) {
            throw new RuntimeException('Firebase service account is not configured.');
        }
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->signedAssertion($email, $key),
        ]);
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $error = $response->json('error_description') ?? $response->json('error.message') ?? 'OAuth token request rejected.';

            throw new RuntimeException('Firebase OAuth failed (HTTP '.$response->status().'): '.substr((string) $error, 0, 300));
        }
        $result = Http::withToken($response->json('access_token'))->timeout(15)->post(
            "https://fcm.googleapis.com/v1/projects/{$project}/messages:send",
            ['message' => [
                'fid' => $token->token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'HIGH'],
                'apns' => ['headers' => ['apns-priority' => '10']],
            ]],
        );
        if (! $result->successful()) {
            $errorCode = $result->json('error.details.0.errorCode');
            if ($errorCode === 'UNREGISTERED' || $result->json('error.status') === 'UNREGISTERED') {
                $token->delete();

                return ['message_id' => null, 'invalid' => true, 'error' => 'Firebase rejected this FID as unregistered.'];
            }
            if ($result->status() === 429 || $result->serverError() || $result->status() === 408) {
                $status = $result->json('error.status') ?? 'unknown';
                $message = $result->json('error.message') ?? 'Temporary Firebase error.';

                throw new RuntimeException('Firebase temporarily unavailable (HTTP '.$result->status().', '.$status.'): '.substr((string) $message, 0, 300));
            }

            $status = $result->json('error.status') ?? 'unknown';
            $message = $result->json('error.message') ?? 'Firebase rejected the notification.';

            return ['message_id' => null, 'invalid' => false, 'error' => 'Firebase rejected notification (HTTP '.$result->status().', '.$status.'): '.substr((string) $message, 0, 300)];
        }

        return ['message_id' => $result->json('name'), 'invalid' => false, 'error' => null];
    }

    private function signedAssertion(string $email, string $key): string
    {
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $now = time();
        $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $encode(json_encode(['iss' => $email, 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;
        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Firebase credential signing failed.');
        }

        return $unsigned.'.'.$encode($signature);
    }
}
