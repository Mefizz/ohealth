<?php

declare(strict_types=1);

namespace App\Auth\EHealth\Services;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SyncTokenContext
{
    public const string GRANTED_SCOPES_KEY = 'ehealthGrantedTokenScopes';

    /**
     * Read credentials together from one live session, never from role permissions.
     *
     * @param  list<string>  $excludedTokenHashes
     * @return array{token: string, scopes: list<string>}|null
     */
    public function find(User $user, LegalEntity $legalEntity, string $requiredScope = '', ?string $token = null, array $excludedTokenHashes = []): ?array
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
            ->orderByDesc('last_activity')
            ->get(['payload']);

        foreach ($sessions as $session) {
            try {
                $decoded = base64_decode($session->payload, true);
                $payload = $decoded === false ? null : @unserialize($decoded, ['allowed_classes' => false]);
            } catch (Throwable) {
                continue;
            }

            if (!is_array($payload)) {
                continue;
            }

            $sessionToken = $payload[config('ehealth.api.oauth.bearer_token')] ?? null;
            $scopes = $payload[self::GRANTED_SCOPES_KEY] ?? null;

            if (!is_string($sessionToken) || $sessionToken === '' || !is_array($scopes)
                || ($payload['ehealth_legal_entity_uuid'] ?? null) !== $legalEntity->uuid
                || (int) ($payload['auth_token_expires_at'] ?? 0) <= now()->timestamp
                || ($token !== null && !hash_equals($token, $sessionToken))
                || in_array(hash('sha256', $sessionToken), $excludedTokenHashes, true)
                || ($requiredScope !== '' && !in_array($requiredScope, $scopes, true))
            ) {
                continue;
            }

            return [
                'token' => $sessionToken,
                'scopes' => array_values(array_filter($scopes, static fn ($scope) => is_string($scope) && $scope !== '')),
            ];
        }

        return null;
    }
}
