<?php

namespace App\Console\Commands;

use App\Models\Resource;
use App\Services\Documents\DocumentStore;
use Illuminate\Console\Command;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Dev/demo utility: every seeded paper currently has no file behind it
 * ("No file attached"), exactly like the Next.js seed's placeholder
 * bundles. This attaches a small generated PDF to every paper so the
 * viewer can be exercised end-to-end (mirrors scripts/prepare-demo.ts).
 */
class GenerateDemoPdfs extends Command
{
    protected $signature = 'demo:generate-pdfs {--force : Regenerate even if a file is already attached}';

    protected $description = 'Attach a generated demo PDF to every seeded paper';

    public function handle(DocumentStore $store): int
    {
        // All resources — bundle papers AND free materials — so the viewer
        // and the public download route can both be exercised end-to-end.
        foreach (Resource::cursor() as $resource) {
            if (! $this->option('force') && $resource->files()->where('is_current', true)->exists()) {
                $this->line("  skip (has file): {$resource->slug}");

                continue;
            }

            $pdf = new Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $pages = max(1, (int) ($resource->page_count ? ceil($resource->page_count / 10) : 2));
            for ($page = 1; $page <= $pages; $page++) {
                $pdf->AddPage();
                $pdf->SetFont('dejavusans', 'B', 18);
                $pdf->SetY(80);
                $pdf->Cell(0, 24, $resource->title, 0, 1, 'C');
                $pdf->SetFont('dejavusans', '', 12);
                $pdf->Cell(0, 20, "Demo ".strtolower(str_replace('_', ' ', $resource->type))." — page {$page} of {$pages}", 0, 1, 'C');
                $pdf->Ln(10);
                $pdf->Cell(0, 20, $resource->course->code.' · '.$resource->academic_year, 0, 1, 'C');
            }

            $bytes = (string) $pdf->Output('', 'S');
            $key = $store->buildStorageKey();
            $store->put($key, $bytes);

            // Supersede any previous current file, then attach the new one.
            $resource->files()->update(['is_current' => false]);
            $resource->files()->create([
                'storage_key' => $key,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'original_name' => $resource->slug.'.pdf',
                'is_current' => true,
            ]);

            $this->info("  attached: {$resource->slug} ({$pages} pages)");
        }

        $this->components->info('Demo PDFs generated.');

        return self::SUCCESS;
    }
}
