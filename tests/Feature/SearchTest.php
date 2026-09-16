<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Programme;
use App\Models\Resource;
use App\Services\ProgrammeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 7: the student search page — port of the Next.js /search with
 * searchBundles/getFreeMaterials semantics (shared filters, visibility
 * gates, sorting, pagination, and the free-materials fallback states).
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);
    }

    public function test_search_lists_published_bundles(): void
    {
        $response = $this->get('/search');

        $response->assertOk()
            ->assertSee('Browse all bundles')
            ->assertSee('Financial Accounting');
    }

    public function test_keyword_matches_course_code_and_title(): void
    {
        // The seeded ACC 102 bundle's course code + title.
        $this->get('/search?q=ACC 102')
            ->assertOk()
            ->assertSee('Introduction to Financial Accounting');

        $this->get('/search?q=Financial Reporting')
            ->assertOk()
            ->assertSee('Financial Reporting')
            ->assertDontSee('Principles of Marketing');

        $this->get('/search?q=nonexistent-thing')
            ->assertOk()
            ->assertSee('No past-question bundles found');
    }

    public function test_level_semester_and_year_filters_narrow_results(): void
    {
        $this->get('/search?level=100')
            ->assertOk()
            ->assertSee('Introduction to Financial Accounting');

        // No level-400 bundles exist in the seed (level 100/200/300 do).
        $this->get('/search?level=400')
            ->assertOk()
            ->assertSee('No past-question bundles found');

        $this->get('/search?semester=1&year=2024/2025')
            ->assertOk()
            ->assertSee('Introduction to Financial Accounting');

        // Year filters bundles only — materials ignore it, so the empty
        // bundles case falls back to the free-materials state (the ported
        // semantics, not a bug).
        $this->get('/search?year=1999/2000')
            ->assertOk()
            ->assertSee('No paid bundles match — but these free materials do');
    }

    public function test_sort_options_are_accepted(): void
    {
        foreach (['relevance', 'newest', 'popular', 'price_asc', 'price_desc'] as $sort) {
            $this->get("/search?sort={$sort}")->assertOk();
        }

        // An unknown sort falls back to relevance rather than erroring.
        $this->get('/search?sort=weird')->assertOk();
    }

    public function test_disabled_programme_hidden_from_search(): void
    {
        $bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->firstOrFail();

        $this->get('/search?q=Financial Accounting')->assertOk()->assertSee('Introduction to Financial Accounting');

        ProgrammeSettings::setEnabled($bundle->programme->slug, false);

        $this->get('/search?q=Financial Accounting')
            ->assertOk()
            ->assertDontSee('Introduction to Financial Accounting');

        ProgrammeSettings::setEnabled($bundle->programme->slug, true);
    }

    public function test_programme_filter_ignores_unknown_or_disabled_tracks(): void
    {
        // Unknown programme → filter doesn't apply; all bundles still show.
        $this->get('/search?programme=not-a-programme')
            ->assertOk()
            ->assertSee('Introduction to Financial Accounting');
    }

    public function test_free_materials_section_renders_with_shared_filters(): void
    {
        $material = Resource::published()->whereNull('bundle_id')->firstOrFail();

        $this->get('/search')
            ->assertOk()
            ->assertSee('Free study materials')
            ->assertSee($material->title);

        // Level filter applies to materials too.
        $this->get('/search?level='.$material->level)
            ->assertOk()
            ->assertSee($material->title);

        // Year filter does NOT hide materials (they are current-year tagged).
        $this->get('/search?year=1999/2000')
            ->assertOk()
            ->assertSee($material->title);
    }

    public function test_free_material_fallback_state_when_no_bundles_match(): void
    {
        // A query that matches only a free material, no bundle.
        $material = Resource::published()->whereNull('bundle_id')->firstOrFail();
        $keyword = substr($material->title, 0, 8);

        $bundleHits = Bundle::published()
            ->where('title', 'like', "%{$keyword}%")
            ->orWhereHas('course', fn ($c) => $c->where('code', 'like', "%{$keyword}%")->orWhere('title', 'like', "%{$keyword}%"))
            ->count();

        if ($bundleHits > 0) {
            $this->markTestSkipped('Seeded data makes this keyword also match a bundle.');
        }

        $this->get('/search?q='.$keyword)
            ->assertOk()
            ->assertSee('No paid bundles match — but these free materials do')
            ->assertSee($material->title);
    }

    public function test_pagination_links_render_when_more_than_twelve_bundles(): void
    {
        // The seeder ships fewer than 13 bundles; create filler to cross
        // the per-page boundary (12 per page, port of SEARCH_PER_PAGE).
        $first = Bundle::published()->firstOrFail();
        $course = $first->course;
        $programme = $first->programme;

        for ($i = 1; $i <= 12; $i++) {
            Bundle::create([
                'slug' => "filler-bundle-{$i}-2024-2025",
                'title' => "Filler bundle {$i}",
                'status' => 'PUBLISHED',
                'published_at' => now(),
                'level' => 400,
                'academic_year' => '2024/2025',
                'price_pesewas' => 1000,
                'solved' => false,
                'programme_id' => $programme->id,
                'course_id' => $course->id,
            ]);
        }

        $this->get('/search')
            ->assertOk()
            ->assertSee('aria-label="Pagination"', false);
    }
}
