<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

/**
 * Enum de tipos MIME suportados pelo Uploader
 * PHP 8.5 Backed Enum para validação type-safe de uploads
 */
enum MimeType: string
{
    // Imagens
    case IMAGE_JPEG = 'image/jpeg';
    case IMAGE_PNG = 'image/png';
    case IMAGE_GIF = 'image/gif';
    case IMAGE_WEBP = 'image/webp';

    // Documentos
    case APPLICATION_PDF = 'application/pdf';
    case APPLICATION_ZIP = 'application/zip';
    case APPLICATION_RAR = 'application/x-rar-compressed';
    case APPLICATION_BZIP = 'application/x-bzip';
    case TEXT_CSV = 'text/csv';

    // Office
    case DOC = 'application/msword';
    case DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    case XLS = 'application/vnd.ms-excel';
    case XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case ODS = 'application/vnd.oasis.opendocument.spreadsheet';
    case ODT = 'application/vnd.oasis.opendocument.text';

    // Mídia
    case AUDIO_MP3 = 'audio/mp3';
    case AUDIO_MPEG = 'audio/mpeg';
    case VIDEO_MP4 = 'video/mp4';

    /**
     * Retorna a extensão padrão para o tipo MIME
     */
    public function extension(): string
    {
        return match ($this) {
            self::IMAGE_JPEG => 'jpg',
            self::IMAGE_PNG => 'png',
            self::IMAGE_GIF => 'gif',
            self::IMAGE_WEBP => 'webp',
            self::APPLICATION_PDF => 'pdf',
            self::APPLICATION_ZIP => 'zip',
            self::APPLICATION_RAR => 'rar',
            self::APPLICATION_BZIP => 'bz',
            self::TEXT_CSV => 'csv',
            self::DOC => 'doc',
            self::DOCX => 'docx',
            self::XLS => 'xls',
            self::XLSX => 'xlsx',
            self::ODS => 'ods',
            self::ODT => 'odt',
            self::AUDIO_MP3, self::AUDIO_MPEG => 'mp3',
            self::VIDEO_MP4 => 'mp4',
        };
    }

    /**
     * Verifica se o tipo MIME é uma imagem
     */
    public function isImage(): bool
    {
        return match ($this) {
            self::IMAGE_JPEG, self::IMAGE_PNG, self::IMAGE_GIF, self::IMAGE_WEBP => true,
            default => false,
        };
    }
}