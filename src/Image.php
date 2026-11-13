<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

use Exception;
use GdImage;
use Imagick;

/**
 * Upload e redimensionamento de imagens com suporte dual GD/Imagick
 * PHP 8.5: detecção automática de engine, tipagem estrita, enum MimeType
 */
class Image extends Uploader
{
    use ImageProcessor;

    /** @var array<MimeType> */
    protected static array $allowTypes = [
        MimeType::IMAGE_JPEG,
        MimeType::IMAGE_PNG,
        MimeType::IMAGE_GIF,
        MimeType::IMAGE_WEBP,
    ];

    private readonly ImageEngine $engine;

    /**
     * @param string $uploadDir Diretório base de uploads
     * @param string $fileTypeDir Subdiretório por tipo de arquivo
     * @param bool $monthYearPath Se true, cria subpastas YYYY/MM automaticamente
     * @param ImageEngine $engine Engine de processamento (AUTO detecta a melhor disponível)
     */
    public function __construct(
        string $uploadDir,
        string $fileTypeDir,
        bool $monthYearPath = true,
        ImageEngine $engine = ImageEngine::AUTO,
    ) {
        parent::__construct($uploadDir, $fileTypeDir, $monthYearPath);
        $this->engine = $engine->resolve();
    }

    /**
     * Retorna a engine de imagem ativa nesta instância
     */
    public function getEngine(): ImageEngine
    {
        return $this->engine;
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $image
     * @param string $name Nome desejado para o arquivo
     * @param int $width Largura máxima em pixels
     * @param array{jpg?: int, png?: int, webp?: int}|null $quality Qualidade por formato
     * @param bool $toWebP Se true, converte automaticamente para WebP após processamento
     * @param bool $validateMime Se true, valida MIME real via finfo_file contra extensão declarada
     * @param int|null $maxWidth Largura máxima permitida da imagem original (null = sem limite)
     * @param int|null $maxHeight Altura máxima permitida da imagem original (null = sem limite)
     * @param int|null $minWidth Largura mínima permitida da imagem original (null = sem limite)
     * @param int|null $minHeight Altura mínima permitida da imagem original (null = sem limite)
     * @param int|null $maxSizeBytes Tamanho máximo do arquivo em bytes (null = sem limite)
     * @return string Caminho do arquivo salvo (WebP se $toWebP for true)
     * @throws Exception
     */
    public function upload(
        array $image,
        string $name,
        int $width = 2000,
        ?array $quality = null,
        bool $toWebP = false,
        bool $validateMime = false,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?int $minWidth = null,
        ?int $minHeight = null,
        ?int $maxSizeBytes = null,
    ): string {
        if (empty($image['type'])) {
            throw new Exception('Dados de imagem inválidos');
        }

        // Validação de tamanho do arquivo
        if ($maxSizeBytes !== null) {
            FileValidator::validateSize($image, $maxSizeBytes);
        }

        // Validação MIME real via finfo_file (segurança contra spoofing)
        if ($validateMime) {
            $allowedExtensions = array_map(
                fn(MimeType $mt): string => $mt->extension(),
                static::$allowTypes
            );
            FileValidator::validateMime($image, $allowedExtensions);
        }

        $mimeType = MimeType::tryFrom($image['type']);

        if ($mimeType === null || !in_array($mimeType, static::$allowTypes, true)) {
            throw new Exception('Tipo de imagem não suportado');
        }

        // Validação de dimensões da imagem original
        if ($maxWidth !== null || $maxHeight !== null || $minWidth !== null || $minHeight !== null) {
            FileValidator::validateDimensions(
                $image['tmp_name'],
                maxWidth: $maxWidth,
                maxHeight: $maxHeight,
                minWidth: $minWidth,
                minHeight: $minHeight,
            );
        }

        $this->ext = $mimeType->extension();
        $this->sanitizeName($name);

        // GIF não é redimensionado, apenas movido (não convertido para WebP)
        if ($mimeType === MimeType::IMAGE_GIF) {
            move_uploaded_file($image['tmp_name'], "{$this->path}/{$this->name}");
            return "{$this->path}/{$this->name}";
        }

        $defaultQuality = ['jpg' => 75, 'png' => 5, 'webp' => 75];
        $resolvedQuality = $quality ?? $defaultQuality;

        $outputPath = match ($this->engine) {
            ImageEngine::IMAGICK => $this->processWithImagick($image, $width, $resolvedQuality),
            default => $this->processWithGd($image, $width, $resolvedQuality),
        };

        // Converte para WebP se solicitado e a imagem ainda não é WebP
        if ($toWebP && $this->ext !== 'webp') {
            $outputPath = $this->convertToWebP($outputPath, $resolvedQuality['webp'] ?? 75);
        }

        return $outputPath;
    }

    /**
     * Converte imagem processada para WebP usando a engine ativa
     *
     * @param string $imagePath Caminho da imagem de origem
     * @param int $quality Qualidade WebP (1-100)
     * @return string Caminho do arquivo WebP gerado
     */
    private function convertToWebP(string $imagePath, int $quality = 75): string
    {
        $webPPath = pathinfo($imagePath, PATHINFO_DIRNAME) . '/' . pathinfo($imagePath, PATHINFO_FILENAME) . '.webp';

        if ($this->engine === ImageEngine::IMAGICK) {
            try {
                $imagick = new Imagick($imagePath);
                $imagick->setImageFormat('WEBP');
                $imagick->setImageCompressionQuality($quality);
                $imagick->writeImage($webPPath);
                $imagick->destroy();

                // Remove original não-WebP após conversão bem-sucedida
                if ($imagePath !== $webPPath && file_exists($imagePath)) {
                    unlink($imagePath);
                }

                return $webPPath;
            } catch (\ImagickException) {
                return $imagePath;
            }
        }

        // Conversão via GD nativo
        $mime = mime_content_type($imagePath);
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($imagePath),
            'image/png' => @imagecreatefrompng($imagePath),
            'image/gif' => @imagecreatefromgif($imagePath),
            default => false,
        };

        if ($image instanceof GdImage) {
            imagewebp($image, $webPPath, $quality);
            imagedestroy($image);

            if ($imagePath !== $webPPath && file_exists($imagePath)) {
                unlink($imagePath);
            }

            return $webPPath;
        }

        return $imagePath;
    }

