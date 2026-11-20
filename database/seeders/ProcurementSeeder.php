<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\Course;
use App\Models\Programme;
use App\Models\Resource;
use App\Models\ResourceFile;
use App\Services\BundlePricing;
use App\Services\Documents\DocumentStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $manifestPath = __DIR__ . '/data/procurement/manifest.json';
        if (!file_exists($manifestPath)) {
            $this->command?->error("Manifest not found at {$manifestPath}");
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            $this->command?->error("Invalid manifest JSON");
            return;
        }

        // 1. Programme: Procurement and Supply
        $programme = Programme::firstOrCreate(
            ['slug' => 'procurement-and-supply'],
            ['name' => 'Procurement and Supply']
        );

        $pricePesewas = BundlePricing::pesewas();
        $store = app(DocumentStore::class);

        // Group papers by course_code so courses with multiple papers share one bundle
        $courseBundles = [];

        foreach ($manifest as $item) {
            $code = $item['course_code'];

            if (!isset($courseBundles[$code])) {
                $course = Course::firstOrCreate(
                    ['code' => $code],
                    [
                        'title' => $item['course_title'],
                        'slug' => Str::slug("{$code} {$item['course_title']}"),
                    ]
                );

                $bundleTitle = "{$code} — {$item['course_title']} · Past Questions";
                $bundleSlug = Str::slug("{$code}-past-questions");

                $bundle = Bundle::updateOrCreate(
                    ['slug' => $bundleSlug],
                    [
                        'title' => $bundleTitle,
                        'description' => "Complete past questions and examination papers for {$item['course_title']} ({$code}) under the Department of Procurement and Supply.",
                        'status' => 'PUBLISHED',
                        'level' => $item['level'],
                        'academic_year' => $item['academic_year'],
                        'price_pesewas' => $pricePesewas,
                        'published_at' => now(),
                        'programme_id' => $programme->id,
                        'course_id' => $course->id,
                    ]
                );

                $courseBundles[$code] = [
                    'course' => $course,
                    'bundle' => $bundle,
                ];
            }

            $course = $courseBundles[$code]['course'];
            $bundle = $courseBundles[$code]['bundle'];

            $pdfPath = __DIR__ . '/data/procurement/' . $item['file_name'];
            if (!file_exists($pdfPath)) {
                $this->command?->warn("PDF missing: {$pdfPath}");
                continue;
            }

            $pdfBytes = file_get_contents($pdfPath);
            $pageCount = (int) $item['page_count'];
            $previewPages = min(2, $pageCount);

            $resource = Resource::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'title' => $item['title'],
                    'description' => "Exam paper for {$item['course_title']} ({$code}).",
                    'type' => 'PAST_QUESTION',
                    'status' => 'PUBLISHED',
                    'level' => $item['level'],
                    'semester' => (int) $item['semester'],
                    'academic_year' => $item['academic_year'],
                    'page_count' => $pageCount,
                    'preview_pages' => $previewPages,
                    'published_at' => now(),
                    'bundle_id' => $bundle->id,
                    'programme_id' => $programme->id,
                    'course_id' => $course->id,
                ]
            );

            // Store document in DocumentStore if not already present
            $existingFile = ResourceFile::where('resource_id', $resource->id)
                ->where('is_current', true)
                ->first();

            if (!$existingFile || !$store->exists($existingFile->storage_key)) {
                $storageKey = $store->buildStorageKey();
                $store->put($storageKey, $pdfBytes);

                ResourceFile::where('resource_id', $resource->id)->update(['is_current' => false]);

                ResourceFile::create([
                    'resource_id' => $resource->id,
                    'storage_key' => $storageKey,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($pdfBytes),
                    'checksum' => hash('sha256', $pdfBytes),
                    'original_name' => $item['file_name'],
                    'is_current' => true,
                ]);
            }
        }

        $this->command?->info("Procurement past questions seeded successfully (" . count($manifest) . " papers across " . count($courseBundles) . " bundles).");
    }
}
