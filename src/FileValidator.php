<?php

declare(strict_types=1);

namespace JonatasFenske\Uploader;

use Exception;
use finfo;

/**
 * Validador de arquivos com detecção MIME real via finfo_file
 * PHP 8.5: tipagem estrita, segurança contra ataques de MIME spoofing
 *
 * Bloqueia tentativas clássicas como:
 * - Renomear .exe para .jpg
 * - Enviar script PHP com extensão .png
 * - SVG com XXE/XSS embutido
 * - Polyglot files (arquivos válidos em múltiplos formatos)
 */
class FileValidator
{
 /** @var array<string, array<string>> MIME types confiáveis por extensão */
 private static array $trustedMimes = [
 // Imagens
 'jpg' => ['image/jpeg'],
 'jpeg' => ['image/jpeg'],
 'png' => ['image/png'],
 'gif' => ['image/gif'],
 'webp' => ['image/webp'],
 'avif' => ['image/avif'],
 'bmp' => ['image/bmp', 'image/x-ms-bmp'],
 'svg' => ['image/svg+xml'],
 'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],

 // Documentos
 'pdf' => ['application/pdf'],
 'doc' => ['application/msword'],
 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
 'xls' => ['application/vnd.ms-excel'],
 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
 'ppt' => ['application/vnd.ms-powerpoint'],
 'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
 'odt' => ['application/vnd.oasis.opendocument.text'],
 'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
 'csv' => ['text/csv', 'text/plain', 'application/csv'],
 'txt' => ['text/plain'],

 // Arquivos compactados
 'zip' => ['application/zip', 'application/x-zip-compressed'],
 'rar' => ['application/x-rar-compressed'],
 'gz' => ['application/gzip', 'application/x-gzip'],
 'tar' => ['application/x-tar'],
 '7z' => ['application/x-7z-compressed'],

 // Áudio
 'mp3' => ['audio/mpeg', 'audio/mp3'],
 'wav' => ['audio/wav', 'audio/x-wav'],
 'ogg' => ['audio/ogg'],
 'flac' => ['audio/flac'],

 // Vídeo
 'mp4' => ['video/mp4'],
 'webm' => ['video/webm'],
 'avi' => ['video/x-msvideo'],
 'mov' => ['video/quicktime'],
 'mkv' => ['video/x-matroska'],
 ];

 /**
 * Detecta MIME real do arquivo usando finfo_file
 *
 * @param string $filePath Caminho do arquivo temporário ou permanente
 * @return string|null MIME type detectado ou null em falha
 */
 public static function detectMime(string $filePath): ?string
 {
 if (!file_exists($filePath) || !is_readable($filePath)) {
 return null;
 }

 if (!extension_loaded('fileinfo')) {
 return null;
 }

 $finfo = new finfo(FILEINFO_MIME_TYPE);
 $mime = $finfo->file($filePath);

 return $mime !== false ? $mime : null;
 }

 /**
 * Valida MIME real contra extensões permitidas
 * Bloqueia arquivos renomeados maliciosamente
 *
 * @param array{name: string, tmp_name: string} $file Array $_FILES
 * @param array<string> $allowedExtensions Extensões permitidas (ex: ['jpg', 'png'])
 * @return bool True se válido
 * @throws Exception Se MIME não bater com extensão
 */
 public static function validateMime(array $file, array $allowedExtensions): bool
 {
 if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
 throw new Exception('Arquivo temporário não encontrado');
 }

 $realMime = self::detectMime($file['tmp_name']);
 if ($realMime === null) {
 throw new Exception('Não foi possível detectar o tipo real do arquivo');
 }

 $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

 // Verifica se extensão está na lista permitida
 if (!in_array($extension, $allowedExtensions, true)) {
 throw new Exception("Extensão não permitida: {$extension}");
 }

 // Verifica se MIME real corresponde à extensão declarada
 $trustedForExtension = self::$trustedMimes[$extension] ?? [];

 if ($trustedForExtension === []) {
 // Extensão não mapeada: aceita qualquer MIME (responsabilidade do caller)
 return true;
 }

 if (!in_array($realMime, $trustedForExtension, true)) {
 throw new Exception(
 "MIME real ({$realMime}) não corresponde à extensão declarada ({$extension}). "
 . 'Possível tentativa de upload malicioso.'
 );
 }

