<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
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
}
