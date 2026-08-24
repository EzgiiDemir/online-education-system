<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ScimFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SCIM 2.0 (RFC 7644) User provisioning endpoint for an identity provider
 * (Okta, Entra ID, etc.) to create/update/deactivate accounts automatically
 * instead of an admin doing it by hand in Filament. Deliberately Users-only:
 * SCIM Groups (RFC 7643 §4.2) would need real membership-sync semantics
 * this app has no equivalent concept for yet -- noted as a scope
 * reduction, not silently skipped (see ReportController's docblock for the
 * same convention elsewhere in this app).
 *
 * Validation is done by hand rather than Request::validate() so every
 * failure path can return a spec-shaped SCIM error body instead of
 * Laravel's own JSON error format, which a SCIM client wouldn't recognize.
 */
class ScimUserController extends Controller
{
    private const SCIM_USER_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';

    private const CONTENT_TYPE = ['Content-Type' => 'application/scim+json'];

    public function index(Request $r)
    {
        $query = User::query();

        $filter = ScimFilter::parse($r->query('filter'));
        if ($filter) {
            match ($filter['attribute']) {
                'username', 'email', 'emails.value' => $query->where('email', $filter['value']),
                'externalid' => $query->where('external_id', $filter['value']),
                'active' => $query->where(function ($q) use ($filter) {
                    filter_var($filter['value'], FILTER_VALIDATE_BOOLEAN)
                        ? $q->whereNull('deactivated_at')
                        : $q->whereNotNull('deactivated_at');
                }),
                // An unrecognized attribute matches nothing rather than
                // silently returning the whole unfiltered user list.
                default => $query->whereRaw('1 = 0'),
            };
        }

        $startIndex = max(1, (int) $r->query('startIndex', 1));
        $count = min(100, max(1, (int) $r->query('count', 20)));
        $total = (clone $query)->count();
        $users = $query->orderBy('id')->skip($startIndex - 1)->take($count)->get();

        return response()->json([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $total,
            'startIndex' => $startIndex,
            'itemsPerPage' => $users->count(),
            'Resources' => $users->map(fn (User $u) => $this->toScim($u))->all(),
        ], 200, self::CONTENT_TYPE);
    }

    public function show(string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->notFound($id);
        }

