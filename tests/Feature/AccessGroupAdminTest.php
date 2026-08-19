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

    public function test_admin_can_create_access_group(): void
    {
        $admin = User::factory()->create(['admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.access_groups.store'), [
            'name' => 'Cavalieri del Drago',
            'slug' => 'cavalieri-del-drago',
            'color' => '#336699',
            'description' => 'Un ordine antico di cavalieri',
        ]);

        $response->assertRedirect(route('admin.access_groups'));
        $this->assertDatabaseHas('access_groups', [
            'slug' => 'cavalieri-del-drago',
            'name' => 'Cavalieri del Drago',
        ]);
    }

    public function test_invalid_slug_is_rejected(): void
    {
        $admin = User::factory()->create(['admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.access_groups.store'), [
            'name' => 'Test Invalido',
            'slug' => 'invalid|slug,with,commas',
            'color' => '#336699',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_admin_can_assign_groups_to_user(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $targetUser = User::factory()->create();
        $group = AccessGroup::create([
            'name' => 'Custodi della Fiamma',
            'slug' => 'custodi-fiamma',
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
