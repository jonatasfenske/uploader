<?php

declare(strict_types=1);

/**
 * Exemplo de upload seguro com validação MIME real e conversão WebP
 * Execute em um servidor web com formulário de upload
 */

require __DIR__ . '/../vendor/autoload.php';

use JonatasFenske\Uploader\Image;
use JonatasFenske\Uploader\File;
use JonatasFenske\Uploader\FileValidator;
use JonatasFenske\Uploader\ImageEngine;

// Verificar engine disponível
echo "Engine de imagem: " . ImageEngine::best()->value . "\n";

// Upload de imagem seguro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $image = new Image('storage/uploads', 'images');

    try {
        $path = $image->upload(
            image: $_FILES['foto'],
            name: 'avatar',
            width: 1200,
            toWebP: true,
            validateMime: true,
            maxWidth: 4096,
            maxHeight: 4096,
            maxSizeBytes: FileValidator::parseSize('5MB'),
        );

        echo "✅ Imagem salva: {$path}\n";
    } catch (\Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
}

// Upload de documento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documento'])) {
    $file = new File('storage/uploads', 'documents');

    try {
        $path = $file->upload($_FILES['documento'], 'relatorio');
        echo "✅ Documento salvo: {$path}\n";
    } catch (\Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
}

// Validação standalone (sem upload)
echo "\n--- Validação Standalone ---\n";
echo "MIME types confiáveis registrados: " . count(FileValidator::getTrustedMimes()) . "\n";
echo "Parse '10MB' = " . FileValidator::parseSize('10MB') . " bytes\n";
echo "Parse '1.5GB' = " . FileValidator::parseSize('1.5GB') . " bytes\n";