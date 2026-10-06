<?php

namespace App\Services\Imports;

use App\Models\Company;
use App\Models\Contact;
use App\Models\DataImport;
use App\Models\DataImportError;
use App\Models\Lead;
use App\Models\User;
use App\Support\DataScope;
use App\Support\ImportCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ImportService
{
    public function __construct(
        protected CsvParser $parser = new CsvParser,
    ) {}

    /**
     * Almacena temporalmente el archivo subido en almacenamiento privado seguro.
     *
     * @return array{temp_path: string, original_filename: string, file_size: int, headers: string[]}
     */
    public function storeTempFile(UploadedFile $file, string $module): array
    {
        if (! in_array($module, ImportCatalog::MODULES, true)) {
            throw new InvalidArgumentException("Módulo '{$module}' no admitido para importación.");
        }

        $originalFilename = $file->getClientOriginalName();
        $safeName = 'import_'.Str::random(32).'.csv';
        $path = $file->storeAs('imports/temp', $safeName, 'local');

        if (! $path) {
            throw new RuntimeException('Error al guardar el archivo en almacenamiento temporal.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $previewData = $this->parser->parsePreview($fullPath, 5);

        return [
            'temp_path' => $path,
            'original_filename' => $originalFilename,
            'file_size' => $file->getSize(),
            'headers' => $previewData['headers'],
            'delimiter' => $previewData['delimiter'],
            'total_rows' => $previewData['total_rows'],
        ];
    }

    /**
     * Genera la vista previa de las primeras 20 filas y registra el token de confirmación.
     *
     * @param  array<string|int, string>  $columnMapping
     * @return array{
     *     preview_rows: array<int, array{row_number: int, data: array<string, mixed>, errors: array<string, string>, is_valid: bool}>,
     *     total_rows: int,
     *     valid_count: int,
     *     error_count: int,
     *     token: string,
     *     token_expires_at: string
     * }
     */
    public function generatePreview(
        string $module,
        string $tempPath,
        string $originalFilename,
        array $columnMapping,
        User $user
    ): array {
        if (! Storage::disk('local')->exists($tempPath)) {
            throw new InvalidArgumentException('El archivo temporal de importación ha caducado o no existe.');
        }

        $fullPath = Storage::disk('local')->path($tempPath);
        $detected = CsvParser::detectDelimiterAndBom($fullPath);
        $delimiter = $detected['delimiter'];

        $previewRows = [];
        $validCount = 0;
        $errorCount = 0;
        $totalRows = 0;

        foreach ($this->parser->streamRows($fullPath, $columnMapping, $delimiter) as $item) {
            $totalRows++;
            $rowErrors = ImportCatalog::validateRow($module, $item['data'], $item['row_number'], $user);
            $isValid = empty($rowErrors);

            if ($isValid) {
                $validCount++;
            } else {
                $errorCount++;
            }

            if (count($previewRows) < 20) {
                $previewRows[] = [
                    'row_number' => $item['row_number'],
                    'data' => $item['data'],
                    'errors' => $rowErrors,
                    'is_valid' => $isValid,
                ];
            }
        }

        // Generar token de confirmación criptográfico con expiración de 30 minutos
        $token = 'imp_tok_'.Str::random(40);
        $tokenData = [
            'user_id' => $user->id,
            'module' => $module,
            'temp_path' => $tempPath,
            'original_filename' => $originalFilename,
            'column_mapping' => $columnMapping,
            'delimiter' => $delimiter,
            'total_rows' => $totalRows,
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("import_confirm_{$token}", $tokenData, now()->addMinutes(30));

        return [
            'preview_rows' => $previewRows,
            'total_rows' => $totalRows,
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'token' => $token,
            'token_expires_at' => now()->addMinutes(30)->toDateTimeString(),
        ];
    }

    /**
     * Confirma y procesa la importación completa con validación por fila,
     * transacciones atómicas e idempotencia estricta.
     */
    public function confirmImport(string $token, User $user): DataImport
    {
        // Obtener y eliminar atómicamente el token para evitar doble confirmación simultánea
        $cacheKey = "import_confirm_{$token}";
        $tokenData = Cache::pull($cacheKey);

        if (! $tokenData) {
            throw new InvalidArgumentException('El token de confirmación es inválido o ya ha sido utilizado.');
        }

        if ((int) $tokenData['user_id'] !== (int) $user->id) {
            throw new InvalidArgumentException('No tienes autorización para confirmar esta importación.');
        }

        $module = $tokenData['module'];
        $tempPath = $tokenData['temp_path'];
        $originalFilename = $tokenData['original_filename'];
        $columnMapping = $tokenData['column_mapping'];
        $delimiter = $tokenData['delimiter'];

        if (! Storage::disk('local')->exists($tempPath)) {
            throw new RuntimeException('El archivo temporal de importación ya no está disponible.');
        }

        $fullPath = Storage::disk('local')->path($tempPath);

        // Crear registro DataImport
        $import = DataImport::create([
            'module' => $module,
            'original_filename' => $originalFilename,
            'status' => 'processing',
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'created_by' => $user->id,
            'started_at' => now(),
            'metadata' => [
                'delimiter' => $delimiter,
                'mapping' => $columnMapping,
            ],
        ]);

        $successfulRows = 0;
        $failedRows = 0;
        $totalProcessed = 0;

        try {
            foreach ($this->parser->streamRows($fullPath, $columnMapping, $delimiter) as $item) {
                $totalProcessed++;
                $rowNumber = $item['row_number'];
                $rowData = $item['data'];

                // Validar fila contra reglas de negocio y DataScope
                $rowErrors = ImportCatalog::validateRow($module, $rowData, $rowNumber, $user);

                if (! empty($rowErrors)) {
                    $failedRows++;
                    foreach ($rowErrors as $field => $msg) {
                        DataImportError::create([
                            'data_import_id' => $import->id,
                            'row_number' => $rowNumber,
                            'field' => $field,
                            'message' => mb_substr($msg, 0, 500),
                        ]);
                    }

                    continue;
                }

                // Crear registro dentro de una transacción individual por fila
                try {
                    DB::transaction(function () use ($module, $rowData, $user) {
                        $this->createEntity($module, $rowData, $user);
                    });
                    $successfulRows++;
                } catch (\Throwable $e) {
                    $failedRows++;
                    DataImportError::create([
                        'data_import_id' => $import->id,
                        'row_number' => $rowNumber,
                        'field' => 'general',
                        'message' => 'Error al persistir registro: '.mb_substr($e->getMessage(), 0, 450),
                    ]);
                }
            }

            // Actualizar estado final
            $finalStatus = 'completed';
            if ($failedRows > 0 && $successfulRows > 0) {
                $finalStatus = 'completed_with_errors';
            } elseif ($failedRows > 0 && $successfulRows === 0) {
                $finalStatus = 'failed';
            }

            $import->update([
                'status' => $finalStatus,
                'total_rows' => $totalProcessed,
                'successful_rows' => $successfulRows,
                'failed_rows' => $failedRows,
                'finished_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'finished_at' => now(),
                'metadata' => array_merge($import->metadata ?? [], ['error' => $e->getMessage()]),
            ]);
            throw $e;
        } finally {
            // Limpieza segura del archivo temporal
            Storage::disk('local')->delete($tempPath);
        }

        return $import;
    }

    /**
     * Persiste la entidad correspondiente suprimiendo disparadores automáticos.
     */
    protected function createEntity(string $module, array $data, User $user): void
    {
        // Resolver owner_id respetando DataScope
        $ownerId = ! empty($data['owner_id']) ? (int) $data['owner_id'] : $user->id;
        $owner = User::find($ownerId);
        if (! $owner || ! DataScope::canAccessOwner($user, $owner)) {
            $ownerId = $user->id;
        }

        $data['owner_id'] = $ownerId;

        // Limpiar campos no asignables o prohibidos
        foreach (ImportCatalog::PROHIBITED_FIELDS as $prohibited) {
            unset($data[$prohibited]);
        }

        // Crear la entidad suprimiendo eventos para no disparar automatizaciones ni campañas
        match ($module) {
            'companies' => Company::withoutEvents(function () use ($data) {
                Company::create($data);
            }),
            'contacts' => Contact::withoutEvents(function () use ($data) {
                Contact::create($data);
            }),
            'leads' => Lead::withoutEvents(function () use ($data) {
                // Estado inicial siempre 'new' para importaciones
                $data['status'] = 'new';
                Lead::create($data);
            }),
            default => throw new InvalidArgumentException("Módulo '{$module}' no soportado."),
        };
    }

    /**
     * Genera el CSV de errores sanitizando fórmulas maliciosas.
     */
    public function generateErrorsCsv(DataImport $import): string
    {
        $errors = $import->errors()->get(['row_number', 'field', 'message']);

        $output = fopen('php://memory', 'r+');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Fila', 'Campo', 'Mensaje']);

        foreach ($errors as $err) {
            fputcsv($output, [
                $err->row_number,
                self::neutralizeFormula($err->field ?? ''),
                self::neutralizeFormula($err->message),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Neutraliza posibles fórmulas maliciosas en hojas de cálculo.
     * Si la cadena empieza con =, +, -, @, se antepone una comilla simple.
     */
    public static function neutralizeFormula(string $value): string
    {
        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
