<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::with('user:id,name,email')->latest('id');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        $entityTypes = AuditLog::query()
            ->select('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type');

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $users = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('audit.index', [
            'logs' => $logs,
            'entityTypes' => $entityTypes,
            'actions' => $actions,
            'users' => $users,
            'filters' => $request->only(['entity_type', 'action', 'user_id', 'date_from', 'date_to']),
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): View|JsonResponse
    {
        $this->authorize('view', $auditLog);

        $auditLog->load('user:id,name,email');

        if ($request->wantsJson()) {
            return response()->json($auditLog);
        }

        return view('audit.show', [
            'log' => $auditLog,
        ]);
    }
}