    /**
     * Processa imagem via GD
     *
     * @param array{tmp_name: string} $image
     * @param int $maxWidth
     * @param array{jpg?: int, png?: int, webp?: int} $quality
     * @return string
     * @throws Exception
     */
    private function processWithGd(array $image, int $maxWidth, array $quality): string
    {
        $source = $this->gdLoadSource($image['tmp_name']);
        if ($source === null) {
            throw new Exception('Falha ao carregar imagem com GD');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $dims = $this->calculateResizeDimensions($srcW, $srcH, $maxWidth);

        $thumb = imagecreatetruecolor($dims['w'], $dims['h']);

        if ($this->ext === 'png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $dims['w'], $dims['h'], $srcW, $srcH);

        $outputPath = "{$this->path}/{$this->name}";

        match ($this->ext) {
            'jpg' => imagejpeg($thumb, $outputPath, $quality['jpg'] ?? 75),
            'png' => imagepng($thumb, $outputPath, $quality['png'] ?? 5),
            'webp' => imagewebp($thumb, $outputPath, $quality['webp'] ?? 75),
            default => null,
        };

        imagedestroy($source);
        imagedestroy($thumb);

        return $outputPath;
    }

    /**
     * Carrega imagem fonte via GD conforme extensão
     */
    private function gdLoadSource(string $tmpName): ?GdImage
    {
        return match ($this->ext) {
            'jpg' => @imagecreatefromjpeg($tmpName) ?: null,
            'png' => @imagecreatefrompng($tmpName) ?: null,
            'webp' => @imagecreatefromwebp($tmpName) ?: null,
            default => null,
        };
    }

    /**
     * Processa imagem via Imagick
     *
     * @param array{tmp_name: string} $image
     * @param int $maxWidth
     * @param array{jpg?: int, png?: int, webp?: int} $quality
     * @return string
     * @throws Exception
     */
    private function processWithImagick(array $image, int $maxWidth, array $quality): string
    {
        try {
            $imagick = new Imagick($image['tmp_name']);
        } catch (\ImagickException $e) {
            throw new Exception("Falha ao carregar imagem com Imagick: {$e->getMessage()}");
        }

        $srcW = $imagick->getImageWidth();
        $srcH = $imagick->getImageHeight();
        $dims = $this->calculateResizeDimensions($srcW, $srcH, $maxWidth);

        $imagick->thumbnailImage($dims['w'], $dims['h'], bestfit: true, fill: false);

        $format = match ($this->ext) {
            'jpg' => 'JPEG',
            'png' => 'PNG',
            'webp' => 'WEBP',
            default => 'JPEG',
        };

        $imagick->setImageFormat($format);

        $compressionQuality = match ($format) {
            'JPEG' => $quality['jpg'] ?? 75,
            'PNG' => ($quality['png'] ?? 5) * 10,
            'WEBP' => $quality['webp'] ?? 75,
            default => 75,
        };

        $imagick->setImageCompressionQuality($compressionQuality);

        $outputPath = "{$this->path}/{$this->name}";

        try {
            $imagick->writeImage($outputPath);
        } catch (\ImagickException $e) {
            $imagick->destroy();
            throw new Exception("Falha ao salvar imagem com Imagick: {$e->getMessage()}");
        }

        $imagick->destroy();

        return $outputPath;
    }
}