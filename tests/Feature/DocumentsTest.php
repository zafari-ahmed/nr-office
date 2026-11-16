<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Letterhead;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(SettingsSeeder::class);

        return User::create(['name' => 'Zafar', 'email' => 'z@nr.pk', 'password' => 'password', 'role' => 'admin']);
    }

    public function test_admin_uploads_a_letterhead_and_its_images_are_served(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $this->get('/settings/letterheads')->assertOk()->assertSee('No letterhead yet');
        $this->get('/settings/letterheads/create')->assertOk();

        // Without the cut header/footer images the browser makes, nothing is saved.
        $this->post('/settings/letterheads', ['name' => 'Main', 'header_mm' => 40, 'footer_mm' => 20, 'side_mm' => 20])->assertSessionHasErrors('header');

        $this->post('/settings/letterheads', [
            'name' => 'Main', 'header_mm' => 42, 'footer_mm' => 20, 'side_mm' => 18,
            'source' => UploadedFile::fake()->create('letterhead.pdf', 50, 'application/pdf'),
            'header' => UploadedFile::fake()->image('header.png', 2480, 400),
            'footer' => UploadedFile::fake()->image('footer.png', 2480, 200),
        ])->assertRedirect('/settings/letterheads');

        $lh = Letterhead::firstOrFail();
        $this->assertTrue($lh->is_default);
        $this->assertSame(42.0, $lh->header_mm);
        $this->get(route('letterheads.image', [$lh, 'header']))->assertOk();
        $this->get(route('letterheads.image', [$lh, 'footer']))->assertOk();
        $this->get('/settings/letterheads')->assertOk()->assertSee('Main');
        $this->get("/settings/letterheads/{$lh->id}/edit")->assertOk();

        // Changing only the margins keeps the images.
        $this->put("/settings/letterheads/{$lh->id}", ['name' => 'Main', 'header_mm' => 42, 'footer_mm' => 20, 'side_mm' => 25])->assertRedirect();
        $this->assertSame(25.0, $lh->fresh()->side_mm);
        $this->assertNotNull($lh->fresh()->header_path);
    }

    public function test_template_then_document_then_print_history(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin);
        $lh = Letterhead::create(['name' => 'Main', 'is_default' => true]);

        $this->get('/settings/templates/create')->assertOk();
        $this->post('/settings/templates', [
            'name' => 'Purchase order', 'ref_prefix' => 'po', 'is_active' => 1,
            'body' => '<h2 style="text-align: center; color: red">Purchase order</h2><p>Ref {{ref}} dated {{date}}</p><script>alert(1)</script><p onclick="x()">To {{recipient}}</p>',
            'sample' => UploadedFile::fake()->create('old-po.docx', 30),
        ])->assertRedirect('/settings/letterheads');

        $template = DocumentTemplate::firstOrFail();
        $this->assertSame('PO', $template->ref_prefix);
        $this->assertStringContainsString('<h2 style="text-align: center">', $template->body);
        $this->assertStringNotContainsString('script', $template->body);
        $this->assertStringNotContainsString('onclick', $template->body);
        $this->get(route('settings.templates.sample', $template))->assertDownload('old-po.docx');

        $this->get('/documents')->assertOk()->assertSee('Purchase order');
        $this->get('/documents/create?template='.$template->id)->assertOk()->assertSee('New Purchase order');

        $this->post('/documents', [
            'template_id' => $template->id, 'title' => 'Cement order', 'recipient' => 'Lucky Traders', 'doc_date' => '2026-11-16',
            'body' => $template->body, 'action' => 'save',
        ])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertSame('PO-'.now()->year.'-0001', $doc->ref_no);

        $this->get("/documents/{$doc->id}")->assertOk()->assertSee('Ref '.$doc->ref_no.' dated 16 Nov 2026', false)->assertSee('To Lucky Traders');
        $this->get("/documents/{$doc->id}/print")->assertOk()->assertSee('Print / Save as PDF');

        $this->postJson("/documents/{$doc->id}/prints", ['with_letterhead' => true])->assertOk()->assertJson(['count' => 1]);
        $doc->update(['recipient' => 'Someone else']);
        $this->postJson("/documents/{$doc->id}/prints", ['with_letterhead' => false])->assertOk()->assertJson(['count' => 2]);

        $doc->refresh();
        $this->assertSame(2, $doc->print_count);
        $first = $doc->prints()->oldest('printed_at')->oldest('id')->first();
        $this->assertStringContainsString('To Lucky Traders', $first->body);
        $this->assertSame($lh->id, $first->letterhead_id);
        $this->get(route('document-prints.show', $first))->assertOk()->assertSee('To Lucky Traders')->assertSee('Copy of the print by Zafar');

        // Reprinting an old print logs a new print with that old content.
        $this->postJson("/documents/{$doc->id}/prints", ['from' => $first->id])->assertOk()->assertJson(['count' => 3]);
        $this->assertStringContainsString('To Lucky Traders', $doc->prints()->latest('id')->first()->body);

        // Duplicate gets the next number.
        $this->post("/documents/{$doc->id}/duplicate")->assertRedirect();
        $this->assertTrue(Document::where('ref_no', 'PO-'.now()->year.'-0002')->exists());
        $this->get('/documents')->assertOk()->assertSee('Cement order')->assertSee('Print history');
    }

    public function test_staff_cannot_use_documents_and_module_can_be_switched_off(): void
    {
        $admin = $this->admin();
        $staff = User::create(['name' => 'Ali', 'email' => 'a@nr.pk', 'password' => 'password', 'role' => 'staff']);
        $manager = User::create(['name' => 'Sara', 'email' => 's@nr.pk', 'password' => 'password', 'role' => 'manager']);

        $this->actingAs($staff)->get('/documents')->assertForbidden();
        $this->actingAs($staff)->get('/settings/letterheads')->assertForbidden();
        $this->actingAs($manager)->get('/documents')->assertOk();
        $this->actingAs($manager)->get('/')->assertSee(route('documents.index').'"', false);

        \App\Models\Setting::put('module.documents', '0');
        $this->actingAs($admin)->get('/documents')->assertNotFound();
        $this->actingAs($admin)->get('/settings/letterheads')->assertNotFound();
        $this->actingAs($admin)->get('/')->assertDontSee(route('documents.index').'"', false);
    }
}
