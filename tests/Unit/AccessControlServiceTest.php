<?php

namespace Tests\Unit;

use App\Models\AccessGroup;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\MarkdownPreprocessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccessControlService::clearCache();
    }

    public function test_public_note_is_visible_to_all(): void
    {
        $content = "# Nota Pubblica\nContenuto aperto a tutti.";
        $user = User::factory()->create(['master' => false]);

        $this->assertTrue(AccessControlService::noteIsVisibleTo($content, null));
        $this->assertTrue(AccessControlService::noteIsVisibleTo($content, $user));
    }

    public function test_dm_note_is_only_visible_to_master(): void
    {
        $content = "#dm\n# Nota Segreta DM";
        $user = User::factory()->create(['master' => false]);
        $master = User::factory()->create(['master' => true]);

        $this->assertFalse(AccessControlService::noteIsVisibleTo($content, null));
        $this->assertFalse(AccessControlService::noteIsVisibleTo($content, $user));
        $this->assertTrue(AccessControlService::noteIsVisibleTo($content, $master));
    }

    public function test_group_hierarchy_access(): void
    {
        // artefici (parent) -> bibliotecari (child)
        $parentGroup = AccessGroup::create([
            'slug' => 'artefici',
            'name' => 'Artefici',
            'color' => '#112233',
        ]);

        $childGroup = AccessGroup::create([
            'slug' => 'bibliotecari',
            'name' => 'Bibliotecari',
            'color' => '#445566',
            'parent_id' => $parentGroup->id,
        ]);

        $parentUser = User::factory()->create(['master' => false]);
        $parentUser->accessGroups()->attach($parentGroup);

        $childUser = User::factory()->create(['master' => false]);
        $childUser->accessGroups()->attach($childGroup);

        $otherUser = User::factory()->create(['master' => false]);
        $masterUser = User::factory()->create(['master' => true]);

        $parentNote = "#access-artefici\n# Nota per Artefici";
        $childNote = "#access-bibliotecari\n# Nota per Bibliotecari";

        // Parent note: visible to parentUser, childUser (inherited), master; NOT to otherUser or guest
        $this->assertTrue(AccessControlService::noteIsVisibleTo($parentNote, $parentUser));
        $this->assertTrue(AccessControlService::noteIsVisibleTo($parentNote, $childUser));
        $this->assertTrue(AccessControlService::noteIsVisibleTo($parentNote, $masterUser));
        $this->assertFalse(AccessControlService::noteIsVisibleTo($parentNote, $otherUser));
        $this->assertFalse(AccessControlService::noteIsVisibleTo($parentNote, null));

        // Child note: visible to childUser and master; NOT to parentUser (parent doesn't see child)
        $this->assertTrue(AccessControlService::noteIsVisibleTo($childNote, $childUser));
        $this->assertTrue(AccessControlService::noteIsVisibleTo($childNote, $masterUser));
        $this->assertFalse(AccessControlService::noteIsVisibleTo($childNote, $parentUser));
        $this->assertFalse(AccessControlService::noteIsVisibleTo($childNote, $otherUser));
    }

    public function test_multiple_groups_with_underscore_or_logic(): void
    {
        $groupA = AccessGroup::create(['slug' => 'gildaLadri', 'name' => 'Gilda Ladri', 'color' => '#111111']);
        $groupB = AccessGroup::create(['slug' => 'gildaMaghi', 'name' => 'Gilda Maghi', 'color' => '#222222']);

        $userA = User::factory()->create(['master' => false]);
        $userA->accessGroups()->attach($groupA);

        $userB = User::factory()->create(['master' => false]);
        $userB->accessGroups()->attach($groupB);

        $userC = User::factory()->create(['master' => false]);

        $note = "#access-gildaLadri_gildaMaghi\nContenuto per ladri o maghi";

        $this->assertTrue(AccessControlService::noteIsVisibleTo($note, $userA));
        $this->assertTrue(AccessControlService::noteIsVisibleTo($note, $userB));
        $this->assertFalse(AccessControlService::noteIsVisibleTo($note, $userC));
    }

    public function test_resolve_block_color_in_same_branch(): void
    {
        $parent = AccessGroup::create(['slug' => 'gPadre', 'name' => 'Padre', 'color' => '#aaaaaa']);
        $child = AccessGroup::create(['slug' => 'gFiglio', 'name' => 'Figlio', 'color' => '#bbbbbb', 'parent_id' => $parent->id]);

        // When tagged with both parent and child in same branch, deepest child color is returned
        $color = AccessControlService::resolveBlockColor(['gPadre', 'gFiglio']);
        $this->assertEquals('#bbbbbb', $color);
    }

    public function test_resolve_block_color_in_unrelated_branches(): void
    {
        $group1 = AccessGroup::create(['slug' => 'ramoA', 'name' => 'Ramo A', 'color' => '#111111']);
        $group2 = AccessGroup::create(['slug' => 'ramoB', 'name' => 'Ramo B', 'color' => '#222222']);

        // When tagged with groups from unrelated branches, group with lower ID is deterministically chosen
        $color = AccessControlService::resolveBlockColor(['ramoA', 'ramoB']);
        $this->assertEquals($group1->color, $color);
    }

    public function test_compute_badge_groups_shows_user_direct_group(): void
    {
        $parent = AccessGroup::create(['slug' => 'artefici', 'name' => 'Artefici', 'color' => '#111111']);
        $child = AccessGroup::create(['slug' => 'bibliotecari', 'name' => 'Bibliotecari', 'color' => '#222222', 'parent_id' => $parent->id]);

        $childUser = User::factory()->create(['master' => false]);
        $childUser->accessGroups()->attach($child);

        // Block is tagged with parent 'artefici', but child user accessed it -> badge shows user's own group 'Bibliotecari'
        $badges = AccessControlService::computeBadgeGroups($childUser, ['artefici']);
        $this->assertCount(1, $badges);
        $this->assertEquals('Bibliotecari', $badges[0]['name']);
        $this->assertEquals('#222222', $badges[0]['color']);
    }

    public function test_markdown_access_block_parsing(): void
    {
        $group = AccessGroup::create(['slug' => 'segreto', 'name' => 'Gruppo Segreto', 'color' => '#ff0000']);
        $userWithAccess = User::factory()->create(['master' => false]);
        $userWithAccess->accessGroups()->attach($group);

        $userWithoutAccess = User::factory()->create(['master' => false]);

        $markdown = "Testo pubblico\n#startAccess-segreto\nTesto Segreto\n#endAccess\nFine";

        $filteredNoAccess = AccessControlService::filterAccessBlocks($markdown, $userWithoutAccess);
        $this->assertStringNotContainsString('Testo Segreto', $filteredNoAccess);
        $this->assertStringContainsString('Testo pubblico', $filteredNoAccess);

        $filteredWithAccess = AccessControlService::filterAccessBlocks($markdown, $userWithAccess);
        $this->assertStringContainsString('Testo Segreto', $filteredWithAccess);
    }
}
