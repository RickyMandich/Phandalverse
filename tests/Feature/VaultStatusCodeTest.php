<?php

namespace Tests\Feature;

use Tests\TestCase;

class VaultStatusCodeTest extends TestCase
{
    public function test_missing_note_returns_not_found_status(): void
    {
        $response = $this->get('/vault/does-not-exist');

        $response->assertStatus(404);
    }
}
