<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentCompiler;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentCompilerTest extends TestCase
{
    private DocumentCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new DocumentCompiler();
    }

    public function test_single_pdf_returns_original_bytes(): void
    {
        $pdfBytes = $this->createTestPdf();
        $file = UploadedFile::fake()->createWithContent('sample.pdf', $pdfBytes);

        $result = $this->compiler->compile($file);

        $this->assertSame($pdfBytes, $result['bytes']);
        $this->assertSame(1, $result['page_count']);
        $this->assertSame('sample.pdf', $result['original_name']);
    }

    public function test_single_image_compiles_to_valid_pdf(): void
    {
        $jpegPath = $this->createTestImage('page1.jpg', 600, 800);
        $file = new UploadedFile($jpegPath, 'page1.jpg', 'image/jpeg', null, true);

        try {
            $result = $this->compiler->compile($file);

            $this->assertNotEmpty($result['bytes']);
            $this->assertStringStartsWith('%PDF', $result['bytes']);
            $this->assertSame(1, $result['page_count']);
            $this->assertSame('page1.pdf', $result['original_name']);
        } finally {
            @unlink($jpegPath);
        }
    }

    public function test_multiple_images_compile_to_multi_page_pdf(): void
    {
        $img1 = $this->createTestImage('img1.jpg', 600, 800, 'Page 1');
        $img2 = $this->createTestImage('img2.png', 800, 600, 'Page 2');
        $img3 = $this->createTestImage('img3.jpg', 600, 800, 'Page 3');

        $files = [
            new UploadedFile($img1, 'page1.jpg', 'image/jpeg', null, true),
            new UploadedFile($img2, 'page2.png', 'image/png', null, true),
            new UploadedFile($img3, 'page3.jpg', 'image/jpeg', null, true),
        ];

        try {
            $result = $this->compiler->compile($files);

            $this->assertNotEmpty($result['bytes']);
            $this->assertStringStartsWith('%PDF', $result['bytes']);
            $this->assertSame(3, $result['page_count']);
            $this->assertSame('page1 (+2 pages).pdf', $result['original_name']);
        } finally {
            @unlink($img1);
            @unlink($img2);
            @unlink($img3);
        }
    }

    public function test_mixed_pdf_and_images_compile_to_single_pdf(): void
    {
        $pdfBytes = $this->createTestPdf('PDF Document');
        $pdfFile = UploadedFile::fake()->createWithContent('paper.pdf', $pdfBytes);

        $imgPath = $this->createTestImage('diagram.jpg', 600, 800, 'Diagram');
        $imgFile = new UploadedFile($imgPath, 'diagram.jpg', 'image/jpeg', null, true);

        try {
            $result = $this->compiler->compile([$pdfFile, $imgFile]);

            $this->assertNotEmpty($result['bytes']);
            $this->assertSame(2, $result['page_count']);
        } finally {
            @unlink($imgPath);
        }
    }

    private function createTestPdf(string $text = 'Test'): string
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->Text(50, 50, $text);

        return (string) $pdf->Output('', 'S');
    }

    private function createTestImage(string $name, int $w, int $h, string $label = 'Test'): string
    {
        $im = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($im, 245, 245, 245);
        imagefill($im, 0, 0, $bg);
        $tc = imagecolorallocate($im, 30, 30, 30);
        imagestring($im, 5, 20, 20, $label, $tc);

        $path = tempnam(sys_get_temp_dir(), 'test_doc_').'_'.$name;
        if (str_ends_with($name, '.png')) {
            imagepng($im, $path);
        } else {
            imagejpeg($im, $path, 95);
        }
        imagedestroy($im);

        return $path;
    }
}
