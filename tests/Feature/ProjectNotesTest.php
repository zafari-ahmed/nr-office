<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectNotesTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->actingAs(User::create(['name' => 'Zafar', 'email' => 'z@nr.pk', 'password' => 'password', 'role' => 'admin']));
        $this->post('/projects', ['name' => 'Gulberg', 'status' => 'lead']);
        $this->project = Project::first();
    }

    public function test_formatting_is_kept_and_unsafe_html_removed(): void
    {
        $this->post("/projects/{$this->project->id}/notes", [
            'body' => '<h2 style="color:red">Meeting</h2><p onclick="x()">Use <strong>local</strong> tiles <a href="javascript:alert(1)">bad</a> <a href="https://nr.pk">spec</a></p><ul><li>One</li></ul><script>alert(1)</script><table><tbody><tr><td>Tiles</td></tr></tbody></table>',
        ])->assertRedirect();

        $body = ProjectNote::first()->body;
        $this->assertStringContainsString('<h2>Meeting</h2>', $body);
        $this->assertStringContainsString('<strong>local</strong>', $body);
        $this->assertStringContainsString('<li>One</li>', $body);
        $this->assertStringContainsString('<td>Tiles</td>', $body);
        $this->assertStringContainsString('href="https://nr.pk"', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('style=', $body);

        $this->get("/projects/{$this->project->id}?tab=notes")->assertOk()->assertSee('<h2>Meeting</h2>', false);
    }

    public function test_empty_note_is_rejected(): void
    {
        $this->post("/projects/{$this->project->id}/notes", ['body' => '<p> </p>'])->assertSessionHasErrors('body');
        $this->assertSame(0, ProjectNote::count());
    }

    public function test_old_plain_text_notes_are_escaped_with_line_breaks(): void
    {
        $this->project->notes()->create(['body' => "Line one\n<b>not bold</b>", 'user_id' => auth()->id()]);

        $this->get("/projects/{$this->project->id}?tab=notes")->assertSee('Line one<br />', false)->assertSee('&lt;b&gt;not bold&lt;/b&gt;', false);
    }
}
