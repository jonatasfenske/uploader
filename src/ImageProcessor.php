<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

use RuntimeException;

/**
 * Trait com lógica comum de processamento de imagem para GD e Imagick
 * Centraliza cálculos de crop, redimensionamento e sanitização de nomes
 */
trait ImageProcessor
{
    /**
     * Calcula região de crop centralizada mantendo proporção
     *
     * @param int $srcW Largura da imagem original
     * @param int $srcH Altura da imagem original
     * @param int $targetW Largura desejada
     * @param int $targetH Altura desejada
     * @return array{x: int, y: int, w: int, h: int} Coordenadas e dimensões do crop
     */
    protected function calculateCropRegion(
        int $srcW,
        int $srcH,
        int $targetW,
        int $targetH,
    ): array {
        $ratioW = $srcW / $targetW;
        $ratioH = $srcH / $targetH;

        $cropX = 0;
        $cropY = 0;
        $cropW = $srcW;
        $cropH = $srcH;

        if ($ratioW > $ratioH) {
            $cropW = (int)round($srcW / $ratioW * $ratioH);
            $cropX = (int)round(($srcW - $cropW) / 2);
        } elseif ($ratioH > $ratioW) {
            $cropH = (int)round($srcH / $ratioH * $ratioW);
            $cropY = (int)round(($srcH - $cropH) / 2);
        }

        return [
            'x' => max(0, $cropX),
            'y' => max(0, $cropY),
            'w' => max(1, $cropW),
            'h' => max(1, $cropH),
        ];
    }

    /**
     * Calcula dimensões proporcionais para redimensionamento
     *
     * @param int $srcW Largura original
     * @param int $srcH Altura original
     * @param int $maxWidth Largura máxima desejada
     * @param int|null $maxHeight Altura máxima desejada (null = proporcional)
     * @return array{w: int, h: int} Novas dimensões
     */
    protected function calculateResizeDimensions(
        int $srcW,
        int $srcH,
        int $maxWidth,
        ?int $maxHeight = null,
    ): array {
        $newW = min($maxWidth, $srcW);
        $newH = $maxHeight ?? (int)round(($newW * $srcH) / max(1, $srcW));

        // Se altura máxima foi definida, respeita ambas as restrições
        if ($maxHeight !== null && $newH > $maxHeight) {
            $newH = $maxHeight;
            $newW = (int)round(($newH * $srcW) / max(1, $srcH));
        }

        return [
            'w' => max(1, $newW),
            'h' => max(1, $newH),
        ];
    }

    /**
     * Sanitiza nome de arquivo removendo caracteres especiais
     */
    protected function sanitizeFilename(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/[^a-z0-9_-]/', '-', $name);
        $name = preg_replace('/-{2,}/', '-', $name);
        return trim($name, '-');
    }

    /**
     * Constrói nome único para cache baseado em hash da imagem original
     */
    protected function buildCacheName(string $imagePath, int $width, ?int $height): string
    {
        $filename = pathinfo($imagePath)['filename'];
        $sanitized = $this->sanitizeFilename($filename);
        $hash = hash('crc32b', pathinfo($imagePath)['basename']);
        $dimensions = $height !== null ? "-{$width}x{$height}" : "-{$width}";

        return "{$sanitized}{$dimensions}-{$hash}";
    }

    /**
     * Valida se o MIME type da imagem é suportado
     *
     * @param string $mime MIME type da imagem
     * @param array<string> $allowedMimes Lista de MIME types permitidos
     * @throws RuntimeException Se o tipo não for suportado
     */
    protected function validateMimeType(string $mime, array $allowedMimes): void
    {
        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException("Tipo de imagem não suportado: {$mime}");
        }
    }

    /**
     * Garante que o diretório de saída existe
     */
    protected function ensureDirectory(string $dir, int $mode = 0755): void
    {
        if (!file_exists($dir) || !is_dir($dir)) {
            if (!mkdir($dir, $mode, true)) {
                throw new RuntimeException("Não foi possível criar o diretório: {$dir}");
            }
        }
    }
}