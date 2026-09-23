<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'string', 'max:30'],
            'category_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'string', 'max:30'],
            'company_id' => ['nullable', 'integer'],
            'channel' => ['nullable', 'string', 'max:30'],
            'preset' => ['nullable', 'string', 'in:mine,unassigned,open,pending,urgent'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        // Accesos rápidos (azúcar sobre los mismos filtros, sin lógica duplicada).
        $preset = $validated['preset'] ?? null;
        $status = $validated['status'] ?? null;
        $priority = $validated['priority'] ?? null;
        $assigned = $validated['assigned_to'] ?? null;
        match ($preset) {
            'mine' => $assigned = (string) $request->user()->id,
            'unassigned' => $assigned = 'unassigned',
            'open' => $status = 'open',
            'pending' => $status = 'pending',
            'urgent' => $priority = 'urgent',
            default => null,
        };

        $sort = in_array($validated['sort'] ?? '', Ticket::SORTABLE, true)
            ? $validated['sort']
            : 'updated_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $tickets = Ticket::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
                'category:id,name', 'assignee:id,name',
            ])
            ->search($validated['search'] ?? null)
            ->status($status)
            ->priority($priority)
            ->category($validated['category_id'] ?? null)
            ->assignedTo($assigned)
            ->forCompany($validated['company_id'] ?? null)
            ->channel($validated['channel'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'priority' => $validated['priority'] ?? '',
                'category_id' => $validated['category_id'] ?? '',
                'assigned_to' => $validated['assigned_to'] ?? '',
                'company_id' => $validated['company_id'] ?? '',
                'channel' => $validated['channel'] ?? '',
                'preset' => $preset ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
            'channels' => Ticket::CHANNELS,
            'categories' => TicketCategory::orderBy('name')->get(['id', 'name']),
            'users' => DataScope::filterableUsers($user),
            'companies' => Company::visibleTo($user)->orderBy('trade_name')->get(['id', 'trade_name']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Ticket::class);
        $preselectedCompanyId = $request->integer('company_id') ?: null;
        $preselectedContactId = $request->integer('contact_id') ?: null;

        if (! DataScope::isVisibleId($request->user(), Company::class, $preselectedCompanyId)) {
            $preselectedCompanyId = null;
        }

        if (! DataScope::isVisibleId($request->user(), Contact::class, $preselectedContactId)) {
            $preselectedContactId = null;
        }

        return view('tickets.create', array_merge(
            $this->formData(),
            [
                'preselectedCompanyId' => $preselectedCompanyId,
                'preselectedContactId' => $preselectedContactId,
            ]
        ));
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $data = $this->normalizeContactCompany($request->validated());
        DataScope::assertVisibleId($request->user(), Company::class, $data['company_id'] ?? null);
        DataScope::assertVisibleId($request->user(), Contact::class, $data['contact_id'] ?? null);
        DataScope::assertCanAssignUser($request->user(), $data['assigned_to'] ?? null);

        $ticket = DB::transaction(function () use ($data, $request) {
            $ticket = Ticket::create([
                'number' => 'TMP-'.Str::uuid(),
                'company_id' => $data['company_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'requester_name' => $data['requester_name'] ?? null,
                'requester_email' => $data['requester_email'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $request->user()->id,
                'category_id' => $data['category_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'status' => 'new',
                'priority' => $data['priority'],
                'channel' => $data['channel'],
            ]);

            $ticket->update([
                'number' => sprintf('TKT-%s-%06d', now()->format('Y'), $ticket->id),
            ]);

            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'type' => 'system',
                'body' => "Ticket creado por {$request->user()->name}.",
                'is_internal' => true,
            ]);

            return $ticket;
        });

        return redirect()->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->number} creado correctamente.");
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $user = request()->user();

        $ticket->load([
            'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
            'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
            'assignee:id,name,email',
            'creator:id,name',
            'category:id,name',
            'messages' => fn ($q) => $q->with(['user:id,name', 'contact:id,first_name,last_name'])->orderBy('created_at'),
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'canUpdate' => $user->can('update', $ticket),
            'canDelete' => $user->can('delete', $ticket),
            'canViewCompany' => DataScope::canViewModel($user, $ticket->company),
            'canViewContact' => DataScope::canViewModel($user, $ticket->contact),
        ]);
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return view('tickets.edit', array_merge(
            $this->formData($ticket),
            ['ticket' => $ticket]
        ));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $data = $this->normalizeContactCompany($request->validated());
        $actor = $request->user();
        DataScope::assertVisibleId($actor, Company::class, $data['company_id'] ?? null);
        DataScope::assertVisibleId($actor, Contact::class, $data['contact_id'] ?? null);
        DataScope::assertCanAssignUser($actor, $data['assigned_to'] ?? null, $ticket->assigned_to);

        DB::transaction(function () use ($data, $ticket, $actor) {
            $before = $ticket->only(['assigned_to', 'priority']);

            $ticket->update($data);

            // Eventos ante cambios relevantes (responsable, prioridad).
            if ((int) ($before['assigned_to'] ?? 0) !== (int) ($ticket->assigned_to ?? 0)) {
                $from = $before['assigned_to']
                    ? User::find($before['assigned_to'])?->name ?? '—'
                    : 'Sin asignar';
                $to = $ticket->assignee?->name ?? 'Sin asignar';
                $ticket->messages()->create([
                    'user_id' => $actor->id,
                    'type' => 'system',
                    'body' => "Responsable cambiado de {$from} a {$to} por {$actor->name}.",
                    'is_internal' => true,
                ]);
            }

            if (($before['priority'] ?? null) !== $ticket->priority) {
                $ticket->messages()->create([
                    'user_id' => $actor->id,
                    'type' => 'system',
                    'body' => "Prioridad cambiada de {$before['priority']} a {$ticket->priority} por {$actor->name}.",
                    'is_internal' => true,
                ]);
            }
        });

        return redirect()->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->number} actualizado correctamente.");
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket eliminado correctamente.');
    }

    /**
     * Mantiene coherente el par contacto/empresa: si se selecciona un contacto
     * ya vinculado y la empresa viene vacía, el ticket hereda esa empresa.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeContactCompany(array $data): array
    {
        if (! empty($data['contact_id']) && empty($data['company_id'])) {
            $contact = Contact::find($data['contact_id']);
            if ($contact?->company_id) {
                $data['company_id'] = $contact->company_id;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Ticket $ticket = null): array
    {
        return [
            'companies' => Company::visibleTo(auth()->user())->orderBy('trade_name')->get(['id', 'trade_name']),
            'contacts' => Contact::visibleTo(auth()->user())
                ->with(['company' => fn ($q) => $q->visibleTo(auth()->user())->select('id', 'trade_name', 'owner_id')])
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_id']),
            'categories' => TicketCategory::orderBy('name')->get(['id', 'name']),
            'assignees' => DataScope::filterableUsers(auth()->user(), $ticket?->assigned_to),
            'priorities' => Ticket::PRIORITIES,
            'channels' => Ticket::CHANNELS,
        ];
    }
}
