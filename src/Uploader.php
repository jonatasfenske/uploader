<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

/**
 * Classe abstrata base para uploads modernizada para PHP 8.5
 * Gerencia criação de diretórios, sanitização de nomes e estrutura de paths
 */
abstract class Uploader
{
    protected string $path;
    protected string $name;
    protected string $ext;

    /** @var array<MimeType> */
    protected static array $allowTypes = [];

    /** @var array<string> */
    protected static array $extensions = [];

    /**
     * @param string $uploadDir Diretório base de uploads
     * @param string $fileTypeDir Subdiretório por tipo de arquivo
     * @param bool $monthYearPath Se true, cria subpastas YYYY/MM automaticamente
     */
    public function __construct(
        string $uploadDir,
        string $fileTypeDir,
        bool $monthYearPath = true,
    ) {
        $this->ensureDirectory($uploadDir);
        $this->ensureDirectory("{$uploadDir}/{$fileTypeDir}");
        $this->path = "{$uploadDir}/{$fileTypeDir}";

        if ($monthYearPath) {
            $this->createDatePath("{$uploadDir}/{$fileTypeDir}");
        }
    }

    /**
     * Retorna tipos MIME permitidos
     *
     * @return array<MimeType>
     */
    public static function isAllowed(): array
    {
        return static::$allowTypes;
    }

    /**
     * Retorna extensões permitidas
     *
     * @return array<string>
     */
    public static function isExtension(): array
    {
        return static::$extensions;
    }

    /**
     * Sanitiza e gera nome único para o arquivo
     */
    protected function sanitizeName(string $name): string
    {
        $name = mb_convert_encoding(
            htmlspecialchars(mb_strtolower($name)),
            'ISO-8859-1',
            'UTF-8'
        );

        $formats = mb_convert_encoding(
            'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜüÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿRr"!@#$%&*()_-+={[}]/?;:.,\\\'<>°ºª',
            'ISO-8859-1',
            'UTF-8'
        );

        $replace = 'aaaaaaaceeeeiiiidnoooooouuuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyrr                                 ';

        $sanitized = strtr($name, $formats, $replace);
        $sanitized = trim(str_replace(' ', '-', $sanitized));
        $sanitized = preg_replace('/-{2,}/', '-', $sanitized);

        $this->name = "{$sanitized}.{$this->ext}";

        if (file_exists("{$this->path}/{$this->name}") && is_file("{$this->path}/{$this->name}")) {
            $this->name = "{$sanitized}-" . time() . ".{$this->ext}";
        }

        return $this->name;
    }

    /**
     * Garante que o diretório existe
     */
    protected function ensureDirectory(string $dir, int $mode = 0755): void
    {
        if (!file_exists($dir) || !is_dir($dir)) {
            mkdir($dir, $mode, true);
        }
    }

    /**
     * Cria subpastas por ano/mês
     */
    protected function createDatePath(string $basePath): void
    {
        [$yearPath, $monthPath] = explode('/', date('Y/m'));

        $this->ensureDirectory("{$basePath}/{$yearPath}");
        $this->ensureDirectory("{$basePath}/{$yearPath}/{$monthPath}");
        $this->path = "{$basePath}/{$yearPath}/{$monthPath}";
    }

    /**
     * Extrai extensão do arquivo enviado
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     */
    protected function extractExtension(array $file): void
    {
        $this->ext = mb_strtolower(pathinfo($file['name'])['extension']);
    }

    /**
     * Reorganiza array de múltiplos uploads para formato normalizado
     *
     * @param string $inputName Nome do campo input
     * @param array<string, array<string, mixed>> $files Array $_FILES
     * @return array<int, array<string, mixed>>
     */
    public function multiple(string $inputName, array $files): array
    {
        $normalized = [];
        $count = count($files[$inputName]['name']);
        $keys = array_keys($files[$inputName]);

        for ($i = 0; $i < $count; $i++) {
            foreach ($keys as $key) {
                $normalized[$i][$key] = $files[$inputName][$key][$i];
            }
        }

        return $normalized;
    }
}