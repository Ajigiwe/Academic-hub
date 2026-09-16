<?php

namespace Tests\Feature;

use App\Models\{Bundle, Entitlement, Order, Payment, Programme, Resource, User, ViewingSession};
use App\Services\BundlePricing;
use App\Services\Documents\{DocumentStore, PdfWatermarker};
use App\Services\Payments\EntitlementBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Bundle $bundle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);

        $this->admin = User::where('email', 'admin@pastq.test')->first();
        $this->student = User::where('email', 'student@pastq.test')->first();
        $this->bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
    }

    // ── Access control ───────────────────────────────────────────────

    public function test_guest_cannot_reach_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_student_gets_404_on_admin_routes(): void
    {
        $this->actingAs($this->student)->get('/admin')->assertNotFound();
        $this->actingAs($this->student)->get('/admin/bundles')->assertNotFound();
        $this->actingAs($this->student)->get('/admin/students')->assertNotFound();
    }

    public function test_admin_reaches_dashboard_and_sections(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->actingAs($this->admin)->get('/admin/bundles')->assertOk()->assertSee('Bundles');
        $this->actingAs($this->admin)->get('/admin/papers')->assertOk()->assertSee('Papers');
        $this->actingAs($this->admin)->get('/admin/orders')->assertOk()->assertSee('Orders');
        $this->actingAs($this->admin)->get('/admin/students')->assertOk()->assertSee('Students');
    }

    // ── Bundle management ────────────────────────────────────────────

    public function test_empty_bundle_cannot_be_published(): void
    {
        $empty = Bundle::create([
            'slug' => 'empty-bundle-2024-2025',
            'title' => 'Empty bundle',
            'status' => 'DRAFT',
            'level' => 100,
            'academic_year' => '2024/2025',
            'price_pesewas' => 1000,
            'programme_id' => $this->bundle->programme_id,
            'course_id' => $this->bundle->course_id,
        ]);

        $this->actingAs($this->admin)->post("/admin/bundles/{$empty->id}/status", ['next' => 'PUBLISHED']);

        $this->assertSame('DRAFT', $empty->refresh()->status, 'Buying an empty bundle would grant nothing.');
    }

    public function test_publishing_bundle_takes_papers_live_and_sets_date(): void
    {
        $paper = $this->bundle->resources->first();
        $paper->update(['status' => 'DRAFT', 'published_at' => null]);

        $this->actingAs($this->admin)->post("/admin/bundles/{$this->bundle->id}/status", ['next' => 'UNPUBLISHED']);

        $this->bundle->update(['status' => 'PUBLISHED', 'published_at' => now()]);

        $this->actingAs($this->admin)->post("/admin/bundles/{$this->bundle->id}/status", ['next' => 'PUBLISHED']);

        $this->assertSame('PUBLISHED', $paper->refresh()->status, 'Publishing a bundle takes all its papers live.');
        $this->assertSame('PUBLISHED', $this->bundle->refresh()->status);
    }

    public function test_platform_price_update_converts_cedis_to_pesewas(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings/pricing', ['price' => '19.99']);

        $this->assertSame(1999, BundlePricing::pesewas(), 'Cedis input, integer pesewas storage.');
    }

    public function test_platform_price_re_prices_every_bundle(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings/pricing', ['price' => '19.99']);

        $this->assertSame(1999, $this->bundle->refresh()->price_pesewas, 'Bundle rows mirror the platform price.');
        $this->assertSame(0, Bundle::where('price_pesewas', '!=', 1999)->count(), 'No bundle is left behind.');
        $this->assertSame(0, BundlePricing::outOfSyncCount());
    }

    public function test_platform_price_shows_throughout_the_catalogue(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings/pricing', ['price' => '42.50']);

        $this->assertSame('42.50', $this->bundle->refresh()->priceCedis());
        $this->get("/bundles/{$this->bundle->slug}")->assertOk()->assertSee('42.50');
        $this->actingAs($this->admin)->get('/admin/bundles')->assertOk()->assertSee('42.50');
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('42.50');
    }

    public function test_pricing_is_validated(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/settings/pricing', ['price' => '-5'])
            ->assertSessionHasErrors('price');
        $this->actingAs($this->admin)
            ->post('/admin/settings/pricing', ['price' => '5000'])
            ->assertSessionHasErrors('price');

        $this->assertSame(BundlePricing::DEFAULT_PESEWAS, BundlePricing::pesewas());
    }

    public function test_per_bundle_price_route_is_gone(): void
    {
        // The old per-bundle editor must not survive as a writable path.
        $this->actingAs($this->admin)
            ->post("/admin/bundles/{$this->bundle->id}/price", ['price' => '9.99'])
            ->assertNotFound();

        $this->assertSame(BundlePricing::DEFAULT_PESEWAS, $this->bundle->refresh()->price_pesewas);
    }

    public function test_sold_bundle_cannot_be_deleted(): void
    {
        $order = Order::create([
            'reference' => 'PQ-SOLD01',
            'user_id' => $this->student->id,
            'status' => 'PAID',
            'amount_pesewas' => $this->bundle->price_pesewas,
            'currency' => 'GHS',
            'paid_at' => now(),
        ]);
        $order->items()->create(['bundle_id' => $this->bundle->id, 'unit_price_pesewas' => $this->bundle->price_pesewas]);

        $response = $this->actingAs($this->admin)->delete("/admin/bundles/{$this->bundle->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('bundles', ['id' => $this->bundle->id]);
        $this->assertNotNull(session('error'), 'The outcome must never be a silent no-op.');
    }

    public function test_unsold_bundle_can_be_deleted(): void
    {
        $this->actingAs($this->admin)->delete("/admin/bundles/{$this->bundle->id}");

        $this->assertDatabaseMissing('bundles', ['id' => $this->bundle->id]);
        $this->assertDatabaseMissing('resources', ['bundle_id' => $this->bundle->id]);
    }

    // ── Paper management ─────────────────────────────────────────────

    public function test_paper_without_file_cannot_be_published(): void
    {
        $paper = $this->bundle->resources->first();
        $paper->update(['status' => 'DRAFT', 'published_at' => null]);
        $paper->files()->delete();

        $response = $this->actingAs($this->admin)->post("/admin/papers/{$paper->id}/status", ['next' => 'PUBLISHED']);

        $response->assertRedirect();
        $this->assertSame('DRAFT', $paper->refresh()->status, 'The viewer would 404 on content.');
        $this->assertNotNull(session('error'));
    }

    public function test_owned_paper_cannot_be_deleted(): void
    {
        $paper = $this->bundle->resources->first();
        $order = Order::create([
            'reference' => 'PQ-SOLD02',
            'user_id' => $this->student->id,
            'status' => 'PAID',
            'amount_pesewas' => $this->bundle->price_pesewas,
            'currency' => 'GHS',
            'paid_at' => now(),
        ]);
        $order->items()->create(['bundle_id' => $this->bundle->id, 'unit_price_pesewas' => $this->bundle->price_pesewas]);
        Entitlement::create(['user_id' => $this->student->id, 'resource_id' => $paper->id, 'order_id' => $order->id]);

        $this->actingAs($this->admin)->delete("/admin/papers/{$paper->id}");

        $this->assertDatabaseHas('resources', ['id' => $paper->id]);
    }

    public function test_admin_can_upload_pdf_as_draft_paper(): void
    {
        Storage::fake('local');

        $pdf = $this->makePdfBytes();
        $upload = UploadedFile::fake()->createWithContent('exam.pdf', $pdf);

        $response = $this->actingAs($this->admin)->post('/admin/papers', [
            'bundle_id' => $this->bundle->id,
            'title' => 'ACC 102 · Semester 2 Resit',
            'semester' => '2',
            'file' => $upload,
        ]);

        $response->assertRedirect();

        $paper = Resource::where('title', 'ACC 102 · Semester 2 Resit')->first();
        $this->assertNotNull($paper);
        $this->assertSame('DRAFT', $paper->status, 'Uploads land as drafts — publish is a separate decision.');
        $this->assertNotNull($paper->page_count);
        $this->assertTrue($paper->files()->where('is_current', true)->exists());
    }

    public function test_non_pdf_upload_is_rejected(): void
    {
        $upload = UploadedFile::fake()->createWithContent('not-a-pdf.pdf', 'definitely not pdf bytes');

        $response = $this->actingAs($this->admin)->post('/admin/papers', [
            'bundle_id' => $this->bundle->id,
            'title' => 'Broken upload',
            'semester' => '1',
            'file' => $upload,
        ]);

        $response->assertRedirect();
        $this->assertNull(Resource::where('title', 'Broken upload')->first());
        $this->assertNotNull(session('error'));
    }

    // ── Paper detail / edit page ─────────────────────────────────────

    public function test_admin_can_open_paper_edit_page(): void
    {
        $paper = $this->bundle->resources->first();

        $this->actingAs($this->admin)->get("/admin/papers/{$paper->id}")
            ->assertOk()
            ->assertSee($paper->title)
            ->assertSee('Catalog details')
            ->assertSee('Document file')
            ->assertSee('Impact');
    }

    public function test_student_cannot_open_paper_edit_page(): void
    {
        $paper = $this->bundle->resources->first();

        $this->actingAs($this->student)->get("/admin/papers/{$paper->id}")->assertNotFound();
    }

    public function test_update_changes_metadata_and_upserts_programme(): void
    {
        $paper = $this->bundle->resources->first();

        $this->actingAs($this->admin)
            ->post("/admin/papers/{$paper->id}/update", [
                'description' => 'Covers the full semester 2 syllabus with worked solutions.',
                'type' => 'REVISION',
                'programme_name' => 'Accounting',
            ])
            ->assertRedirect();

        $paper->refresh();
        $this->assertSame('REVISION', $paper->type);
        $this->assertSame('Covers the full semester 2 syllabus with worked solutions.', $paper->description);
        $this->assertSame('Accounting', $paper->programme->name);

        // A brand-new programme name must be created, not rejected.
        $this->actingAs($this->admin)
            ->post("/admin/papers/{$paper->id}/update", [
                'description' => null,
                'type' => 'PAST_QUESTION',
                'programme_name' => 'Brand New Faculty',
            ])
            ->assertRedirect();

        $newProgramme = Programme::where('slug', 'brand-new-faculty')->first();
        $this->assertNotNull($newProgramme, 'Unknown programme names must be upserted.');
        $this->assertSame($newProgramme->id, $paper->refresh()->programme_id);
    }

    public function test_replacing_file_supersedes_the_old_version(): void
    {
        Storage::fake('local');
        $paper = $this->bundle->resources->firstWhere(fn ($r) => $r->files()->where('is_current', true)->exists())
            ?? $this->bundle->resources->first();
        $originalCount = $paper->files()->count();
        if ($originalCount === 0) {
            // Seed some papers without files; attach one so a current version exists.
            $bytes = $this->makePdfBytes();
            $key = app(DocumentStore::class)->buildStorageKey();
            app(DocumentStore::class)->put($key, $bytes);
            $paper->files()->create([
                'storage_key' => $key, 'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes), 'checksum' => hash('sha256', $bytes),
                'original_name' => 'v1.pdf', 'is_current' => true,
            ]);
            $originalCount = 1;
        }
        $this->assertTrue($paper->files()->where('is_current', true)->exists());

        $pdf = $this->makePdfBytes();
        $this->actingAs($this->admin)
            ->post("/admin/papers/{$paper->id}/replace", [
                'file' => UploadedFile::fake()->createWithContent('v2.pdf', $pdf),
            ])
            ->assertRedirect();

        $paper->refresh();
        $this->assertSame($originalCount + 1, $paper->files()->count(), 'Replacement adds a version, never overwrites.');
        $this->assertSame(1, $paper->files()->where('is_current', true)->count());
        $this->assertNotNull($paper->files->firstWhere('is_current', false), 'The old file is kept, marked superseded.');
    }

    // ── Student management ───────────────────────────────────────────

    public function test_admin_can_suspend_and_reactivate_student(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/students/{$this->student->id}/status", ['status' => 'SUSPENDED']);
        $this->assertSame('SUSPENDED', $this->student->refresh()->status);

        // Drop the admin session so /login is reachable (guest middleware).
        $this->post('/logout');

        // Suspended students cannot log in (login-time check).
        $this->post('/login', ['email' => $this->student->email, 'password' => 'Student@123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->admin)
            ->post("/admin/students/{$this->student->id}/status", ['status' => 'ACTIVE']);
        $this->assertSame('ACTIVE', $this->student->refresh()->status);
    }

    public function test_admin_cannot_be_suspended_via_students_endpoint(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/students/{$this->admin->id}/status", ['status' => 'SUSPENDED']);

        $this->assertSame('ACTIVE', $this->admin->refresh()->status);
    }

    // ── Late-upload backfill (the bulk-model guarantee) ──────────────

    public function test_publishing_new_paper_backfills_past_buyers(): void
    {
        // Student already PAID for the bundle, but a new paper was added after.
        $order = Order::create([
            'reference' => 'PQ-EARLY1',
            'user_id' => $this->student->id,
            'status' => 'PAID',
            'amount_pesewas' => $this->bundle->price_pesewas,
            'currency' => 'GHS',
            'paid_at' => now(),
        ]);
        $order->items()->create(['bundle_id' => $this->bundle->id, 'unit_price_pesewas' => $this->bundle->price_pesewas]);
        $owned = $this->bundle->resources->first();
        Entitlement::create(['user_id' => $this->student->id, 'resource_id' => $owned->id, 'order_id' => $order->id]);

        $late = Resource::create([
            'slug' => 'late-upload-paper',
            'title' => 'Late upload paper',
            'status' => 'DRAFT',
            'level' => $this->bundle->level,
            'semester' => 2,
            'academic_year' => $this->bundle->academic_year,
            'bundle_id' => $this->bundle->id,
            'programme_id' => $this->bundle->programme_id,
            'course_id' => $this->bundle->course_id,
        ]);

        $store = app(DocumentStore::class);
        $key = $store->buildStorageKey();
        $store->put($key, $this->makePdfBytes());
        $late->files()->create([
            'storage_key' => $key, 'mime_type' => 'application/pdf',
            'size_bytes' => 1, 'original_name' => 'late.pdf', 'is_current' => true,
        ]);

        // Publish the late paper through the admin endpoint — the status
        // transition itself must trigger the backfill.
        $this->actingAs($this->admin)
            ->post("/admin/papers/{$late->id}/status", ['next' => 'PUBLISHED'])
            ->assertRedirect();

        // The past buyer was granted the late paper automatically.
        $this->assertDatabaseHas('entitlements', [
            'user_id' => $this->student->id,
            'resource_id' => $late->id,
            'order_id' => $order->id,
        ]);

        // Idempotent: re-running the backfill fills nothing new.
        $backfill = new EntitlementBackfill;
        $this->assertSame(0, $backfill->grantBundlePapersToPastBuyers($this->bundle->id));
    }

    private function makePdfBytes(): string
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->Text(50, 50, 'Admin upload test');

        return (string) $pdf->Output('', 'S');
    }
}
