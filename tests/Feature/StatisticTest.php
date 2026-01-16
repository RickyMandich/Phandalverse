<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Statistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StatisticTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that middleware records statistics for guests.
     */
    public function test_tracks_guest_requests(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        $this->assertDatabaseHas('statistics', [
            'url' => url('/'),
            'http_method' => 'GET',
            'user_id' => null,
        ]);
    }

    /**
     * Test that middleware records statistics for authenticated users.
     */
    public function test_tracks_authenticated_user_requests(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);

        $this->assertDatabaseHas('statistics', [
            'url' => url('/'),
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test that admin can view statistics page.
     */
    public function test_admin_can_view_statistics(): void
    {
        $admin = User::factory()->create(['admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.statistics');
    }

    /**
     * Test that non-admin cannot view statistics page.
     */
    public function test_non_admin_cannot_view_statistics(): void
    {
        $user = User::factory()->create(['admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.statistics'));

        // Assuming middleware redirects to 403 or home, but based on AdminController it returns view errors.403
        // Let's check status. If it returns the view, status is 200 but content contains 403 error.
        // Wait, AdminController::checkAdmin returns view('errors.403').

        $response->assertStatus(200);
        $response->assertSee('403');
    }

    /**
     * Test grouped statistics query scope.
     */
    public function test_grouped_statistics_scope(): void
    {
        $user = User::factory()->create();

        // Create 3 requests from same user/ip
        Statistic::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'url' => 'http://localhost/1',
            'http_method' => 'GET',
        ]);
        Statistic::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'url' => 'http://localhost/2',
            'http_method' => 'GET',
        ]);
        Statistic::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'url' => 'http://localhost/3',
            'http_method' => 'GET',
        ]);

        // Create 1 request from different IP
        Statistic::create([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.1',
            'url' => 'http://localhost/4',
            'http_method' => 'GET',
        ]);

        $stats = Statistic::getGroupedByUserAndIp()->get();

        $this->assertEquals(2, $stats->count());
        $this->assertEquals(3, $stats->first()->request_count);
    }

    /**
     * Test CSV export.
     */
    public function test_csv_export(): void
    {
        $admin = User::factory()->create(['admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.statistics.export.csv'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