 return true;
 }

 /**
 * Valida dimensões de imagem (largura e altura máximas)
 *
 * @param string $filePath Caminho do arquivo de imagem
 * @param int|null $maxWidth Largura máxima em pixels (null = sem limite)
 * @param int|null $maxHeight Altura máxima em pixels (null = sem limite)
 * @param int|null $minWidth Largura mínima em pixels (null = sem limite)
 * @param int|null $minHeight Altura mínima em pixels (null = sem limite)
 * @return bool True se dimensões válidas
 * @throws Exception Se fora dos limites
 */
 public static function validateDimensions(
 string $filePath,
 ?int $maxWidth = null,
 ?int $maxHeight = null,
 ?int $minWidth = null,
 ?int $minHeight = null,
 ): bool {
 $imageSize = @getimagesize($filePath);

 if ($imageSize === false) {
 throw new Exception('Não foi possível ler dimensões da imagem');
 }

 [$width, $height] = $imageSize;

 if ($minWidth !== null && $width < $minWidth) {
 throw new Exception("Largura mínima não atingida: {$width}px < {$minWidth}px");
 }

 if ($minHeight !== null && $height < $minHeight) {
 throw new Exception("Altura mínima não atingida: {$height}px < {$minHeight}px");
 }

 if ($maxWidth !== null && $width > $maxWidth) {
 throw new Exception("Largura máxima excedida: {$width}px > {$maxWidth}px");
 }

 if ($maxHeight !== null && $height > $maxHeight) {
 throw new Exception("Altura máxima excedida: {$height}px > {$maxHeight}px");
 }

 return true;
 }

 /**
 * Detecta e valida SVG limpo de conteúdo malicioso (XSS/XXE)
 *
 * @param string $filePath Caminho do SVG
 * @return bool True se SVG é seguro
 * @throws Exception Se SVG contém conteúdo malicioso
 */
 public static function validateSvg(string $filePath): bool
 {
 $content = @file_get_contents($filePath);

 if ($content === false) {
 throw new Exception('Não foi possível ler conteúdo do SVG');
 }

 // Bloqueia referências externas (XXE)
 $dangerousPatterns = [
 '/<!ENTITY/i', // Entity definitions
 '/<!DOCTYPE.*SYSTEM/i', // External DOCTYPE
 '/javascript:/i', // javascript: URLs
 '/<script/i', // Tags script
 '/on\w+\s*=/i', // Event handlers (onclick, onload, etc.)
 '/data:(?!image\/)/i', // data: URIs não-imagem
 '/base64,/i', // Base64 encoded payloads suspeitos
 '/<\?php/i', // PHP tags
 '/<%/', // ASP tags
 ];

 foreach ($dangerousPatterns as $pattern) {
 if (preg_match($pattern, $content)) {
 throw new Exception(
 'SVG contém conteúdo potencialmente malicioso e foi bloqueado. '
 . 'Padrão detectado: ' . $pattern
 );
 }
 }

 return true;
 }

 /**
 * Valida tamanho do arquivo
 *
 * @param array{size: int} $file Array $_FILES
 * @param int $maxBytes Tamanho máximo em bytes
 * @return bool
 * @throws Exception Se exceder limite
 */
 public static function validateSize(array $file, int $maxBytes): bool
 {
 if (($file['size'] ?? 0) > $maxBytes) {
 $sizeMb = round(($file['size'] ?? 0) / 1024 / 1024, 2);
 $limitMb = round($maxBytes / 1024 / 1024, 2);
 throw new Exception("Arquivo muito grande: {$sizeMb}MB > {$limitMb}MB");
 }

 return true;
 }

 /**
 * Registra MIME types customizados para uma extensão
 *
 * @param string $extension Extensão sem ponto
 * @param array<string> $mimes MIME types aceitos
 */
 public static function registerMime(string $extension, array $mimes): void
 {
 self::$trustedMimes[strtolower($extension)] = $mimes;
 }

 /**
 * Retorna todos os MIME types confiáveis registrados
 *
 * @return array<string, array<string>>
 */
 public static function getTrustedMimes(): array
 {
 return self::$trustedMimes;
 }

 /**
 * Helpers de conversão de tamanho legível
 */
 public static function parseSize(string $size): int
 {
 $size = strtolower(trim($size));
 $value = (float)preg_replace('/[^0-9.]/', '', $size);
 $unit = preg_replace('/[^a-z]/', '', $size);

 return (int)match ($unit) {
 'k', 'kb' => $value * 1024,
 'm', 'mb' => $value * 1024 * 1024,
 'g', 'gb' => $value * 1024 * 1024 * 1024,
 't', 'tb' => $value * 1024 * 1024 * 1024 * 1024,
 default => $value,
 };
 }
}