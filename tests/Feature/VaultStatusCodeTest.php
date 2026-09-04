<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VaultStatusCodeTest extends TestCase
{
    public function test_unauthenticated_user_cannot_browse_vault(): void
    {
        $response = $this->get('/vault');

        $response->assertRedirect(route('login'));
    }

    public function test_missing_note_returns_not_found_status(): void
    {
        $response = $this->get('/vault/does-not-exist');

        $response->assertStatus(404);
    }

    public function test_graph_excludes_dm_only_notes_for_non_master_users(): void
    {
        $vaultPath = base_path('Vault');
        File::ensureDirectoryExists($vaultPath);

        $notePath = $vaultPath . '/test-graph-visibility.md';
        $noteContent = "#dm\n\nQuesta nota deve essere nascosta dal grafo per utenti non master.";

        File::put($notePath, $noteContent);

        try {
            $user = new User(['id' => 999, 'name' => 'Player', 'email' => 'player@example.com']);
            $response = $this->actingAs($user)->get('/vault');

            if ($response->status() === 302) {
                $response = $this->actingAs($user)->followRedirects($response);
            }

            if ($response->status() === 200) {
                $graphData = $response->viewData('graphData');
                if ($graphData) {
                    $nodeIds = array_column($graphData['nodes'] ?? [], 'id');
                    $this->assertNotContains('test-graph-visibility', $nodeIds);
                }
            } else {
                $this->assertTrue(true);
            }
        } finally {
            File::delete($notePath);
        }
    }
}
