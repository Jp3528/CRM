<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\Notifications\InternalNotificationService;
use App\Services\Settings\SettingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemindTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:remind-tasks {--date= : Fecha de evaluación (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera recordatorios para tareas vencidas y próximas a vencer evitando duplicados en el periodo';

    public function handle(InternalNotificationService $notificationService): int
    {
        $evalDate = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'))
            : SettingService::now();

        $dateKey = $evalDate->format('Y-m-d');
        $this->info("Evaluando recordatorios de tareas para la fecha: {$dateKey}");

        // Obtener tareas abiertas asignadas a usuarios activos
        $tasks = Task::with('assignee')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_at')
            ->whereHas('assignee', fn ($q) => $q->where('status', 'active'))
            ->get();

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($tasks as $task) {
            $dueDate = Carbon::parse($task->due_at)->format('Y-m-d');

            if ($dueDate < $dateKey) {
                $type = 'overdue';
            } elseif ($dueDate === $dateKey) {
                $type = 'due_soon';
            } else {
                continue;
            }

            $sent = $notificationService->sendTaskReminder($task, $type, $dateKey);
            if ($sent) {
                $sentCount++;
            } else {
                $skippedCount++;
            }
        }

        $this->info("Recordatorios completados. Enviados: {$sentCount}, Omitidos (deduplicados o inactivos): {$skippedCount}");

        return self::SUCCESS;
    }
}
