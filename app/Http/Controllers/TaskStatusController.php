<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskStatusController extends Controller
{
    public function complete(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        if ($task->status === 'completed') {
            return back()->with('status', 'La tarea ya estaba completada.');
        }

        if ($task->status === 'cancelled') {
            return back()->with('error', 'Reabre la tarea antes de completarla.');
        }

        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $this->logOnRelated($task, $request->user()->id, 'Tarea completada');

        return back()->with('success', 'Tarea completada correctamente.');
    }

    public function reopen(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        if (in_array($task->status, ['pending', 'in_progress'], true)) {
            return back()->with('status', 'La tarea ya está abierta.');
        }

        $task->update(['status' => 'pending', 'completed_at' => null]);
        $this->logOnRelated($task, $request->user()->id, 'Tarea reabierta');

        return back()->with('success', 'Tarea reabierta correctamente.');
    }

    public function cancel(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        if ($task->status === 'cancelled') {
            return back()->with('status', 'La tarea ya estaba cancelada.');
        }

        if ($task->status === 'completed') {
            return back()->with('error', 'Reabre la tarea antes de cancelarla.');
        }

        // Cancelada ≠ completada: completed_at permanece null.
        $task->update(['status' => 'cancelled', 'completed_at' => null]);
        $this->logOnRelated($task, $request->user()->id, 'Tarea cancelada');

        return back()->with('success', 'Tarea cancelada correctamente.');
    }

    private function logOnRelated(Task $task, int $userId, string $action): void
    {
        $related = $task->taskable;

        if ($related && method_exists($related, 'activities')) {
            $related->activities()->create([
                'type' => 'note',
                'subject' => "{$action}: {$task->title}",
                'status' => 'completed',
                'completed_at' => now(),
                'user_id' => $userId,
            ]);
        }
    }
}
