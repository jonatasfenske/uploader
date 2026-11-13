<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

use Exception;

/**
 * Upload de documentos e arquivos genéricos modernizado para PHP 8.5
 * Suporta PDF, ZIP, RAR, CSV, Office e outros formatos com validação type-safe via enum
 */
class File extends Uploader
{
    /** @var array<MimeType> */
    protected static array $allowTypes = [
        MimeType::APPLICATION_PDF,
        MimeType::APPLICATION_ZIP,
        MimeType::APPLICATION_RAR,
        MimeType::APPLICATION_BZIP,
        MimeType::TEXT_CSV,
        MimeType::DOC,
        MimeType::DOCX,
        MimeType::XLS,
        MimeType::XLSX,
        MimeType::ODS,
        MimeType::ODT,
    ];

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     * @param string $name Nome desejado para o arquivo
     * @return string Caminho do arquivo salvo
     * @throws Exception
     */
    public function upload(array $file, string $name): string
    {
        if (empty($file['type'])) {
            throw new Exception('Dados de arquivo inválidos');
        }

        $mimeType = MimeType::tryFrom($file['type']);

        if ($mimeType === null || !in_array($mimeType, static::$allowTypes, true)) {
            throw new Exception("Tipo de arquivo não suportado: {$file['type']}");
        }

        $this->ext = $mimeType->extension();
        $this->sanitizeName($name);

        if (!move_uploaded_file($file['tmp_name'], "{$this->path}/{$this->name}")) {
            throw new Exception('Falha ao mover arquivo enviado');
        }

        return "{$this->path}/{$this->name}";
    }
}