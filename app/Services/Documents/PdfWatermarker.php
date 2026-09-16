<?php

namespace App\Services\Documents;

use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Per-user PDF watermarking — the FPDI import + TCPDF stamp equivalent
 * of the Next.js burnWatermark() compositor (spec §13/§15): the
 * requesting user's identity is burned into the document itself, not a
 * DOM overlay a user could remove.
 *
 * Marks on every page:
 *   1. the original page content (imported with FPDI)
 *   2. staggered diagonal identity tiles (name · id · order)
 *   3. a footer line with the licensee and order reference
 *
 * Output is a NEW pdf; the original bytes are never modified.
 *
 * Coordinates are in points with Y measured from the top (TCPDF
 * convention, matching the canvas renderer's Y-down math). Identity
 * strings are drawn with a UTF-8 font (dejavusans) so names with
 * Ghanaian characters (ɔ, ɛ, …) render correctly.
 */
class PdfWatermarker
{
    /** Marks for public previews — clearly not a licensed copy. */
    public const PREVIEW_IDENTITY = [
        'name' => 'PREVIEW',
        'account_id' => 'SAMPLE',
        'order_ref' => 'Buy the bundle to unlock all pages',
    ];

    /**
     * @param array{name: string, account_id: string, order_ref: string} $identity
     */
    public function watermark(string $pdfBytes, array $identity): string
    {
        $pdf = new Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false); // footer/tiles must never spill a new page
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pageCount = $pdf->setSourceFile(StreamReader::createByString($pdfBytes));

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $template = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($template);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

            // 1. The original page, exactly as-is.
            $pdf->useTemplate($template);

            // 2 + 3. Identity burned into the same content stream.
            $this->burnWatermark($pdf, (float) $size['width'], (float) $size['height'], $identity);
        }

        return (string) $pdf->Output('', 'S');
    }

    /** Page count probe; unparseable bytes return 0 (caller 422s). */
    public function pageCount(string $pdfBytes): int
    {
        try {
            $pdf = new Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);

            return $pdf->setSourceFile(StreamReader::createByString($pdfBytes));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param array{name: string, account_id: string, order_ref: string} $identity
     */
    private function burnWatermark(Fpdi $pdf, float $w, float $h, array $identity): void
    {
        $text = "{$identity['name']} · {$identity['account_id']} · {$identity['order_ref']}";

        $pdf->SetFont('dejavusans', 'B', 10);
        $textWidth = (float) $pdf->GetStringWidth($text);

        $cellW = max($textWidth * 1.25, $w * 0.45);
        $cellH = $cellW * 0.45;
        $angle = -32.0;
        $cols = (int) ceil($w / $cellW) + 1;
        $rows = (int) ceil($h / $cellH) + 1;

        // Staggered diagonal identity tiles.
        $pdf->SetAlpha(0.08);
        $pdf->SetTextColor(13, 51, 36); // #0d3324
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $cx = $c * $cellW + ($r % 2 === 0 ? 0 : $cellW / 2);
                $cy = $r * $cellH + $cellH / 2;

                $pdf->StartTransform();
                $pdf->Rotate($angle, $cx, $cy);
                $pdf->Text($cx, $cy, $text);
                $pdf->StopTransform();
            }
        }

        // Footer license line.
        $pdf->SetAlpha(0.65);
        $pdf->SetTextColor(75, 85, 83); // #4b5553
        $pdf->SetFont('dejavusans', 'B', 7);
        $footer = "Licensed to {$identity['name']} ({$identity['account_id']})"
            ." · Order {$identity['order_ref']} · Academic Resource Hub";
        $pdf->Text($w / 2 - $pdf->GetStringWidth($footer) / 2, $h - 5, $footer);
        $pdf->SetAlpha(1);
    }
}
