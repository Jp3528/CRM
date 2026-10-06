<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PrivateDocument;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:backup {--verify : Comprobar la integridad del archivo inmediatamente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera un respaldo empaquetado de datos del sistema y documentos privados (P19C)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $zipFilename = "{$backupDir}/nexuscrm_backup_{$timestamp}.zip";

        $this->info("[NexusCRM] Iniciando respaldo del sistema: {$timestamp}");

        $zip = new ZipArchive;
        if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("No se pudo crear el archivo de respaldo en {$zipFilename}");

            return self::FAILURE;
        }

        // 1. Manifiesto y metadatos del estado del sistema
        $manifest = [
            'app_name' => config('app.name'),
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'database_connection' => config('database.default'),
            'entity_counts' => [
                'users' => User::count(),
                'companies' => Company::count(),
                'contacts' => Contact::count(),
                'leads' => Lead::count(),
                'opportunities' => Opportunity::count(),
                'quotes' => Quote::count(),
                'sales' => Sale::count(),
                'invoices' => Invoice::count(),
                'tickets' => Ticket::count(),
                'private_documents' => PrivateDocument::count(),
            ],
        ];

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Archivos privados de almacenamiento
        $privatePath = storage_path('app/private/documents');
        $documentsArchived = 0;
        if (File::isDirectory($privatePath)) {
            $files = File::allFiles($privatePath);
            foreach ($files as $file) {
                $relativePath = 'documents/'.$file->getRelativePathname();
                $zip->addFile($file->getRealPath(), $relativePath);
                $documentsArchived++;
            }
        }

        $zip->close();

        $fileSize = round(File::size($zipFilename) / 1024, 2);
        $this->info("✓ Archivo de respaldo generado exitosamente ({$fileSize} KB): {$zipFilename}");
        $this->line("- Documentos privados incluidos: {$documentsArchived}");

        // 3. Verificación de integridad si se solicita
        if ($this->option('verify')) {
            $this->info('[NexusCRM] Verificando integridad del archivo generado...');
            $verifyZip = new ZipArchive;
            if ($verifyZip->open($zipFilename) === true) {
                $manifestContent = $verifyZip->getFromName('manifest.json');
                if ($manifestContent && json_decode($manifestContent, true)) {
                    $this->info('✓ Verificación de integridad exitosa: Manifiesto válido y legible.');
                } else {
                    $this->error('✗ Fallo en la verificación del manifiesto de respaldo.');
                    $verifyZip->close();

                    return self::FAILURE;
                }
                $verifyZip->close();
            } else {
                $this->error('✗ No se pudo abrir el archivo de respaldo para verificación.');

                return self::FAILURE;
            }
        }

        $this->line('');
        $this->line('Instrucciones de Recuperación:');
        $this->line('  1. Extraer manifest.json para auditar conteos de entidades.');
        $this->line('  2. Restaurar carpeta documents/ en storage/app/private/documents/.');
        $this->line('  3. Mantener el APP_KEY original del entorno.');

        return self::SUCCESS;
    }
}
