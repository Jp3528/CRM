<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;
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

        // Cotizaciones pendientes y próximas a vencer (conteos + top 5, sin analítica).
        $pendingQuotes = collect();
        $expiringQuotes = collect();

        if ($user->can('viewAny', Quote::class)) {
            $pendingQuotes = Quote::with('company:id,trade_name')
                ->whereIn('status', ['draft', 'sent'])
                ->orderBy('valid_until')
                ->limit(5)
                ->get();
            $expiringQuotes = Quote::with('company:id,trade_name')
                ->whereIn('status', ['draft', 'sent'])
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '>=', today())
                ->whereDate('valid_until', '<=', today()->addDays(7))
                ->orderBy('valid_until')
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
            'pendingQuotes' => $pendingQuotes,
            'expiringQuotes' => $expiringQuotes,
            'canSeeQuotes' => $user->can('viewAny', Quote::class),
            'recentSales' => $user->can('viewAny', Sale::class)
                ? Sale::with('company:id,trade_name')->latest()->limit(5)->get()
                : collect(),
            'pendingInvoices' => $user->can('viewAny', Invoice::class)
                ? Invoice::with('company:id,trade_name')->whereIn('status', ['draft', 'sent'])->latest()->limit(5)->get()
                : collect(),
            'overdueInvoices' => $user->can('viewAny', Invoice::class)
                ? Invoice::with('company:id,trade_name')->whereNotNull('due_date')
                    ->whereDate('due_date', '<', today())
                    ->whereNotIn('status', ['paid', 'cancelled'])->orderBy('due_date')->limit(5)->get()
                : collect(),
            'canSeeSales' => $user->can('viewAny', Sale::class),
            'canSeeInvoices' => $user->can('viewAny', Invoice::class),
            'openTickets' => $user->can('viewAny', Ticket::class)
                ? Ticket::whereIn('status', ['new', 'open', 'pending'])->count()
                : 0,
            'urgentTickets' => $user->can('viewAny', Ticket::class)
                ? Ticket::with('company:id,trade_name')->where('priority', 'urgent')
                    ->whereNotIn('status', ['resolved', 'closed'])->latest()->limit(5)->get()
                : collect(),
            'unassignedTickets' => $user->can('viewAny', Ticket::class)
                ? Ticket::whereNull('assigned_to')->whereNotIn('status', ['resolved', 'closed'])->count()
                : 0,
            'pendingTickets' => $user->can('viewAny', Ticket::class)
                ? Ticket::where('status', 'pending')->count()
                : 0,
            'canSeeTickets' => $user->can('viewAny', Ticket::class),
        ]);
    }
}
