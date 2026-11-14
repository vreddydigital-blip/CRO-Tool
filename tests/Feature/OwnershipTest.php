<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::create([
            'user_id' => $owner->id, 'name' => 'Owner site',
            'url' => 'https://example.com', 'primary_url' => 'https://example.com',
        ]);

        $this->actingAs($other)->get("/projects/{$project->id}")->assertForbidden();
    }

    public function test_a_user_cannot_view_another_users_scan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::create([
            'user_id' => $owner->id, 'name' => 'Owner site',
            'url' => 'https://example.com', 'primary_url' => 'https://example.com',
        ]);
        $scan = Scan::create([
            'project_id' => $project->id, 'user_id' => $owner->id,
            'url' => 'https://example.com', 'status' => 'completed',
        ]);

        $this->actingAs($other)->get("/scans/{$scan->id}")->assertForbidden();
        $this->actingAs($other)->get("/scans/{$scan->id}/report")->assertForbidden();
    }

    public function test_a_user_cannot_delete_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::create([
            'user_id' => $owner->id, 'name' => 'Owner site',
            'url' => 'https://example.com', 'primary_url' => 'https://example.com',
        ]);

        $this->actingAs($other)->delete("/projects/{$project->id}")->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_a_user_can_view_their_own_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::create([
            'user_id' => $owner->id, 'name' => 'My site',
            'url' => 'https://example.com', 'primary_url' => 'https://example.com',
        ]);

        $this->actingAs($owner)->get("/projects/{$project->id}")->assertOk();
    }

    public function test_project_creation_rejects_private_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', [
            'name' => 'Evil', 'url' => 'http://127.0.0.1/admin',
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseCount('projects', 0);
    }
}
