<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ResetDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:demo-reset {--force : Forzar reinicio en entornos autorizados}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reinicia de forma segura los datos de demostración en bases de datos autorizadas (P19A)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 1. Guard absoluto: jamás en producción
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->error('ERROR CRÍTICO: El comando crm:demo-reset no puede ejecutarse en entorno de producción.');

            return self::FAILURE;
        }

        // 2. Guard de base de datos autorizada
        $connection = Config::get('database.default');
        $databaseName = (string) Config::get("database.connections.{$connection}.database");

        $currentEnv = config('app.env', app()->environment());
        $isAuthorized = in_array($currentEnv, ['demo', 'local', 'testing'], true)
            || str_contains(strtolower($databaseName), 'demo')
            || str_contains(strtolower($databaseName), 'test')
            || $databaseName === ':memory:';

        if (! $isAuthorized && ! $this->option('force')) {
            $this->error("ABORTADO: La base de datos '{$databaseName}' en entorno '".app()->environment()."' no está identificada como entorno demo o de pruebas.");
            $this->line('Use la opción --force solo si está completamente seguro en un entorno de desarrollo aislado.');

            return self::FAILURE;
        }

        $this->info('[NexusCRM] Ejecutando reinicio seguro de dataset de demostración...');

        // Ejecutar DemoSeeder
        $this->call('db:seed', [
            '--class' => DemoSeeder::class,
            '--force' => true,
        ]);

        $this->info('✓ Dataset de demostración inicializado exitosamente.');
        $this->table(
            ['Rol Demo', 'Correo de Acceso', 'Contraseña'],
            [
                ['Superadministrador', 'superadmin@demo.test', 'password'],
                ['Administrador', 'admin@demo.test', 'password'],
                ['Supervisor Corp.', 'supervisor.corp@demo.test', 'password'],
                ['Vendedor Corp.', 'marcos.diaz@demo.test', 'password'],
                ['Supervisor Pyme', 'supervisor.pyme@demo.test', 'password'],
                ['Vendedora Pyme', 'valeria.ruiz@demo.test', 'password'],
                ['Soporte Técnico', 'david.soporte@demo.test', 'password'],
                ['Auditor Consulta (Solo Lectura)', 'auditor.consulta@demo.test', 'password'],
            ]
        );

        return self::SUCCESS;
    }
}
