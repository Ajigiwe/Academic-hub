<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

/**
 * Compiles uploaded files (PDFs, images, or multiple mixed files) into a
 * single high-resolution, watermarking-ready PDF document.
 */
class DocumentCompiler
{
    public const SUPPORTED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public const SUPPORTED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Standard A4 width in PostScript points (72 pt/inch).
     */
    private const A4_WIDTH_PT = 595.28;

    /**
     * Standard A4 height in PostScript points.
     */
    private const A4_HEIGHT_PT = 841.89;

    public function __construct(
        private readonly PdfWatermarker $probe = new PdfWatermarker(),
    ) {}

    /**
     * Compile one or more uploaded files into a single PDF byte string.
     *
     * @param  array<int, UploadedFile|string>|UploadedFile|string  $files
     * @return array{
     *     bytes: string,
     *     page_count: int,
     *     original_name: string
     * }
     *
     * @throws InvalidArgumentException
     */
    public function compile(array|UploadedFile|string $files): array
    {
        $normalized = is_array($files) ? array_values(array_filter($files)) : [$files];

        if (empty($normalized)) {
            throw new InvalidArgumentException('At least one file must be provided.');
        }

        // Fast path: if exactly one PDF was uploaded, keep its original bytes without re-encoding
        if (count($normalized) === 1 && $this->isPdf($normalized[0])) {
            $file = $normalized[0];
            $bytes = $this->readBytes($file);
            $pageCount = $this->probe->pageCount($bytes);

            if ($pageCount === 0) {
                throw new InvalidArgumentException('The uploaded PDF file is invalid or unreadable.');
            }

            return [
                'bytes' => $bytes,
                'page_count' => $pageCount,
                'original_name' => $this->resolveOriginalName($normalized),
            ];
        }

        // Multiple files or image files: compile into a crisp multi-page PDF
        $tempFiles = [];

        try {
            $pdf = new Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
            $pdf->SetMargins(0, 0, 0, true);
            $pdf->SetAutoPageBreak(false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            $totalPages = 0;

            foreach ($normalized as $file) {
                $path = $this->resolvePath($file);

                if ($this->isPdf($file)) {
                    $pageCount = $pdf->setSourceFile(StreamReader::createByFile($path));
                    for ($p = 1; $p <= $pageCount; $p++) {
                        $template = $pdf->importPage($p);
                        $size = $pdf->getTemplateSize($template);
                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($template);
                        $totalPages++;
                    }
                } else {
                    $processedPath = $this->prepareImageForEmbedding($path, $tempFiles);
                    $imgSize = @getimagesize($processedPath);

                    if (! $imgSize || empty($imgSize[0]) || empty($imgSize[1])) {
                        throw new InvalidArgumentException("Unable to read image dimensions for {$this->resolveFileName($file)}.");
                    }

                    $wPx = (float) $imgSize[0];
                    $hPx = (float) $imgSize[1];
                    $aspect = $hPx / $wPx;

                    // Size the page to match the image aspect ratio so it fills the page without distortion
                    if ($wPx > $hPx) {
                        $pageW = self::A4_HEIGHT_PT;
                        $pageH = round($pageW * $aspect, 2);
                        $orientation = 'L';
                    } else {
                        $pageW = self::A4_WIDTH_PT;
                        $pageH = round($pageW * $aspect, 2);
                        $orientation = 'P';
                    }

                    $pdf->AddPage($orientation, [$pageW, $pageH]);
                    // Embed at full resolution (300+ DPI target)
                    $pdf->Image($processedPath, 0, 0, $pageW, $pageH, '', '', '', false, 300, '', false, false, 0, false, false, false);
                    $totalPages++;
                }
            }

            if ($totalPages === 0) {
                throw new InvalidArgumentException('No readable pages could be extracted from the uploaded files.');
            }

            $outputBytes = (string) $pdf->Output('', 'S');
            $verifiedPageCount = $this->probe->pageCount($outputBytes);

            return [
                'bytes' => $outputBytes,
                'page_count' => $verifiedPageCount > 0 ? $verifiedPageCount : $totalPages,
                'original_name' => $this->resolveOriginalName($normalized),
            ];
        } finally {
            foreach ($tempFiles as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    /**
     * Prepare an image file for TCPDF: auto-orient JPEG using EXIF data, or
     * convert WebP to high-quality PNG.
     *
     * @param  list<string>  $tempFiles
     */
    private function prepareImageForEmbedding(string $path, array &$tempFiles): string
    {
        $info = @getimagesize($path);
        $mime = $info['mime'] ?? mime_content_type($path);

        // Convert WebP images to high-quality PNG for TCPDF compatibility
        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $gd = @imagecreatefromwebp($path);
            if ($gd) {
                $tmpPng = tempnam(sys_get_temp_dir(), 'ah_webp_').'.png';
                imagepng($gd, $tmpPng, 9);
                imagedestroy($gd);
                $tempFiles[] = $tmpPng;

                return $tmpPng;
            }
        }

        // Auto-orient JPEG based on camera EXIF orientation flag
        if (in_array($mime, ['image/jpeg', 'image/jpg'], true) && function_exists('exif_read_data') && function_exists('imagerotate')) {
            $exif = @exif_read_data($path);
            $orientation = $exif['Orientation'] ?? 1;

            if ($orientation > 1) {
                $gd = @imagecreatefromjpeg($path);
                if ($gd) {
                    $deg = match ($orientation) {
                        3 => 180,
                        6 => -90,
                        8 => 90,
                        default => 0,
                    };

                    if ($deg !== 0) {
                        $rotated = imagerotate($gd, $deg, 0);
                        if ($rotated) {
                            $tmpJpg = tempnam(sys_get_temp_dir(), 'ah_rot_').'.jpg';
                            imagejpeg($rotated, $tmpJpg, 96);
                            imagedestroy($rotated);
                            $tempFiles[] = $tmpJpg;
                            imagedestroy($gd);

                            return $tmpJpg;
                        }
                    }

                    imagedestroy($gd);
                }
            }
        }

        return $path;
    }

    private function isPdf(UploadedFile|string $file): bool
    {
        if ($file instanceof UploadedFile) {
            $mime = $file->getMimeType();
            $ext = strtolower($file->getClientOriginalExtension());

            if ($mime === 'application/pdf' || $ext === 'pdf') {
                return true;
            }
        }

        $path = $this->resolvePath($file);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return true;
        }

        $mime = @mime_content_type($path);

        return $mime === 'application/pdf';
    }

    private function readBytes(UploadedFile|string $file): string
    {
        $path = $this->resolvePath($file);

        return (string) file_get_contents($path);
    }

    private function resolvePath(UploadedFile|string $file): string
    {
        return $file instanceof UploadedFile ? $file->getRealPath() : $file;
    }

    private function resolveFileName(UploadedFile|string $file): string
    {
        return $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
    }

    /**
     * @param  array<int, UploadedFile|string>  $files
     */
    private function resolveOriginalName(array $files): string
    {
        if (empty($files)) {
            return 'document.pdf';
        }

        $first = $files[0];
        $firstName = $this->resolveFileName($first);
        $base = pathinfo($firstName, PATHINFO_FILENAME);

        if (count($files) === 1) {
            return $this->isPdf($first) ? $firstName : "{$base}.pdf";
        }

        return "{$base} (+".(count($files) - 1).' pages).pdf';
    }
}