        return response()->json($this->toScim($user), 200, self::CONTENT_TYPE);
    }

    public function store(Request $r)
    {
        $data = $r->json()->all();

        $email = $data['userName'] ?? ($data['emails'][0]['value'] ?? null);
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->scimError(400, 'A valid userName or emails[0].value (used as the account email) is required.', 'invalidValue');
        }
        if (User::where('email', $email)->exists()) {
            return $this->scimError(409, 'A user with this email already exists.', 'uniqueness');
        }
        $externalId = $data['externalId'] ?? null;
        if ($externalId && User::where('external_id', $externalId)->exists()) {
            return $this->scimError(409, 'A user with this externalId already exists.', 'uniqueness');
        }

        $name = $this->resolveName($data['name'] ?? []) ?? Str::before($email, '@');
        $active = array_key_exists('active', $data) ? (bool) $data['active'] : true;

        // No password is set from the SCIM payload -- provisioning happens
        // ahead of the person ever needing to log in locally, and this app
        // has no SSO integration for this identity provider to hand off to
        // yet. A random, unusable password (same pattern AccountController
        // already uses for anonymized accounts) is the safe placeholder.
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'external_id' => $externalId,
            'password' => Hash::make(Str::random(40)),
            'role' => 'learner',
            'deactivated_at' => $active ? null : now(),
        ]);

        return response()->json($this->toScim($user), 201, self::CONTENT_TYPE);
    }

    /**
     * SCIM's PUT is spec'd as a full replace, but real-world IdPs routinely
     * send a partial resource on PUT anyway -- this only overwrites
     * attributes actually present in the payload, the same pragmatic
     * compromise most SCIM server implementations make.
     */
    public function update(Request $r, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->notFound($id);
        }

        $data = $r->json()->all();
        $updates = [];

        if (array_key_exists('userName', $data) || array_key_exists('emails', $data)) {
            $email = $data['userName'] ?? ($data['emails'][0]['value'] ?? null);
            if ($email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->scimError(400, 'userName/emails[0].value must be a valid email.', 'invalidValue');
                }
                $exists = User::where('email', $email)->whereKeyNot($user->id)->exists();
                if ($exists) {
                    return $this->scimError(409, 'A user with this email already exists.', 'uniqueness');
                }
                $updates['email'] = $email;
            }
        }
        if (array_key_exists('name', $data)) {
            $name = $this->resolveName($data['name'] ?? []);
            if ($name) {
                $updates['name'] = $name;
            }
        }
        if (array_key_exists('externalId', $data)) {
            $updates['external_id'] = $data['externalId'];
        }
        if (array_key_exists('active', $data)) {
            $updates['deactivated_at'] = $data['active'] ? null : now();
        }

        $user->update($updates);

        return response()->json($this->toScim($user), 200, self::CONTENT_TYPE);
    }

    /**
     * SCIM PATCH (RFC 7644 §3.5.2): an Operations array of {op, path,
     * value} entries. Handles both the spec'd per-attribute `path` form
     * and the no-`path`-plus-object-`value` form real IdPs (notably Entra
     * ID) commonly send instead, e.g. deactivation as
     * {"op":"replace","value":{"active":false}}.
     */
    public function patch(Request $r, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->notFound($id);
        }

        $operations = $r->json('Operations', []);
        if (! is_array($operations) || empty($operations)) {
            return $this->scimError(400, 'Operations must be a non-empty array.', 'invalidValue');
        }

        foreach ($operations as $operation) {
            $op = strtolower($operation['op'] ?? '');
            if (! in_array($op, ['replace', 'add'], true)) {
                continue;
            }

            if (isset($operation['path'])) {
                $this->applyScimPath($user, $operation['path'], $operation['value'] ?? null);
            } elseif (is_array($operation['value'] ?? null)) {
                foreach ($operation['value'] as $path => $value) {
                    $this->applyScimPath($user, (string) $path, $value);
                }
            }
        }

        if ($user->isDirty()) {
            $exists = User::where('email', $user->email)->whereKeyNot($user->id)->exists();
            if ($exists) {
                return $this->scimError(409, 'A user with this email already exists.', 'uniqueness');
            }
            $user->save();
        }

        return response()->json($this->toScim($user), 200, self::CONTENT_TYPE);
    }

    /**
     * Deactivates rather than deletes -- an IdP's DELETE means "this
     * person left/is disabled", not "erase their history", and a sync
     * hiccup that removes-then-re-adds someone shouldn't have scrubbed
     * their name/email/completion records in between. This is a
     * different situation from AccountController::destroy() (a person
     * explicitly asking to delete their own account), which anonymizes;
     * a SCIM-driven offboarding is reversible by design, matching how
     * "active": false already works for PATCH/PUT.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->notFound($id);
        }

        $user->update(['deactivated_at' => now()]);

        return response()->noContent();
    }

    private function toScim(User $user): array
    {
        return [
            'schemas' => [self::SCIM_USER_SCHEMA],
            'id' => (string) $user->id,
            'externalId' => $user->external_id,
            'userName' => $user->email,
            'name' => ['formatted' => $user->name],
            'emails' => [['value' => $user->email, 'primary' => true]],
            'active' => ! $user->isDeactivated(),
            'meta' => [
                'resourceType' => 'User',
                'created' => $user->created_at?->toAtomString(),
                'lastModified' => $user->updated_at?->toAtomString(),
                'location' => url('/scim/v2/Users/'.$user->id),
            ],
        ];
    }

    private function resolveName(array $name): ?string
    {
        if (! empty($name['formatted'])) {
            return $name['formatted'];
        }
        $parts = array_filter([$name['givenName'] ?? null, $name['familyName'] ?? null]);

        return $parts ? implode(' ', $parts) : null;
    }

    private function applyScimPath(User $user, string $path, mixed $value): void
    {
        match (strtolower($path)) {
            'active' => $user->deactivated_at = $value ? null : now(),
            'username', 'emails[type eq "work"].value' => $user->email = $value,
            'externalid' => $user->external_id = $value,
            'name.formatted' => $user->name = $value,
            'name.givenname' => $user->name = trim(($value ?? '').' '.Str::afterLast($user->name, ' ')),
            'name.familyname' => $user->name = trim(Str::beforeLast($user->name, ' ').' '.($value ?? '')),
            default => null,
        };
    }

    private function notFound(string $id)
    {
        return $this->scimError(404, "User {$id} not found.");
    }

    private function scimError(int $status, string $detail, ?string $scimType = null)
    {
        $body = [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'status' => (string) $status,
            'detail' => $detail,
        ];
        if ($scimType) {
            $body['scimType'] = $scimType;
        }

        return response()->json($body, $status, self::CONTENT_TYPE);
    }
}
