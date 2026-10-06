<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DataImport;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Muestra la bandeja paginada de notificaciones del usuario autenticado.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $query = $user->notifications();

        if ($filter === 'unread') {
            $query = $user->unreadNotifications();
        } elseif ($filter === 'read') {
            $query = $user->readNotifications();
        }

        $notifications = $query->paginate(20)->withQueryString();

        // Verificar el acceso actual a las entidades vinculadas en la página para no exponer enlaces rotos o no autorizados
        $itemsWithAccess = $notifications->through(function ($notification) use ($user) {
            $data = $notification->data;
            $data['has_access'] = $this->verifyEntityAccess($user, $data);
            $notification->computed_data = $data;

            return $notification;
        });

        return view('notifications.index', [
            'notifications' => $itemsWithAccess,
            'unreadCount' => $user->unreadNotifications()->count(),
            'filter' => $filter,
        ]);
    }

    /**
     * Marca una notificación como leída asegurando que pertenezca al usuario autenticado.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();

        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        // Si se solicitó navegar a la entidad referenciada
        if ($request->boolean('navigate') && ! empty($notification->data['entity_type'])) {
            $hasAccess = $this->verifyEntityAccess($user, $notification->data);

            if ($hasAccess && ! empty($notification->data['url'])) {
                return redirect($notification->data['url']);
            }

            return redirect()->route('notifications.index')
                ->with('status', 'La entidad vinculada no está disponible o tu acceso actual ya no la permite.');
        }

        return back()->with('status', 'Notificación marcada como leída.');
    }

    /**
     * Marca todas las notificaciones pendientes como leídas.
     */
    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('status', 'Todas las notificaciones han sido marcadas como leídas.');
    }

    /**
     * Endpoint ligero para la campana de notificaciones de la barra superior.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadCount = $user->unreadNotifications()->count();
        $recent = $user->notifications()->latest()->take(5)->get()->map(function ($notif) use ($user) {
            $data = $notif->data;
            $data['has_access'] = $this->verifyEntityAccess($user, $data);

            return [
                'id' => $notif->id,
                'title' => $data['title'] ?? 'Aviso del sistema',
                'message' => $data['message'] ?? '',
                'read' => $notif->read_at !== null,
                'created_at' => $notif->created_at?->diffForHumans(),
                'url' => $data['has_access'] ? ($data['url'] ?? null) : null,
                'has_access' => $data['has_access'],
            ];
        });

        return response()->json([
            'unread_count' => $unreadCount,
            'recent' => $recent,
        ]);
    }

    /**
     * Vuelve a evaluar la autorización actual sobre la entidad sin revelar datos si fue revocada o eliminada.
     *
     * @param  array<string, mixed>  $data
     */
    protected function verifyEntityAccess(User $user, array $data): bool
    {
        $type = $data['entity_type'] ?? null;
        $id = $data['entity_id'] ?? null;

        if (! $type || ! $id) {
            return false;
        }

        return match ($type) {
            'task' => Task::whereKey($id)->exists() && $user->can('view', Task::find($id)),
            'opportunity' => Opportunity::whereKey($id)->exists() && $user->can('view', Opportunity::find($id)),
            'ticket' => Ticket::whereKey($id)->exists() && $user->can('view', Ticket::find($id)),
            'company' => Company::whereKey($id)->exists() && $user->can('view', Company::find($id)),
            'data_import' => DataImport::whereKey($id)->exists() && $user->can('view', DataImport::find($id)),
            default => false,
        };
    }
}
