<?php

namespace App\Services\Imports;

use InvalidArgumentException;
use RuntimeException;

/**
 * Parser de archivos CSV con soporte de comillas, BOM UTF-8 y preservación de cadenas.
 *
 * Mantiene intactos ceros iniciales en teléfonos y documentos.
 * Limita tamaño a 5MB y 2000 filas (configurables).
 */
class CsvParser
{
    public const MAX_BYTES = 5242880; // 5 MB

    public const MAX_ROWS = 2000;

    public const MAX_COLUMNS = 50;

    /**
     * Detecta el delimitador y elimina el BOM UTF-8 si existe.
     *
     * @return array{delimiter: string, has_bom: bool}
     */
    public static function detectDelimiterAndBom(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            throw new RuntimeException('No se pudo abrir el archivo CSV para lectura.');
        }

        $sample = fread($handle, 4096);
        fclose($handle);

        $hasBom = str_starts_with($sample, "\xEF\xBB\xBF");
        if ($hasBom) {
            $sample = substr($sample, 3);
        }

        // Analizar primera línea
        $firstLine = strtok($sample, "\r\n");
        if ($firstLine === false || $firstLine === '') {
            return ['delimiter' => ',', 'has_bom' => $hasBom];
        }

        $delimiters = [',', ';', "\t"];
        $counts = [];

        foreach ($delimiters as $delim) {
            $counts[$delim] = substr_count($firstLine, $delim);
        }

        arsort($counts);
        $bestDelim = key($counts);

        return [
            'delimiter' => $counts[$bestDelim] > 0 ? $bestDelim : ',',
            'has_bom' => $hasBom,
        ];
    }

    /**
     * Lee la cabecera y una muestra de hasta $maxRows filas para previsualización.
     *
     * @return array{
     *     headers: array<int, string>,
     *     rows: array<int, array<int, string>>,
     *     total_rows: int,
     *     delimiter: string
     * }
     */
    public function parsePreview(string $filePath, int $maxRows = 20): array
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException('El archivo CSV especificado no existe.');
        }

        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_BYTES) {
            throw new InvalidArgumentException('El archivo excede el tamaño máximo permitido de 5 MB.');
        }

        $detected = self::detectDelimiterAndBom($filePath);
        $delimiter = $detected['delimiter'];

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        // Si tiene BOM, saltarlo
        if ($detected['has_bom']) {
            fseek($handle, 3);
        }

        // Leer cabecera
        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (! $rawHeaders || empty(array_filter($rawHeaders))) {
            fclose($handle);
            throw new InvalidArgumentException('El archivo CSV está vacío o no contiene una fila de cabeceras válida.');
        }

        // Limpiar cabeceras
        $headers = [];
        foreach ($rawHeaders as $idx => $header) {
            $cleaned = trim((string) $header);
            // Si el primer header aún tiene BOM residual
            $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', $cleaned);
            $headers[$idx] = $cleaned ?: "columna_{$idx}";
        }

        if (count($headers) > self::MAX_COLUMNS) {
            fclose($handle);
            throw new InvalidArgumentException('El archivo CSV excede el límite máximo de '.self::MAX_COLUMNS.' columnas.');
        }

        $previewRows = [];
        $totalRows = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Ignorar líneas vacías
            if (count($data) === 1 && $data[0] === null) {
                continue;
            }

            $totalRows++;

            if ($totalRows > self::MAX_ROWS) {
                fclose($handle);
                throw new InvalidArgumentException('El archivo excede el límite máximo de '.self::MAX_ROWS.' filas.');
            }

            if (count($previewRows) < $maxRows) {
                $cleanRow = [];
                foreach ($headers as $colIdx => $colName) {
                    $val = isset($data[$colIdx]) ? (string) $data[$colIdx] : '';
                    // Preservar como texto puro (sin castear números ni quitar ceros iniciales)
                    $cleanRow[$colIdx] = trim($val);
                }
                $previewRows[] = $cleanRow;
            }
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $previewRows,
            'total_rows' => $totalRows,
            'delimiter' => $delimiter,
        ];
    }

    /**
     * Generador para iterar todas las filas del archivo de forma eficiente.
     *
     * @return \Generator<int, array{row_number: int, data: array<string, string>}>
     */
    public function streamRows(string $filePath, array $columnMapping, ?string $delimiter = null): \Generator
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException('El archivo CSV no existe.');
        }

        $detected = self::detectDelimiterAndBom($filePath);
        $delimiter = $delimiter ?: $detected['delimiter'];

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        if ($detected['has_bom']) {
            fseek($handle, 3);
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (! $rawHeaders) {
            fclose($handle);

            return;
        }

        $headers = [];
        foreach ($rawHeaders as $idx => $header) {
            $cleaned = trim((string) $header);
            $cleaned = preg_replace('/^\xEF\xBB\xBF/', '', $cleaned);
            $headers[$idx] = $cleaned ?: "columna_{$idx}";
        }

        $rowNumber = 1; // La cabecera fue la fila 1

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) === 1 && $data[0] === null) {
                continue;
            }

            $rowNumber++;

            if ($rowNumber - 1 > self::MAX_ROWS) {
                break;
            }

            // Mapear columnas según el mapping proporcionado
            // $columnMapping viene como: ['columna_csv' => 'campo_modelo'] o [0 => 'campo_modelo']
            $mappedData = [];
            foreach ($columnMapping as $sourceCol => $targetField) {
                if (empty($targetField) || $targetField === '__ignore__') {
                    continue;
                }

                $value = '';
                if (is_numeric($sourceCol) && isset($data[(int) $sourceCol])) {
                    $value = (string) $data[(int) $sourceCol];
                } elseif (is_string($sourceCol)) {
                    $headerIndex = array_search($sourceCol, $headers, true);
                    if ($headerIndex !== false && isset($data[$headerIndex])) {
                        $value = (string) $data[$headerIndex];
                    }
                }

                $mappedData[$targetField] = trim($value);
            }

            yield [
                'row_number' => $rowNumber,
                'data' => $mappedData,
            ];
        }

        fclose($handle);
    }
}
