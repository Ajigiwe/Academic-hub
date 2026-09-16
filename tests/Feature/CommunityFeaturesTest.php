<?php

namespace Tests\Feature;

use App\Models\{Bundle, Course, Favourite, Notification, Order, Programme, Resource, Setting, User};
use App\Services\Payments\OrderFulfiller;
use App\Services\ProgrammeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

/**
 * Slice 5: programme visibility settings, free materials with public
 * downloads, and the student notification + favourites surface.
 */
class CommunityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);
        $this->student = User::where('email', 'student@pastq.test')->first();
        $this->admin = User::where('email', 'admin@pastq.test')->first();
    }

    // ── Programme visibility settings ─────────────────────────────────

    public function test_missing_setting_row_counts_as_enabled(): void
    {
        $programme = Programme::first();
        Setting::where('key', ProgrammeSettings::settingKey($programme->slug))->delete();

        $this->assertTrue(ProgrammeSettings::isEnabled($programme->slug));
    }

    public function test_disabled_programme_hidden_from_catalog_and_bundle_404s(): void
    {
        $bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $programme = $bundle->programme;

        // Visible before, hidden after the toggle. The guided results
        // view lists the bundle; programme cards are step 1 of the flow.
        $this->get('/courses?programme=accounting&level=100&semester=1')
            ->assertOk()->assertSee($bundle->course->code);
        $this->get("/bundles/{$bundle->slug}")->assertOk();

        ProgrammeSettings::setEnabled($programme->slug, false);

        $this->get('/courses')->assertOk()->assertDontSee($bundle->course->code);
        $this->get("/bundles/{$bundle->slug}")->assertNotFound();
    }

    public function test_disabled_programme_hides_its_free_materials_too(): void
    {
        $bundle = Bundle::where('slug', 'psm-201-procurement-principles-2024-2025')->first();

        $this->get('/materials?programme=procurement-and-supply&level=200&semester=1')
            ->assertOk()
            ->assertSee('Revision Pack');

        ProgrammeSettings::setEnabled($bundle->programme->slug, false);

        $this->get('/materials?programme=procurement-and-supply&level=200&semester=1')
            ->assertOk()
            ->assertDontSee('Revision Pack');
        // And the download route 404s for that track.
        $this->get('/materials/psm-201-procurement-principles-revision-pack/download')
            ->assertNotFound();
    }

    public function test_admin_can_toggle_visibility(): void
    {
        $programme = Programme::first();

        $this->actingAs($this->student)->get('/admin/settings')->assertNotFound();

        $this->actingAs($this->admin)->post("/admin/settings/{$programme->id}/toggle", ['enabled' => '0'])
            ->assertRedirect();

        $this->assertSame('false', Setting::where('key', ProgrammeSettings::settingKey($programme->slug))->value('value'));
        $this->assertFalse(ProgrammeSettings::isEnabled($programme->slug));

        $this->actingAs($this->admin)->post("/admin/settings/{$programme->id}/toggle", ['enabled' => '1'])
            ->assertRedirect();
        $this->assertTrue(ProgrammeSettings::isEnabled($programme->slug));
    }

    // ── Programme management: add / rename / delete ───────────────────

    public function test_admin_can_add_programme_with_generated_slug(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/settings', ['name' => "Bachelor of Science in IT"])
            ->assertRedirect();

        $programme = Programme::where('slug', 'bachelor-of-science-in-it')->first();
        $this->assertNotNull($programme, 'The programme must be created with the slugified name.');
        $this->assertSame('Bachelor of Science in IT', $programme->name);
        $this->assertTrue(
            ProgrammeSettings::isEnabled('bachelor-of-science-in-it'),
            'New tracks start ENABLED so they appear in the browse flow immediately.'
        );
        // Idempotent: a repeated submit updates rather than duplicating.
        $this->actingAs($this->admin)
            ->post('/admin/settings', ['name' => 'Bachelor of Science in IT'])
            ->assertRedirect();
        $this->assertSame(1, Programme::where('slug', 'bachelor-of-science-in-it')->count());
    }

    public function test_student_cannot_add_programme(): void
    {
        $this->actingAs($this->student)
            ->post('/admin/settings', ['name' => 'Sneaky Track'])
            ->assertNotFound();
        $this->assertDatabaseMissing('programmes', ['name' => 'Sneaky Track']);
    }

    public function test_rename_regenerates_slug_and_migrates_visibility_setting(): void
    {
        $programme = Programme::where('slug', 'accounting')->firstOrFail();
        ProgrammeSettings::setEnabled('accounting', false);

        $this->actingAs($this->admin)
            ->post("/admin/settings/{$programme->id}/rename", ['name' => 'Financial Accounting'])
            ->assertRedirect();

        $programme->refresh();
        $this->assertSame('Financial Accounting', $programme->name);
        $this->assertSame('financial-accounting', $programme->slug);
        // The visibility state must survive the slug change.
        $this->assertSame(
            'false',
            Setting::where('key', ProgrammeSettings::settingKey('financial-accounting'))->value('value'),
            'Renaming migrates the setting key so the track keeps its hidden state.'
        );
        $this->assertDatabaseMissing('settings', ['key' => ProgrammeSettings::settingKey('accounting')]);
    }

    public function test_rename_rejects_slug_owned_by_another_programme(): void
    {
        $a = Programme::where('slug', 'accounting')->firstOrFail();
        $marketing = Programme::where('slug', 'marketing')->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/admin/settings/{$a->id}/rename", ['name' => $marketing->name])
            ->assertRedirect();

        $this->assertSame('accounting', $a->refresh()->slug, 'The rename must be refused, not merged.');
        $this->assertNotNull(session('error'));
    }

    public function test_programme_with_content_cannot_be_deleted(): void
    {
        $programme = Programme::where('slug', 'accounting')->firstOrFail();
        $this->assertTrue($programme->bundles()->exists());

        $this->actingAs($this->admin)
            ->delete("/admin/settings/{$programme->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('programmes', ['id' => $programme->id]);
        $this->assertNotNull(session('error'), 'The blocked outcome must never be a silent no-op.');
    }

    public function test_empty_programme_can_be_deleted(): void
    {
        $programme = Programme::create(['name' => 'Disposable Track', 'slug' => 'disposable-track']);
        ProgrammeSettings::setEnabled('disposable-track', false);

        $this->actingAs($this->admin)
            ->delete("/admin/settings/{$programme->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('programmes', ['id' => $programme->id]);
        $this->assertDatabaseMissing(
            'settings',
            ['key' => ProgrammeSettings::settingKey('disposable-track')]
        );
    }

    // ── Free materials & public downloads ─────────────────────────────

    public function test_seeded_materials_are_publicly_downloadable(): void
    {
        $slug = 'acc-102-financial-accounting-lecture-notes';

        $this->get('/materials?programme=accounting&level=100&semester=1')
            ->assertOk()
            ->assertSee('Lecture Notes');

        // The seeder creates metadata only — attach a real PDF (the demo
        // generator does this on the dev DB; tests must self-provide).
        $material = Resource::where('slug', $slug)->first();
        $bytes = $this->demoPdf('ACC 102 lecture notes');
        $key = app(\App\Services\Documents\DocumentStore::class)->buildStorageKey();
        app(\App\Services\Documents\DocumentStore::class)->put($key, $bytes);
        $material->files()->create([
            'storage_key' => $key,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'original_name' => 'notes.pdf',
            'is_current' => true,
        ]);

        $response = $this->get("/materials/{$slug}/download");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // Download counter incremented.
        $this->assertSame(1, Resource::where('slug', $slug)->value('download_count'));
    }

    public function test_paid_paper_is_never_served_through_the_public_route(): void
    {
        // Every bundle paper belongs to a bundle — none may download.
        $paper = Resource::whereNotNull('bundle_id')->first();
        $this->get("/materials/{$paper->slug}/download")->assertNotFound();
    }

    public function test_draft_material_is_not_downloadable(): void
    {
        $material = Resource::whereNull('bundle_id')->first();
        $material->update(['status' => 'DRAFT', 'published_at' => null]);

        $this->get("/materials/{$material->slug}/download")->assertNotFound();
    }

    public function test_admin_can_upload_a_free_material(): void
    {
        $pdf = $this->demoPdf('Demo free material');

        $response = $this->actingAs($this->admin)->post('/admin/materials', [
            'title' => 'SMG 204 — Office Administration · Practice Pack',
            'type' => 'PRACTICE',
            'level' => '200',
            'semester' => '2',
            'course_code' => 'SMG 204',
            'course_title' => 'Office Administration',
            'programme' => 'Secretaryship and Management',
            'file' => UploadedFile::fake()->createWithContent('pack.pdf', $pdf),
        ]);

        $response->assertRedirect(route('admin.materials'));

        $material = Resource::where('slug', 'smg-204-office-administration-practice-pack')->first();
        $this->assertNotNull($material);
        $this->assertNull($material->bundle_id);
        $this->assertSame('PUBLISHED', $material->status);
        $this->assertSame('PRACTICE', $material->type);
        $this->assertNotNull($material->files->firstWhere('is_current', true));
    }

    public function test_admin_material_upload_rejects_non_pdf(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/materials', [
                'title' => 'Not a real PDF upload attempt',
                'type' => 'SLIDES',
                'level' => '200',
                'semester' => '1',
                'course_code' => 'XX 100',
                'course_title' => 'Something',
                'programme' => 'Marketing',
                'file' => UploadedFile::fake()->createWithContent('nope.pdf', 'not a pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error'); // PDF sniff fails → error flash back

        $this->assertSame(0, Resource::whereNull('bundle_id')->where('title', 'like', 'Not a real%')->count());
    }

    public function test_guest_cannot_use_admin_materials_pages(): void
    {
        $this->get('/admin/materials')->assertRedirect('/login');
        $this->post('/admin/materials')->assertRedirect('/login');
    }

    // ── Notifications ─────────────────────────────────────────────────

    public function test_purchase_creates_notification_and_bell_renders(): void
    {
        $bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $order = $this->createPendingOrder($bundle);

        (new OrderFulfiller)->fulfill($order->reference);

        $notification = Notification::where('user_id', $this->student->id)->sole();
        $this->assertSame('PURCHASE', $notification->type);
        $this->assertStringContainsString($order->reference, $notification->body);

        // Bell shows it; the list renders it.
        $this->actingAs($this->student)->get('/library')
            ->assertOk()
            ->assertSee('Notifications');
        $this->actingAs($this->student)->get('/notifications')
            ->assertOk()
            ->assertSee('Purchase confirmed');
    }

    public function test_mark_all_read_clears_the_bell(): void
    {
        Notification::create([
            'user_id' => $this->student->id,
            'type' => 'PURCHASE',
            'title' => 'Purchase confirmed',
            'body' => 'Order PQ-ABC123 is complete.',
        ]);

        $this->actingAs($this->student)->post('/notifications/read-all')->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $this->student->id)->whereNull('read_at')->count());
    }

    public function test_notifications_are_scoped_to_their_owner(): void
    {
        Notification::create([
            'user_id' => $this->admin->id,
            'type' => 'SYSTEM',
            'title' => 'Admin note',
            'body' => 'Admin-only body text.',
        ]);

        $this->actingAs($this->student)->get('/notifications')
            ->assertOk()
            ->assertDontSee('Admin-only body text.');
    }

    public function test_guest_cannot_read_notifications(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    // ── Favourites ────────────────────────────────────────────────────

    public function test_student_can_toggle_a_favourite(): void
    {
        $paper = Resource::whereNotNull('bundle_id')->first();

        $this->actingAs($this->student)->post("/favourites/{$paper->id}")->assertRedirect();
        $this->assertDatabaseHas('favourites', ['user_id' => $this->student->id, 'resource_id' => $paper->id]);

        // Toggle again removes it (idempotent composite key).
        $this->actingAs($this->student)->post("/favourites/{$paper->id}")->assertRedirect();
        $this->assertDatabaseMissing('favourites', ['user_id' => $this->student->id, 'resource_id' => $paper->id]);
    }

    public function test_favourite_of_a_free_material_shows_in_saved_items(): void
    {
        $material = Resource::whereNull('bundle_id')->first();

        $this->actingAs($this->student)->post("/favourites/{$material->id}")->assertRedirect();

        $this->actingAs($this->student)->get('/favourites')
            ->assertOk()
            ->assertSee($material->title)
            ->assertSee('Download');
    }

    public function test_guest_cannot_toggle_favourites(): void
    {
        $resource = Resource::first();

        $this->post("/favourites/{$resource->id}")->assertRedirect('/login');
        $this->assertDatabaseCount('favourites', 0);
    }

    public function test_favouriting_an_unknown_resource_404s(): void
    {
        $this->actingAs($this->student)->post('/favourites/99999')->assertNotFound();
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function createPendingOrder(Bundle $bundle): Order
    {
        $order = Order::create([
            'reference' => 'PQ-NOTIF1',
            'user_id' => $this->student->id,
            'status' => 'PENDING',
            'amount_pesewas' => $bundle->price_pesewas,
            'currency' => 'GHS',
        ]);
        $order->items()->create([
            'bundle_id' => $bundle->id,
            'unit_price_pesewas' => $bundle->price_pesewas,
            'quantity' => 1,
        ]);

        return $order;
    }

    private function demoPdf(string $title): string
    {
        $pdf = new Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 24, $title, 0, 1, 'C');

        return (string) $pdf->Output('', 'S');
    }
}
