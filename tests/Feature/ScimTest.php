<?php

namespace Tests\Feature;

use App\Filament\Resources\ScimTokens\Pages\CreateScimToken;
use App\Filament\Resources\ScimTokens\Pages\ListScimTokens;
use App\Models\ScimToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 SCIM 2.0 provisioning (RFC 7644), Users-only (see ScimUserController's
 * docblock for the Groups scope reduction). Every request is authenticated
 * with a bearer token issued via the admin panel (ScimToken); there is no
 * session/CSRF involved, matching how a real identity provider talks to
 * this endpoint.
 */
class ScimTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(): array
    {
        [, $plaintext] = ScimToken::generate('Test IdP');

        return ['Authorization' => 'Bearer '.$plaintext];
    }

    // --- Auth -----------------------------------------------------------

    public function test_a_request_with_no_token_is_rejected(): void
    {
        $response = $this->getJson('/api/scim/v2/Users');

        $response->assertStatus(401);
        $response->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:Error');
    }

    public function test_a_request_with_an_invalid_token_is_rejected(): void
    {
        $response = $this->getJson('/api/scim/v2/Users', ['Authorization' => 'Bearer not-a-real-token']);

        $response->assertStatus(401);
    }

    public function test_a_valid_token_is_accepted_and_its_last_used_at_is_updated(): void
    {
        [$token, $plaintext] = ScimToken::generate('Test IdP');
        $this->assertNull($token->last_used_at);

        $this->getJson('/api/scim/v2/Users', ['Authorization' => 'Bearer '.$plaintext])->assertOk();

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_a_revoked_token_no_longer_authenticates(): void
    {
        [$token, $plaintext] = ScimToken::generate('Test IdP');
        $token->delete();

        $this->getJson('/api/scim/v2/Users', ['Authorization' => 'Bearer '.$plaintext])->assertStatus(401);
    }

    // --- Create -----------------------------------------------------------

    public function test_creating_a_user_defaults_to_the_learner_role_and_active_true(): void
    {
        $response = $this->postJson('/api/scim/v2/Users', [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'userName' => 'yeni.kullanici@example.com',
            'name' => ['givenName' => 'Yeni', 'familyName' => 'Kullanıcı'],
            'externalId' => 'idp-001',
        ], $this->bearer());

        $response->assertStatus(201);
        $response->assertJsonPath('userName', 'yeni.kullanici@example.com');
        $response->assertJsonPath('active', true);
        $this->assertDatabaseHas('users', ['email' => 'yeni.kullanici@example.com', 'role' => 'learner', 'external_id' => 'idp-001', 'deactivated_at' => null]);
        $user = User::where('email', 'yeni.kullanici@example.com')->firstOrFail();
        $this->assertSame('Yeni Kullanıcı', $user->name);
    }

    public function test_creating_a_user_with_active_false_deactivates_immediately(): void
    {
        $response = $this->postJson('/api/scim/v2/Users', [
            'userName' => 'pasif@example.com',
            'active' => false,
        ], $this->bearer());

        $response->assertStatus(201);
        $response->assertJsonPath('active', false);
        $user = User::where('email', 'pasif@example.com')->firstOrFail();
        $this->assertNotNull($user->deactivated_at);
    }

    public function test_creating_a_user_with_a_duplicate_email_returns_409(): void
    {
        User::factory()->create(['email' => 'var@example.com']);

        $response = $this->postJson('/api/scim/v2/Users', ['userName' => 'var@example.com'], $this->bearer());

        $response->assertStatus(409);
        $response->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:Error');
    }

    public function test_creating_a_user_with_a_duplicate_external_id_returns_409(): void
    {
        User::factory()->create(['external_id' => 'idp-existing']);

        $response = $this->postJson('/api/scim/v2/Users', [
            'userName' => 'baska@example.com',
            'externalId' => 'idp-existing',
        ], $this->bearer());

        $response->assertStatus(409);
    }

    public function test_creating_a_user_without_a_valid_email_returns_400(): void
    {
        $response = $this->postJson('/api/scim/v2/Users', ['name' => ['givenName' => 'İsimsiz']], $this->bearer());

        $response->assertStatus(400);
        $response->assertJsonPath('scimType', 'invalidValue');
    }

    // --- Show / List --------------------------------------------------------

    public function test_showing_a_user_returns_the_scim_shape(): void
    {
        $user = User::factory()->create(['email' => 'gosterilen@example.com', 'name' => 'Görünen Kullanıcı']);

        $response = $this->getJson("/api/scim/v2/Users/{$user->id}", $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('id', (string) $user->id);
        $response->assertJsonPath('userName', 'gosterilen@example.com');
        $response->assertJsonPath('name.formatted', 'Görünen Kullanıcı');
        $response->assertJsonPath('active', true);
    }

    public function test_showing_a_nonexistent_user_returns_a_scim_404(): void
    {
        $response = $this->getJson('/api/scim/v2/Users/999999', $this->bearer());

        $response->assertStatus(404);
        $response->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:Error');
    }

    public function test_listing_users_returns_a_list_response_envelope(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/scim/v2/Users', $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('schemas.0', 'urn:ietf:params:scim:api:messages:2.0:ListResponse');
        $response->assertJsonPath('totalResults', 3);
        $this->assertCount(3, $response->json('Resources'));
    }

    public function test_listing_users_respects_start_index_and_count(): void
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/scim/v2/Users?startIndex=2&count=2', $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('totalResults', 5);
        $response->assertJsonPath('itemsPerPage', 2);
        $this->assertCount(2, $response->json('Resources'));
    }

    public function test_filtering_by_username_returns_only_the_matching_user(): void
    {
        User::factory()->create(['email' => 'hedef@example.com']);
        User::factory()->create(['email' => 'diger@example.com']);

        $response = $this->getJson('/api/scim/v2/Users?'.http_build_query(['filter' => 'userName eq "hedef@example.com"']), $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('totalResults', 1);
        $response->assertJsonPath('Resources.0.userName', 'hedef@example.com');
    }

    public function test_filtering_by_external_id_returns_only_the_matching_user(): void
    {
        User::factory()->create(['external_id' => 'idp-target']);
        User::factory()->create(['external_id' => 'idp-other']);

        $response = $this->getJson('/api/scim/v2/Users?'.http_build_query(['filter' => 'externalId eq "idp-target"']), $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('totalResults', 1);
    }

    public function test_filtering_by_active_false_returns_only_deactivated_users(): void
    {
        User::factory()->create(['deactivated_at' => now()]);
        User::factory()->create(['deactivated_at' => null]);

        $response = $this->getJson('/api/scim/v2/Users?'.http_build_query(['filter' => 'active eq false']), $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('totalResults', 1);
        $response->assertJsonPath('Resources.0.active', false);
    }

    // --- Update (PUT) --------------------------------------------------------

    public function test_put_updates_only_the_fields_present_in_the_payload(): void
    {
        $user = User::factory()->create(['email' => 'degismeyen@example.com', 'name' => 'Eski İsim']);

        $response = $this->putJson("/api/scim/v2/Users/{$user->id}", [
            'name' => ['formatted' => 'Yeni İsim'],
        ], $this->bearer());

        $response->assertOk();
        $user->refresh();
        $this->assertSame('Yeni İsim', $user->name);
        $this->assertSame('degismeyen@example.com', $user->email);
    }

    public function test_put_with_active_false_deactivates_the_user(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/scim/v2/Users/{$user->id}", ['active' => false], $this->bearer())->assertOk();

        $this->assertNotNull($user->fresh()->deactivated_at);
    }

    public function test_put_rejects_an_email_already_used_by_another_user(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'catisan@example.com']);

        $response = $this->putJson("/api/scim/v2/Users/{$user->id}", ['userName' => 'catisan@example.com'], $this->bearer());

        $response->assertStatus(409);
    }

    // --- Patch --------------------------------------------------------

    public function test_patch_replace_active_deactivates_with_a_path(): void
    {
        $user = User::factory()->create();

        $response = $this->patchJson("/api/scim/v2/Users/{$user->id}", [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
            'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => false]],
        ], $this->bearer());

        $response->assertOk();
        $this->assertNotNull($user->fresh()->deactivated_at);
    }

    /**
     * The real-world Entra ID deactivation payload: no `path`, an object
     * `value` with the attribute inside it instead.
     */
    public function test_patch_replace_with_no_path_and_an_object_value_deactivates(): void
    {
        $user = User::factory()->create();

        $response = $this->patchJson("/api/scim/v2/Users/{$user->id}", [
            'Operations' => [['op' => 'Replace', 'value' => ['active' => false]]],
        ], $this->bearer());

        $response->assertOk();
        $this->assertNotNull($user->fresh()->deactivated_at);
    }

    public function test_patch_can_reactivate_a_deactivated_user(): void
    {
        $user = User::factory()->create(['deactivated_at' => now()]);

        $this->patchJson("/api/scim/v2/Users/{$user->id}", [
            'Operations' => [['op' => 'replace', 'path' => 'active', 'value' => true]],
        ], $this->bearer())->assertOk();

        $this->assertNull($user->fresh()->deactivated_at);
    }

    public function test_patch_replace_family_name_updates_the_combined_name(): void
    {
        $user = User::factory()->create(['name' => 'Ahmet Yılmaz']);

        $this->patchJson("/api/scim/v2/Users/{$user->id}", [
            'Operations' => [['op' => 'replace', 'path' => 'name.familyName', 'value' => 'Kaya']],
        ], $this->bearer())->assertOk();

        $this->assertSame('Ahmet Kaya', $user->fresh()->name);
    }

    public function test_patch_ignores_unrecognized_paths_without_erroring(): void
    {
        $user = User::factory()->create();

        $response = $this->patchJson("/api/scim/v2/Users/{$user->id}", [
            'Operations' => [['op' => 'replace', 'path' => 'title', 'value' => 'Yönetici']],
        ], $this->bearer());

        $response->assertOk();
    }

    // --- Delete (deprovision) --------------------------------------------------------

    /**
     * SCIM DELETE deactivates rather than destroys -- see
     * ScimUserController::destroy()'s docblock for why this deliberately
     * differs from AccountController::destroy()'s anonymize-on-request
     * behavior.
     */
    public function test_delete_deactivates_but_does_not_remove_the_user_row(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/scim/v2/Users/{$user->id}", [], $this->bearer());

        $response->assertNoContent();
        $user->refresh();
        $this->assertNotNull($user);
        $this->assertNotNull($user->deactivated_at);
        $this->assertNotNull($user->email); // PII intact, unlike AccountController::destroy()
    }

    public function test_delete_of_a_nonexistent_user_returns_a_scim_404(): void
    {
        $response = $this->deleteJson('/api/scim/v2/Users/999999', [], $this->bearer());

        $response->assertStatus(404);
    }

    // --- Admin token management --------------------------------------------------------

    public function test_admin_can_create_a_scim_token_from_the_admin_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CreateScimToken::class)
            ->fillForm(['name' => 'Okta prod'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('scim_tokens', ['name' => 'Okta prod']);
    }

    public function test_a_newly_created_tokens_plaintext_actually_authenticates(): void
    {
        [, $plaintext] = ScimToken::generate('İzin Testi');

        $this->getJson('/api/scim/v2/Users', ['Authorization' => 'Bearer '.$plaintext])->assertOk();
    }

    public function test_deleting_a_token_from_the_admin_panel_revokes_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$token, $plaintext] = ScimToken::generate('İptal edilecek');

        Livewire::actingAs($admin)->test(ListScimTokens::class)
            ->callTableAction('delete', $token);

        $this->assertDatabaseMissing('scim_tokens', ['id' => $token->id]);
        $this->getJson('/api/scim/v2/Users', ['Authorization' => 'Bearer '.$plaintext])->assertStatus(401);
    }
}
