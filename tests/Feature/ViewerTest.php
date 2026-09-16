<?php

namespace Tests\Feature;

use App\Models\{Bundle, Entitlement, Order, Payment, Resource, ResourceFile, User, ViewingSession};
use App\Services\Documents\{DocumentStore, PdfWatermarker};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $other;

    private Resource $paper;

    private ResourceFile $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);

        $this->student = User::where('email', 'student@pastq.test')->first();
        $this->other = User::where('email', 'admin@pastq.test')->first(); // replaced below where needed

        $this->paper = Resource::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025-sem1-exam')->first();

        // Attach a real (minimal, watermarker-parseable) PDF to the paper.
        $bytes = $this->makePdf();
        $store = app(DocumentStore::class);
        $key = $store->buildStorageKey();
        $store->put($key, $bytes);
        $this->file = $this->paper->files()->create([
            'storage_key' => $key,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'original_name' => 'test.pdf',
            'is_current' => true,
        ]);
    }

    // ── The authorization chain ──────────────────────────────────────

    public function test_guest_cannot_open_viewer(): void
    {
        $this->get('/view/'.$this->paper->slug)->assertRedirect('/login');
    }

    public function test_unknown_resource_404s(): void
    {
        $this->actingAs($this->student)->get('/view/does-not-exist')->assertNotFound();
    }

    public function test_unentitled_student_is_denied_and_logged(): void
    {
        $response = $this->actingAs($this->student)->get('/view/'.$this->paper->slug);

        $response->assertForbidden();
        $this->assertDatabaseHas('access_logs', [
            'user_id' => $this->student->id,
            'action' => 'ACCESS_DENIED',
        ]);
    }

    public function test_entitled_student_gets_viewer_page_and_session(): void
    {
        $this->entitleStudent();

        $response = $this->actingAs($this->student)->get('/view/'.$this->paper->slug);

        $response->assertOk();
        $response->assertSee('Secure viewer');
        $this->assertDatabaseHas('viewing_sessions', ['user_id' => $this->student->id, 'resource_id' => $this->paper->id]);
        $this->assertDatabaseHas('access_logs', ['user_id' => $this->student->id, 'action' => 'STARTED_VIEWER']);
        $this->assertDatabaseHas('recently_viewed', ['user_id' => $this->student->id, 'resource_id' => $this->paper->id]);
    }

    public function test_admin_has_role_granted_access_without_entitlement(): void
    {
        $admin = User::where('email', 'admin@pastq.test')->first();

        $response = $this->actingAs($admin)->get('/view/'.$this->paper->slug);

        $response->assertOk();
        // Admins legitimately have no purchase row.
        $session = ViewingSession::where('user_id', $admin->id)->sole();
        $this->assertNull($session->entitlement_id);
    }

    // ── In-page reading, and the ways in when it cannot run ──────────

    public function test_the_reader_renders_its_own_pages_and_keeps_a_way_in(): void
    {
        $this->entitleStudent();

        $response = $this->actingAs($this->student)->get('/view/'.$this->paper->slug);

        $response->assertOk();

        // The canvas reader is configured server-side: signed document,
        // a renewal endpoint, and the vendored PDF.js worker. No browser
        // PDF viewer is involved, so no native download menu either.
        $response->assertSee('id="pdf-reader"', false);
        $response->assertSee('data-document="', false);
        $response->assertSee('data-refresh="'.route('viewer.document-url', $this->paper->slug).'"', false);
        $response->assertSee('vendor/pdfjs/pdf.min.js', false);
        $response->assertSee('js/pdf-reader.js', false);
        $response->assertDontSee('<iframe', false);

        // And if none of that can run, the paper is still one click away.
        $response->assertSee('id="pdf-fallback"', false);
        $response->assertSee('Open the document');
        $response->assertSee(route('viewer.open', $this->paper->slug), false);
    }

    public function test_reading_takes_the_whole_screen(): void
    {
        $this->entitleStudent();

        $response = $this->actingAs($this->student)->get('/view/'.$this->paper->slug);

        $response->assertOk();

        // The reader opts out of the site chrome, so the paper owns the
        // viewport instead of sitting between a header and a nav bar.
        $response->assertSee('is-reader', false);
        $response->assertSee('reader-shell', false);
        $response->assertDontSee('id="site-header"', false);
        $response->assertDontSee('id="mobile-nav"', false);

        // …and where the browser can go truly full screen, so can they.
        $response->assertSee('id="pdf-fullscreen"', false);

        // Every other page keeps its chrome.
        $this->actingAs($this->student)->get('/library')
            ->assertOk()
            ->assertSee('id="site-header"', false)
            ->assertSee('id="mobile-nav"', false);
    }

    public function test_the_reader_can_renew_its_own_link(): void
    {
        $this->get('/view/'.$this->paper->slug.'/document-url')
            ->assertRedirect('/login');

        $this->actingAs($this->student)
            ->getJson('/view/'.$this->paper->slug.'/document-url')
            ->assertForbidden();

        $this->entitleStudent();

        $response = $this->actingAs($this->student)->getJson('/view/'.$this->paper->slug.'/document-url');

        $response->assertOk();
        $response->assertJsonStructure(['url', 'expires_in']);

        $url = $response->json('url');
        $this->assertStringContainsString('signature=', $url);

        // The renewed link serves the watermarked paper itself.
        $document = $this->actingAs($this->student)->get($url);
        $document->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $document->getContent());
    }

    public function test_the_open_action_re_builds_the_authorization_chain(): void
    {
        // Guest: bounced to sign in, like the reader itself.
        $this->get('/view/'.$this->paper->slug.'/document')->assertRedirect('/login');

        // Without an entitlement: denied and logged, exactly as the page is.
        $this->actingAs($this->student)
            ->get('/view/'.$this->paper->slug.'/document')
            ->assertForbidden();
        $this->assertDatabaseHas('access_logs', [
            'user_id' => $this->student->id,
            'action' => 'ACCESS_DENIED',
        ]);

        // Entitled: a fresh signed URL for a fresh session.
        $this->entitleStudent();

        $response = $this->actingAs($this->student)->get('/view/'.$this->paper->slug.'/document');
        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('signature=', $location);

        $session = ViewingSession::where('user_id', $this->student->id)->latest('id')->first();
        $this->assertStringContainsString('/'.$session->id, $location);

        // And that URL really serves the watermarked paper.
        $document = $this->actingAs($this->student)->get($location);
        $document->assertOk();
        $this->assertSame('application/pdf', $document->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $document->getContent());
        $this->assertDatabaseHas('access_logs', [
            'user_id' => $this->student->id,
            'action' => 'STARTED_VIEWER',
        ]);
    }

    // ── Signed document URL security ─────────────────────────────────

    public function test_document_streams_watermarked_pdf_with_live_session(): void
    {
        $this->entitleStudent();
        $session = $this->openSession();

        $url = $this->signedUrl($session);
        $response = $this->actingAs($this->student)->get($url);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // The body must be a parseable PDF whose page count matches the
        // source document (the watermark does not add or remove pages).
        $body = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertSame(
            app(PdfWatermarker::class)->pageCount($this->file->storage_key ? app(DocumentStore::class)->get($this->file->storage_key) : ''),
            app(PdfWatermarker::class)->pageCount($body),
        );
    }

    public function test_document_without_signature_is_rejected(): void
    {
        $this->entitleStudent();
        $session = $this->openSession();

        $unsigned = '/viewer-document/'.$this->paper->id.'/'.$session->id;

        $this->actingAs($this->student)->get($unsigned)->assertForbidden();
    }

    public function test_document_url_for_another_users_session_is_denied(): void
    {
        $this->entitleStudent();
        $session = $this->openSession();

        // Another entitled user must not use the student's signed URL.
        $intruder = User::create([
            'name' => 'Intruder', 'first_name' => 'In', 'last_name' => 'Truder',
            'email' => 'intruder@pastq.test', 'password' => 'Password123',
            'role' => 'STUDENT', 'status' => 'ACTIVE',
        ]);
        $this->entitle($intruder);

        $url = $this->signedUrl($session);
        $this->actingAs($intruder)->get($url)->assertForbidden();
    }

    public function test_document_url_after_session_expiry_is_428(): void
    {
        $this->entitleStudent();
        $session = $this->openSession();
        $session->update(['started_at' => now()->subMinutes(11)]);

        $url = $this->signedUrl($session);
        $this->actingAs($this->student)->get($url)->assertStatus(428);
    }

    public function test_document_after_losing_entitlement_is_denied(): void
    {
        $this->entitleStudent();
        $session = $this->openSession();
        $url = $this->signedUrl($session);

        Entitlement::where('user_id', $this->student->id)->delete();

        $this->actingAs($this->student)->get($url)->assertForbidden();
    }

    // ── Watermark correctness ─────────────────────────────────────────

    public function test_watermarker_stamps_identity_on_every_page(): void
    {
        $watermarker = app(PdfWatermarker::class);
        $bytes = $this->makePdf(3);

        $out = $watermarker->watermark($bytes, [
            'name' => 'Ama Mensah',
            'account_id' => 'ABC123',
            'order_ref' => 'PQ-TEST01',
        ]);

        $this->assertNotSame($bytes, $out);
        $this->assertSame(3, $watermarker->pageCount($out));
    }

    public function test_watermarker_rejects_non_pdf_bytes_gracefully(): void
    {
        $watermarker = app(PdfWatermarker::class);

        $this->assertSame(0, $watermarker->pageCount('this is not a pdf'));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function entitleStudent(): void
    {
        $bundle = Bundle::where('id', $this->paper->bundle_id)->first();
        $order = Order::create([
            'reference' => 'PQ-OWNED1',
            'user_id' => $this->student->id,
            'status' => 'PAID',
            'amount_pesewas' => $bundle->price_pesewas,
            'currency' => 'GHS',
            'paid_at' => now(),
        ]);
        $order->items()->create([
            'bundle_id' => $bundle->id,
            'unit_price_pesewas' => $bundle->price_pesewas,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'mock',
            'provider_ref' => 'mock_PQ-OWNED1',
            'reference' => $order->reference,
            'channel' => 'MOCK',
            'amount_pesewas' => $order->amount_pesewas,
            'currency' => 'GHS',
        ]);
        Entitlement::create([
            'user_id' => $this->student->id,
            'resource_id' => $this->paper->id,
            'order_id' => $order->id,
        ]);
    }

    private function entitle(User $user): void
    {
        Entitlement::create([
            'user_id' => $user->id,
            'resource_id' => $this->paper->id,
            'order_id' => Order::where('user_id', $this->student->id)->first()->id,
        ]);
    }

    private function openSession(): ViewingSession
    {
        return ViewingSession::create([
            'user_id' => $this->student->id,
            'resource_id' => $this->paper->id,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function signedUrl(ViewingSession $session): string
    {
        return URL::temporarySignedRoute(
            'viewer.document',
            now()->addSeconds(config('viewer.url_ttl_seconds')),
            ['resource' => $this->paper->id, 'session' => $session->id],
        );
    }

    private function makePdf(int $pages = 1): string
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 12);
            $pdf->Text(50, 50, "Test page {$i}");
        }

        return (string) $pdf->Output('', 'S');
    }
}
