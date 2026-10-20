<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\User\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /** @return Collection<int, User> */
    public function getSyncRetryCandidates(User $excludedUser, int $legalEntityId): Collection
    {
        return User::with('permissions')
            ->whereKeyNot($excludedUser->id)
            ->when($excludedUser->partyId !== null, fn (Builder $query) => $query->whereNot('party_id', $excludedUser->partyId))
            ->whereHas('employees', fn (Builder $query) => $query->whereLegalEntityId($legalEntityId))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('sessions')
                ->whereColumn('sessions.user_id', 'users.id')
                ->whereColumn('sessions.id', 'users.session_id')
                ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp))
            ->orderBy('users.id')
            ->get();
    }

    /** Read only the session currently bound in the database, even for a serialized User. */
    public function getSyncSessionPayload(User $user): array
    {
        $payload = DB::table('sessions')
            ->join('users', 'users.session_id', '=', 'sessions.id')
            ->where('users.id', $user->id)
            ->where('sessions.user_id', $user->id)
            ->where('sessions.last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
            ->value('sessions.payload');

        if (!is_string($payload)) {
            return [];
        }

        $decoded = base64_decode($payload, true);
        $attributes = $decoded === false ? false : @unserialize($decoded, ['allowed_classes' => false]);

        return is_array($attributes) ? $attributes : [];
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
