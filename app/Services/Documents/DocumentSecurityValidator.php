<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DocumentSecurityValidator
{
    public const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    public const ALLOWED_MIME_TYPES = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/pjpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    ];

    public const MAX_UNCOMPRESSED_OOXML_BYTES = 50 * 1024 * 1024; // 50 MB

    public const MAX_OOXML_ENTRIES = 500;

    /**
     * Valida de forma exhaustiva un archivo cargado.
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'El archivo no se cargó correctamente.',
            ]);
        }

        // 1. Tamaño máximo
        $size = $file->getSize();
        if ($size > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'El archivo supera el tamaño máximo permitido de 10 MB.',
            ]);
        }

        // 2. Extensión y MIME coherentes
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        if (! array_key_exists($mime, self::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages([
                'file' => "Tipo de archivo no permitido ({$mime}). Solo se aceptan PDF, PNG, JPEG y XLSX.",
            ]);
        }

        $validExtensions = self::ALLOWED_MIME_TYPES[$mime];
        if (! in_array($extension, $validExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => "La extensión '.{$extension}' no coincide con el contenido real del archivo ({$mime}).",
            ]);
        }

        // 3. Inspección de contenido malicioso / scripts embebidos
        $this->inspectContent($file, $mime, $extension);

        // 4. Nombre saneado
        $sanitizedName = $this->sanitizeFilename($file->getClientOriginalName());

        return [
            'original_name' => $sanitizedName,
            'mime_type' => $mime,
            'file_size' => $size,
            'extension' => $extension,
        ];
    }

    /**
     * Sanea el nombre original del archivo eliminando rutas y caracteres peligrosos.
     */
    public function sanitizeFilename(string $filename): string
    {
        // Extraer únicamente el nombre base sin rutas
        $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));

        // Eliminar caracteres de control y nulos
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename);

        // Reemplazar secuencias de path traversal
        $filename = str_replace('..', '', $filename);

        // Limitar longitud
        if (mb_strlen($filename) > 200) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = mb_substr($name, 0, 190).'.'.$ext;
        }

        return $filename ?: 'documento_'.time();
    }

    /**
     * Inspecciona el contenido para detectar ejecutables, PHP o zip bombs.
     *
     * @throws ValidationException
     */
    protected function inspectContent(UploadedFile $file, string $mime, string $extension): void
    {
        $path = $file->getRealPath();

        // Leer primeros 4096 bytes para buscar cabeceras de script
        $handle = @fopen($path, 'rb');
        if ($handle) {
            $header = fread($handle, 4096);
            fclose($handle);

            if ($header !== false) {
                $lowered = strtolower($header);
                if (
                    str_contains($lowered, '<?php') ||
                    str_contains($lowered, '<?=') ||
                    str_contains($lowered, '<script') ||
                    str_contains($lowered, '<html') ||
                    str_contains($lowered, '<svg')
                ) {
                    throw ValidationException::withMessages([
                        'file' => 'El archivo contiene secuencias de código o etiquetas no permitidas.',
                    ]);
                }
            }
        }

        // Inspección OOXML (XLSX)
        if ($extension === 'xlsx' && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            if ($zip->open($path) === true) {
                $numFiles = $zip->numFiles;
                if ($numFiles > self::MAX_OOXML_ENTRIES) {
                    $zip->close();
                    throw ValidationException::withMessages([
                        'file' => 'El archivo XLSX excede el número máximo de entradas internas permitidas.',
                    ]);
                }

                $totalUncompressed = 0;
                for ($i = 0; $i < $numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if ($stat) {
                        $totalUncompressed += ($stat['size'] ?? 0);
                        $entryName = strtolower($stat['name'] ?? '');

                        // Verificar que no contenga ejecutables ni scripts
                        if (preg_match('/\.(exe|bat|cmd|sh|php|phtml|vbs|js|jar)$/i', $entryName)) {
                            $zip->close();
                            throw ValidationException::withMessages([
                                'file' => 'El contenedor XLSX incluye archivos ejecutables o scripts no permitidos.',
                            ]);
                        }
                    }
                }

                $zip->close();

                if ($totalUncompressed > self::MAX_UNCOMPRESSED_OOXML_BYTES) {
                    throw ValidationException::withMessages([
                        'file' => 'El archivo XLSX excede el límite de descompresión segura (posible archivo malicioso).',
                    ]);
                }
            }
        }
    }
}
