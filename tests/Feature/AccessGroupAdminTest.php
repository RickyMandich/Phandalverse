<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessGroupAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_access_groups(): void
    {
        $user = User::factory()->create(['admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.access_groups'));
        $response->assertStatus(200); // AdminController returns view('errors.403')
        $response->assertSee('403');
    }

    public function test_admin_can_create_access_group_with_camel_case(): void
    {
        $admin = User::factory()->create(['admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.access_groups.store'), [
            'name' => 'Cavalieri del Drago',
            'slug' => 'cavalieriDelDrago',
            'color' => '#336699',
            'description' => 'Un ordine antico di cavalieri',
        ]);

        $response->assertRedirect(route('admin.access_groups'));
        $this->assertDatabaseHas('access_groups', [
            'slug' => 'cavalieriDelDrago',
            'name' => 'Cavalieri del Drago',
        ]);
    }

    public function test_slug_with_dash_or_underscore_is_rejected(): void
    {
        $admin = User::factory()->create(['admin' => true]);

        // Dash is rejected
        $responseDash = $this->actingAs($admin)->post(route('admin.access_groups.store'), [
            'name' => 'Test Trattino',
            'slug' => 'slug-con-trattino',
            'color' => '#336699',
        ]);
        $responseDash->assertSessionHasErrors('slug');

        // Underscore is rejected
        $responseUnderscore = $this->actingAs($admin)->post(route('admin.access_groups.store'), [
            'name' => 'Test Underscore',
            'slug' => 'slug_con_underscore',
            'color' => '#336699',
        ]);
        $responseUnderscore->assertSessionHasErrors('slug');
    }

    public function test_admin_can_assign_groups_to_user(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $targetUser = User::factory()->create();
        $group = AccessGroup::create([
            'name' => 'Custodi della Fiamma',
            'slug' => 'custodiDellaFiamma',
            'color' => '#ff5500',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $targetUser), [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'access_groups' => [$group->id],
        ]);

        $response->assertRedirect();
        $this->assertTrue($targetUser->fresh()->accessGroups->contains($group->id));
    }

    private function makeCampaign(string $folder, int $order): Campaign
    {
        return Campaign::create([
            'folder_name' => $folder,
            'display_name' => ucfirst($folder),
            'order' => $order,
        ]);
    }

    public function test_admin_can_copy_group_to_another_campaign(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $source = $this->makeCampaign('sorgente', 10);
        $target = $this->makeCampaign('destinazione', 20);

        $group = AccessGroup::create([
            'campaign_id' => $source->id,
            'name' => 'Artefici',
            'slug' => 'artefici',
            'description' => 'Costruttori di meraviglie',
            'color' => '#336699',
        ]);
        $member = User::factory()->create();
        $group->users()->attach($member->id);

        $response = $this->actingAs($admin)->post(route('admin.access_groups.copy', $group), [
            'target_campaign_id' => $target->id,
        ]);

        $response->assertRedirect(route('admin.access_groups'));
        $response->assertSessionHas('success');

        $copy = AccessGroup::where('campaign_id', $target->id)->where('slug', 'artefici')->first();
        $this->assertNotNull($copy);
        $this->assertSame('Artefici', $copy->name);
        $this->assertSame('Costruttori di meraviglie', $copy->description);
        $this->assertSame('#336699', $copy->color);
        $this->assertNull($copy->parent_id);
        $this->assertSame(0, $copy->users()->count(), 'I membri non devono essere copiati');

        // il gruppo originale resta invariato
        $this->assertDatabaseHas('access_groups', ['id' => $group->id, 'campaign_id' => $source->id]);
    }

    public function test_copy_links_parent_by_slug_when_present_in_target_campaign(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $source = $this->makeCampaign('sorgente', 10);
        $target = $this->makeCampaign('destinazione', 20);

        $sourceParent = AccessGroup::create(['campaign_id' => $source->id, 'name' => 'Artefici', 'slug' => 'artefici']);
        $sourceChild = AccessGroup::create([
            'campaign_id' => $source->id,
            'name' => 'Bibliotecari',
            'slug' => 'bibliotecari',
            'parent_id' => $sourceParent->id,
        ]);
        $targetParent = AccessGroup::create(['campaign_id' => $target->id, 'name' => 'Artefici Locali', 'slug' => 'artefici']);

        $this->actingAs($admin)->post(route('admin.access_groups.copy', $sourceChild), [
            'target_campaign_id' => $target->id,
        ])->assertSessionHas('success');

        $copy = AccessGroup::where('campaign_id', $target->id)->where('slug', 'bibliotecari')->first();
        $this->assertNotNull($copy);
        $this->assertSame($targetParent->id, $copy->parent_id);
    }

    public function test_copy_without_parent_in_target_campaign_creates_group_without_parent(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $source = $this->makeCampaign('sorgente', 10);
        $target = $this->makeCampaign('destinazione', 20);

        $sourceParent = AccessGroup::create(['campaign_id' => $source->id, 'name' => 'Artefici', 'slug' => 'artefici']);
        $sourceChild = AccessGroup::create([
            'campaign_id' => $source->id,
            'name' => 'Bibliotecari',
            'slug' => 'bibliotecari',
            'parent_id' => $sourceParent->id,
        ]);

        $this->actingAs($admin)->post(route('admin.access_groups.copy', $sourceChild), [
            'target_campaign_id' => $target->id,
        ])->assertSessionHas('success');

        $copy = AccessGroup::where('campaign_id', $target->id)->where('slug', 'bibliotecari')->first();
        $this->assertNotNull($copy);
        $this->assertNull($copy->parent_id);
        // il padre non viene copiato implicitamente
        $this->assertDatabaseMissing('access_groups', ['campaign_id' => $target->id, 'slug' => 'artefici']);
    }

    public function test_copy_is_rejected_when_slug_already_exists_in_target_campaign(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $source = $this->makeCampaign('sorgente', 10);
        $target = $this->makeCampaign('destinazione', 20);

        $group = AccessGroup::create(['campaign_id' => $source->id, 'name' => 'Artefici', 'slug' => 'artefici']);
        AccessGroup::create(['campaign_id' => $target->id, 'name' => 'Esistente', 'slug' => 'artefici']);

        $response = $this->actingAs($admin)->post(route('admin.access_groups.copy', $group), [
            'target_campaign_id' => $target->id,
        ]);

        $response->assertRedirect(route('admin.access_groups'));
        $response->assertSessionHas('error');
        $this->assertSame(1, AccessGroup::where('campaign_id', $target->id)->where('slug', 'artefici')->count());
        $this->assertSame('Esistente', AccessGroup::where('campaign_id', $target->id)->where('slug', 'artefici')->value('name'));
    }

    public function test_copy_to_the_same_campaign_is_rejected(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $source = $this->makeCampaign('sorgente', 10);

        $group = AccessGroup::create(['campaign_id' => $source->id, 'name' => 'Artefici', 'slug' => 'artefici']);

        $response = $this->actingAs($admin)->post(route('admin.access_groups.copy', $group), [
            'target_campaign_id' => $source->id,
        ]);

        $response->assertSessionHasErrors('target_campaign_id');
        $this->assertSame(1, AccessGroup::count());
    }

    public function test_non_admin_cannot_copy_group(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $source = $this->makeCampaign('sorgente', 10);
        $target = $this->makeCampaign('destinazione', 20);

        $group = AccessGroup::create(['campaign_id' => $source->id, 'name' => 'Artefici', 'slug' => 'artefici']);

        $this->actingAs($user)->post(route('admin.access_groups.copy', $group), [
            'target_campaign_id' => $target->id,
        ]);

        $this->assertDatabaseMissing('access_groups', ['campaign_id' => $target->id]);
    }
}
