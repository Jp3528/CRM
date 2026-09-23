<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->loadMissing(['team', 'roles', 'permissions']);

        // Widgets comerciales moderados (solo productividad personal, sin analítica).
        $tasksToday = collect();
        $overdueTasks = collect();
        $upcomingTasks = collect();
        $upcomingMeetings = collect();

        if ($user->can('viewAny', Task::class)) {
            $base = Task::with('taskable')
                ->where('assigned_to', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled']);

            $tasksToday = (clone $base)->whereDate('due_at', today())->orderBy('due_at')->limit(5)->get();
            $overdueTasks = (clone $base)->where('due_at', '<', now())->orderBy('due_at')->limit(5)->get();
            $upcomingTasks = (clone $base)->whereDate('due_at', '>', today())
                ->whereDate('due_at', '<=', today()->addDays(7))->orderBy('due_at')->limit(5)->get();
        }

        if ($user->can('viewAny', Activity::class)) {
            $upcomingMeetings = Activity::with('subjectable')
                ->where('type', 'meeting')
                ->where('user_id', $user->id)
                ->where('scheduled_at', '>=', now())
                ->where('scheduled_at', '<=', now()->addDays(7))
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get();
        }

        return view('dashboard', [
            'user' => $user,
            'primaryRole' => $user->primaryRole(),
            'now' => now(),
            'tasksToday' => $tasksToday,
            'overdueTasks' => $overdueTasks,
            'upcomingTasks' => $upcomingTasks,
            'upcomingMeetings' => $upcomingMeetings,
            'canSeeTasks' => $user->can('viewAny', Task::class),
            'canSeeActivities' => $user->can('viewAny', Activity::class),
        ]);
    }
}
