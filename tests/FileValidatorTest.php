<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader\Tests;

use JonatasFenske\Uploader\FileValidator;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do FileValidator para PHP 8.5
 * Valida detecção MIME real, dimensões, SVG sanitização e helpers
 */
final class FileValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/coffeecode_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Limpa arquivos temporários
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testDetectMimeReturnsNullForNonexistentFile(): void
    {
        self::assertNull(FileValidator::detectMime('/nonexistent/file.jpg'));
    }

    public function testDetectMimeReturnsValidMimeForRealImage(): void
    {
        // Cria imagem PNG real mínima (1x1 pixel)
        $img = imagecreatetruecolor(1, 1);
        $path = $this->tempDir . '/test.png';
        imagepng($img, $path);
        imagedestroy($img);

        $mime = FileValidator::detectMime($path);

        self::assertSame('image/png', $mime);
    }

    public function testValidateMimeAcceptsMatchingExtensionAndMime(): void
    {
        $img = imagecreatetruecolor(10, 10);
        $path = $this->tempDir . '/valid.jpg';
        imagejpeg($img, $path);
        imagedestroy($img);

        $file = ['name' => 'foto.jpg', 'tmp_name' => $path];

        self::assertTrue(FileValidator::validateMime($file, ['jpg', 'png']));
    }

    public function testValidateMimeRejectsMismatchedExtension(): void
    {
        // Cria PNG real mas nomeia como .exe
        $img = imagecreatetruecolor(10, 10);
        $path = $this->tempDir . '/fake.exe';
        imagepng($img, $path);
        imagedestroy($img);

        $file = ['name' => 'malware.exe', 'tmp_name' => $path];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Extensão não permitida');
        FileValidator::validateMime($file, ['jpg', 'png']);
    }

    public function testValidateMimeRejectsRenamedFile(): void
    {
        // Cria PNG real mas nomeia como .jpg (MIME spoofing)
        $img = imagecreatetruecolor(10, 10);
        $path = $this->tempDir . '/spoofed.jpg';
        imagepng($img, $path);
        imagedestroy($img);

        $file = ['name' => 'foto.jpg', 'tmp_name' => $path];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MIME real');
        FileValidator::validateMime($file, ['jpg', 'png']);
    }

    public function testValidateMimeThrowsForMissingTmpFile(): void
    {
        $file = ['name' => 'test.jpg', 'tmp_name' => '/nonexistent'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Arquivo temporário não encontrado');
        FileValidator::validateMime($file, ['jpg']);
    }

    public function testValidateDimensionsAcceptsWithinLimits(): void
    {
        $img = imagecreatetruecolor(800, 600);
        $path = $this->tempDir . '/dims.png';
        imagepng($img, $path);
        imagedestroy($img);

        self::assertTrue(FileValidator::validateDimensions($path, maxWidth: 1920, maxHeight: 1080));
    }

    public function testValidateDimensionsRejectsExceedingMaxWidth(): void
    {
        $img = imagecreatetruecolor(5000, 100);
        $path = $this->tempDir . '/wide.png';
        imagepng($img, $path);
        imagedestroy($img);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Largura máxima excedida');
        FileValidator::validateDimensions($path, maxWidth: 1920);
    }

    public function testValidateDimensionsRejectsExceedingMaxHeight(): void
    {
        $img = imagecreatetruecolor(100, 5000);
        $path = $this->tempDir . '/tall.png';
        imagepng($img, $path);
        imagedestroy($img);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Altura máxima excedida');
        FileValidator::validateDimensions($path, maxHeight: 1080);
    }

    public function testValidateDimensionsRejectsBelowMinWidth(): void
    {
        $img = imagecreatetruecolor(50, 50);
        $path = $this->tempDir . '/small.png';
        imagepng($img, $path);
        imagedestroy($img);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Largura mínima não atingida');
        FileValidator::validateDimensions($path, minWidth: 100);
    }

    public function testValidateDimensionsAcceptsNoLimits(): void
    {
        $img = imagecreatetruecolor(1, 1);
        $path = $this->tempDir . '/tiny.png';
        imagepng($img, $path);
        imagedestroy($img);

        self::assertTrue(FileValidator::validateDimensions($path));
    }

    public function testValidateSvgAcceptsCleanSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>';
        $path = $this->tempDir . '/clean.svg';
        file_put_contents($path, $svg);

        self::assertTrue(FileValidator::validateSvg($path));
    }

    public function testValidateSvgRejectsScriptTag(): void
    {
        $svg = '<svg><script>alert("xss")</script></svg>';
        $path = $this->tempDir . '/xss.svg';
        file_put_contents($path, $svg);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SVG contém conteúdo potencialmente malicioso');
        FileValidator::validateSvg($path);
    }

    public function testValidateSvgRejectsJavascriptUrl(): void
    {
        $svg = '<svg><a href="javascript:alert(1)">click</a></svg>';
        $path = $this->tempDir . '/jsurl.svg';
        file_put_contents($path, $svg);

        $this->expectException(\Exception::class);
        FileValidator::validateSvg($path);
    }

    public function testValidateSvgRejectsEventHandler(): void
    {
        $svg = '<svg><rect onclick="alert(1)" width="100" height="100"/></svg>';
        $path = $this->tempDir . '/handler.svg';
        file_put_contents($path, $svg);

        $this->expectException(\Exception::class);
        FileValidator::validateSvg($path);
    }

    public function testValidateSvgRejectsEntityDefinition(): void
    {
        $svg = '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg>&xxe;</svg>';
        $path = $this->tempDir . '/xxe.svg';
        file_put_contents($path, $svg);

        $this->expectException(\Exception::class);
        FileValidator::validateSvg($path);
    }

    public function testValidateSizeAcceptsWithinLimit(): void
    {
        $file = ['size' => 1024 * 1024]; // 1MB

        self::assertTrue(FileValidator::validateSize($file, 2 * 1024 * 1024)); // 2MB
    }

    public function testValidateSizeRejectsExceedingLimit(): void
    {
        $file = ['size' => 5 * 1024 * 1024]; // 5MB

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Arquivo muito grande');
        FileValidator::validateSize($file, 2 * 1024 * 1024); // 2MB
    }

    public function testParseSizeKilobytes(): void
    {
        self::assertSame(1024, FileValidator::parseSize('1KB'));
        self::assertSame(512, FileValidator::parseSize('0.5kb'));
    }

    public function testParseSizeMegabytes(): void
    {
        self::assertSame(1048576, FileValidator::parseSize('1MB'));
        self::assertSame(5242880, FileValidator::parseSize('5mb'));
    }

    public function testParseSizeGigabytes(): void
    {
        self::assertSame(1073741824, FileValidator::parseSize('1GB'));
    }

    public function testParseSizePlainNumber(): void
    {
        self::assertSame(1024, FileValidator::parseSize('1024'));
    }

    public function testRegisterCustomMime(): void
    {
        FileValidator::registerMime('heic', ['image/heic', 'image/heif']);

        $mimes = FileValidator::getTrustedMimes();
        self::assertArrayHasKey('heic', $mimes);
        self::assertContains('image/heic', $mimes['heic']);
    }

    public function testGetTrustedMimesContainsCommonFormats(): void
    {
        $mimes = FileValidator::getTrustedMimes();

        self::assertArrayHasKey('jpg', $mimes);
        self::assertArrayHasKey('png', $mimes);
        self::assertArrayHasKey('pdf', $mimes);
        self::assertArrayHasKey('mp4', $mimes);
        self::assertArrayHasKey('zip', $mimes);
    }
}