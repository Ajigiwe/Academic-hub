<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCatalog(): void
    {
        $this->seed(\Database\Seeders\PastqSeeder::class);
    }

    public function test_home_is_the_hero_with_two_intent_doors(): void
    {
        $this->seedCatalog();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Find the Past Questions');
        $response->assertSee('Past questions');
        $response->assertSee('Course materials');
    }

    public function test_courses_page_lists_published_bundles(): void
    {
        $this->seedCatalog();

        // Guided browse flow: all three steps committed → library opens.
        $response = $this->get('/courses?programme=accounting&level=200&semester=2');

        $response->assertOk();
        $response->assertSee('Financial Reporting');
    }

    public function test_courses_flow_steps_progressively_narrow(): void
    {
        $this->seedCatalog();

        // Step 1: programme cards only.
        $step1 = $this->get('/courses');
        $step1->assertOk();
        $step1->assertSee('Procurement');
        $step1->assertDontSee('Financial Reporting');

        // Step 2: levels for the chosen programme (big number + year label).
        $step2 = $this->get('/courses?programme=accounting');
        $step2->assertOk();
        $step2->assertSee('First year');
        $step2->assertDontSee('Financial Reporting');

        // Step 3: semesters for the chosen level.
        $step3 = $this->get('/courses?programme=accounting&level=200');
        $step3->assertOk();
        $step3->assertSee('Second Semester');
        $step3->assertDontSee('Financial Reporting');
    }

    public function test_level_cards_report_their_content_counts(): void
    {
        $this->seedCatalog();

        // Accounting holds ACC 102 + its free lecture notes at level 100,
        // ACC 201 at level 200, and nothing at 300/400 — so the level
        // cards can say so before the visitor picks one.
        $response = $this->get('/courses?programme=accounting');

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '1 bundle · 1 free material'));
        $this->assertSame(2, substr_count($response->getContent(), 'Nothing here yet'));
    }

    public function test_semester_cards_count_bundles_by_the_semester_their_papers_belong_to(): void
    {
        $this->seedCatalog();

        // ACC 201 is a level-200 accounting bundle whose only paper is a
        // second-semester paper: the first-semester card must stay empty.
        $response = $this->get('/courses?programme=accounting&level=200');

        $response->assertOk();
        $response->assertSee('1 bundle');
        $this->assertSame(1, substr_count($response->getContent(), 'Nothing here yet'));
    }

    public function test_materials_level_cards_count_free_materials_only(): void
    {
        $this->seedCatalog();

        // The materials page must not advertise the level-200 accounting
        // bundle — nothing in it is downloadable from that page.
        $response = $this->get('/materials?programme=accounting');

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '1 free material'));
        $response->assertDontSee('1 bundle');
    }

    public function test_course_search_filters_by_code_and_title(): void
    {
        $this->seedCatalog();

        // Guided: procurement programme, level 200, semester 1 → PSM 201.
        $response = $this->get('/courses?programme=procurement-and-supply&level=200&semester=1');

        $response->assertOk();
        $response->assertSee('PSM 201');
        $response->assertDontSee('Financial Accounting');
    }

    public function test_bundle_detail_shows_price_and_year(): void
    {
        $this->seedCatalog();

        $response = $this->get('/bundles/acc-102-introduction-to-financial-accounting-2024-2025');

        $response->assertOk();
        $response->assertSee('2024/2025');
        $response->assertSee('GHS 15.00');
    }

    public function test_unknown_bundle_404s(): void
    {
        $this->seedCatalog();

        $this->get('/bundles/does-not-exist')->assertNotFound();
    }
}
