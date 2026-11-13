# JonatasFenske\Uploader

<p align="center">
 <strong>Upload seguro de arquivos PHP 8.5 com validação MIME real e conversão WebP</strong>
</p>

<p align="center">
 <img src="https://img.shields.io/badge/PHP-8.5%2B-8892BF?logo=php" alt="PHP 8.5+">
 <img src="https://img.shields.io/badge/license-MIT-blue" alt="MIT License">
</p>

## Instalação

```bash
composer require jonatasfenske/uploader
```

## Recursos

- **Validação MIME real** via `finfo_file` — bloqueia `.exe` renomeado como `.jpg`
- **Sanitização de SVG** contra XSS, XXE, event handlers e PHP tags
- **Validação de dimensões** de imagens (min/max width e height)
- **Conversão WebP automática** no momento do upload
- **Suporte dual GD/Imagick** com detecção automática via `ImageEngine` enum
- **MimeType enum** para type-safety em tipos permitidos
- **Upload múltiplo** com normalização de array `$_FILES`
- **Classes especializadas**: Image, File, Media, Send

## Uso Rápido

### Upload de Imagem Seguro

```php
use JonatasFenske\Uploader\Image;
use JonatasFenske\Uploader\FileValidator;

$image = new Image('storage/uploads', 'images');

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
```

### Upload de Documentos

```php
use JonatasFenske\Uploader\File;

$file = new File('storage/uploads', 'documents');
$path = $file->upload($_FILES['pdf'], 'relatorio-anual');
```

### Upload Múltiplo

```php
$files = $image->multiple('fotos', $_FILES);
foreach ($files as $file) {
 $path = $image->upload($file, 'galeria', width: 800);
}
```

### Validação Standalone

```php
use JonatasFenske\Uploader\FileValidator;

FileValidator::validateMime($_FILES['foto'], ['jpg', 'png', 'webp']);
FileValidator::validateDimensions($_FILES['foto']['tmp_name'], maxWidth: 4096);
FileValidator::validateSvg('storage/uploads/icon.svg');
FileValidator::validateSize($_FILES['video'], FileValidator::parseSize('50MB'));
```

## Testes

```bash
composer test
```

## Licença

MIT