<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\User\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    /** Latest usable session per legal entity; a token must never be reused across entities. */
    public function getSpecialityClearSessions(?int $userId = null): array
    {
        $contexts = [];
        $sessions = DB::table('sessions')->whereNotNull('user_id')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('last_activity')->cursor();
        foreach ($sessions as $session) {
            $payload = @unserialize(base64_decode($session->payload), ['allowed_classes' => false]);
            $legalEntityUuid = is_array($payload) ? ($payload['ehealth_legal_entity_uuid'] ?? null) : null;
            $token = is_array($payload) ? ($payload[config('ehealth.api.oauth.bearer_token')] ?? null) : null;
            $scopes = is_array($payload) ? ($payload[config('ehealth.api.oauth.token_scopes')] ?? null) : null;
            if (!is_string($legalEntityUuid) || isset($contexts[$legalEntityUuid])
                || !is_string($token) || trim($token) === ''
                || !is_numeric($payload['auth_token_expires_at'] ?? null) || $payload['auth_token_expires_at'] <= now()->timestamp
                || !is_array($scopes) || !in_array('employee:details', $scopes, true)) {
                continue;
            }
            $user = User::find($session->user_id);
            if ($user) {
                $contexts[$legalEntityUuid] = ['user' => $user, 'token' => $token];
            }
        }

        return $contexts;
    }

    public function getEHealthTokenFromSession(User $user): string
    {
        $session = DB::table('sessions')->where('user_id', $user->id)->first();
        if (!$session) {
            return '';
        }

        $payload = unserialize(base64_decode($session->payload));

        return $payload['auth_token'] ?? '';
    }

    /**
     * Get list of users that:
     * 1) have permission 'division:write'
     * 2) have an employee in the updated division
     * 3) and this employee belongs to the same legal_entity
     *
     * @param  int  $divisionId
     * @return Collection
     */
    public function getDivisionEditorsByLegalEntity(int $divisionId): Collection
    {
        return User::permission('division:write')
            ->whereHas('employees', static function (Builder $query) use ($divisionId) {
                $query->whereDivisionId($divisionId)
                    ->whereLegalEntityId(legalEntity()->id);
            })
            ->get();
    }

    /**
     * Get a collection of users who have the "OWNER" role and are linked as employees to the current legal entity.
     *
     * @return Collection
     */
    public function getLegalEntityOwners(): Collection
    {
        return User::role(Role::OWNER)
            ->whereHas('employees', static fn (Builder $query) => $query->whereLegalEntityId(legalEntity()->id))
            ->get();
    }
}
