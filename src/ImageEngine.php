<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

use RuntimeException;

/**
 * Enum de engines de processamento de imagem com detecção automática
 * PHP 8.5 Backed Enum para type-safety na seleção GD vs Imagick
 */
enum ImageEngine: string
{
    case GD = 'gd';
    case IMAGICK = 'imagick';
    case AUTO = 'auto';

    /**
     * Resolve a engine disponível no servidor
     * Prioriza Imagick quando AUTO, pois oferece melhor qualidade e mais formatos
     *
     * @return self Engine resolvida (GD ou IMAGICK)
     * @throws RuntimeException Se nenhuma engine estiver disponível
     */
    public function resolve(): self
    {
        if ($this !== self::AUTO) {
            $this->ensureAvailable();
            return $this;
        }

        // AUTO: prefere Imagick pela qualidade superior e suporte a mais formatos
        if (self::IMAGICK->isAvailable()) {
            return self::IMAGICK;
        }

        if (self::GD->isAvailable()) {
            return self::GD;
        }

        throw new RuntimeException(
            'Nenhuma engine de imagem disponível. Instale a extensão GD ou Imagick.'
        );
    }

    /**
     * Verifica se a engine está disponível no servidor atual
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::GD => extension_loaded('gd'),
            self::IMAGICK => extension_loaded('imagick') && class_exists(\Imagick::class),
            self::AUTO => true, // AUTO sempre "existe", mas resolve() valida
        };
    }

    /**
     * Retorna lista de engines disponíveis no servidor atual
     *
     * @return array<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            [self::GD, self::IMAGICK],
            fn(self $engine): bool => $engine->isAvailable()
        ));
    }

    /**
     * Retorna a melhor engine disponível (Imagick > GD)
     *
     * @throws RuntimeException Se nenhuma engine estiver disponível
     */
    public static function best(): self
    {
        return self::AUTO->resolve();
    }

    /**
     * Garante que a engine específica está disponível
     *
     * @throws RuntimeException Se a engine não estiver instalada
     */
    private function ensureAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(
                "Extensão {$this->value} não está disponível neste servidor."
            );
        }
    }
}