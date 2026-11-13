<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

use Exception;

/**
 * Upload de arquivos de mídia (áudio/vídeo) modernizado para PHP 8.5
 * Suporta MP3 e MP4 com validação type-safe via enum MimeType
 */
class Media extends Uploader
{
    /** @var array<MimeType> */
    protected static array $allowTypes = [
        MimeType::AUDIO_MP3,
        MimeType::AUDIO_MPEG,
        MimeType::VIDEO_MP4,
    ];

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $media
     * @param string $name Nome desejado para o arquivo
     * @return string Caminho do arquivo salvo
     * @throws Exception
     */
    public function upload(array $media, string $name): string
    {
        if (empty($media['type'])) {
            throw new Exception('Dados de mídia inválidos');
        }

        $mimeType = MimeType::tryFrom($media['type']);

        if ($mimeType === null || !in_array($mimeType, static::$allowTypes, true)) {
            throw new Exception("Tipo de mídia não suportado: {$media['type']}");
        }

        $this->ext = $mimeType->extension();
        $this->sanitizeName($name);

        if (!move_uploaded_file($media['tmp_name'], "{$this->path}/{$this->name}")) {
            throw new Exception('Falha ao mover arquivo de mídia');
        }

        return "{$this->path}/{$this->name}";
    }
}