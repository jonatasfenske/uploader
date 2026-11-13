<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

use Exception;

/**
 * Upload genérico customizável modernizado para PHP 8.5
 * Permite definir tipos MIME e extensões permitidas dinamicamente via enum MimeType
 */
class Send extends Uploader
{
    /**
     * @param string $uploadDir Diretório base de uploads
     * @param string $fileTypeDir Subdiretório por tipo de arquivo
     * @param array<MimeType> $allowTypes Tipos MIME permitidos
     * @param bool $monthYearPath Se true, cria subpastas YYYY/MM automaticamente
     */
    public function __construct(
        string $uploadDir,
        string $fileTypeDir,
        array $allowTypes,
        bool $monthYearPath = true,
    ) {
        parent::__construct($uploadDir, $fileTypeDir, $monthYearPath);
        static::$allowTypes = $allowTypes;
        static::$extensions = array_map(
            fn(MimeType $type): string => $type->extension(),
            $allowTypes
        );
    }

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