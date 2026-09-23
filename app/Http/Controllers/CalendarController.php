<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Calendario comercial ligero (server-rendered, sin librerías externas).
     *
     * Eventos: tareas con due_at + reuniones (activities type=meeting con
     * scheduled_at). Nunca se usa created_at como fecha programada.
     * Timezone: configuración existente de la app (UTC).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $canTasks = $user->can('viewAny', Task::class);
        $canActivities = $user->can('viewAny', Activity::class);

        abort_unless($canTasks || $canActivities, 403);

        $validated = $request->validate([
            'view' => ['nullable', 'string', 'in:month,week,day'],
            'date' => ['nullable', 'date'],
        ]);

        $mode = $validated['view'] ?? 'month';
        $cursor = isset($validated['date']) ? Carbon::parse($validated['date']) : today();

        [$start, $end, $gridStart, $gridEnd] = $this->resolveRange($mode, $cursor);

        $events = [];

        if ($canTasks) {
            $tasks = Task::with(['assignee:id,name', 'taskable'])
                ->whereNotNull('due_at')
                ->whereDate('due_at', '>=', $start->toDateString())
                ->whereDate('due_at', '<=', $end->toDateString())
                ->orderBy('due_at')
                ->get();

            foreach ($tasks as $task) {
                $events[$task->due_at->toDateString()][] = [
                    'kind' => 'task',
                    'time' => $task->due_at->format('H:i'),
                    'title' => $task->title,
                    'url' => route('tasks.show', $task),
                    'meta' => trim(($task->assignee?->name ?? 'Sin asignar').' · '.$task->status),
                    'done' => in_array($task->status, ['completed', 'cancelled'], true),
                ];
            }
        }

        if ($canActivities) {
            $meetings = Activity::with(['user:id,name', 'subjectable'])
                ->where('type', 'meeting')
                ->whereNotNull('scheduled_at')
                ->whereDate('scheduled_at', '>=', $start->toDateString())
                ->whereDate('scheduled_at', '<=', $end->toDateString())
                ->orderBy('scheduled_at')
                ->get();

            foreach ($meetings as $meeting) {
                $events[$meeting->scheduled_at->toDateString()][] = [
                    'kind' => 'meeting',
                    'time' => $meeting->scheduled_at->format('H:i'),
                    'title' => $meeting->subject ?? 'Reunión',
                    'url' => route('activities.show', $meeting),
                    'meta' => trim(($meeting->user?->name ?? '—').($meeting->related_label ? ' · '.$meeting->related_label : '')),
                    'done' => $meeting->status === 'completed',
                ];
            }
        }

        foreach ($events as &$day) {
            usort($day, fn ($a, $b) => strcmp($a['time'], $b['time']));
        }
        unset($day);

        return view('calendar.index', [
            'mode' => $mode,
            'cursor' => $cursor,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'events' => $events,
            'canCreateTask' => $user->can('create', Task::class),
            'canCreateMeeting' => $user->can('create', Activity::class),
        ]);
    }

    /**
     * @return array{Carbon, Carbon, Carbon, Carbon} [start, end, gridStart, gridEnd]
     */
    private function resolveRange(string $mode, Carbon $cursor): array
    {
        if ($mode === 'day') {
            $day = $cursor->copy()->startOfDay();

            return [$day, $day->copy()->endOfDay(), $day, $day];
        }

        if ($mode === 'week') {
            $start = $cursor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $start->copy()->addDays(6)->endOfDay();

            return [$start, $end, $start, $end];
        }

        $start = $cursor->copy()->startOfMonth()->startOfDay();
        $end = $cursor->copy()->endOfMonth()->endOfDay();
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        return [$start, $end, $gridStart, $gridEnd];
    }
}
