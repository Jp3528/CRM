<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageTemplateRequest;
use App\Http\Requests\UpdateMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Support\DataScope;
use App\Support\TemplateVariables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', MessageTemplate::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:30'],
            'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', MessageTemplate::SORTABLE, true)
            ? $validated['sort']
            : 'updated_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $templates = MessageTemplate::query()
            ->visibleTo($user)
            ->with(['owner:id,name'])
            ->search($validated['search'] ?? null)
            ->channel($validated['channel'] ?? null)
            ->status($validated['status'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('templates.index', [
            'templates' => $templates,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'channel' => $validated['channel'] ?? '',
                'status' => $validated['status'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'channels' => MessageTemplate::CHANNELS,
            'statuses' => MessageTemplate::STATUSES,
            'owners' => DataScope::filterableUsers($user),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MessageTemplate::class);

        return view('templates.create', [
            'owners' => DataScope::filterableUsers(auth()->user()),
            'channels' => MessageTemplate::CHANNELS,
            'statuses' => MessageTemplate::STATUSES,
            'variables' => TemplateVariables::VARIABLES,
        ]);
    }

    public function store(StoreMessageTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = DataScope::normalizeOwnerId($request->user(), $data['owner_id'] ?? null);
        DataScope::assertCanAssignUser($request->user(), $data['owner_id'] ?? null);
        $data['status'] ??= 'draft';
        $data['created_by'] = $request->user()->id;

        $template = MessageTemplate::create($data);

        return redirect()->route('templates.show', $template)
            ->with('success', 'Plantilla creada correctamente.');
    }

    public function show(MessageTemplate $messageTemplate, Request $request): View
    {
        // El parámetro de ruta es {template} por legibilidad; resolvemos ambos.
        $template = $messageTemplate;
        $this->authorize('view', $template);

        $template->load(['owner:id,name', 'creator:id,name']);

        $preview = $template->preview();

        return view('templates.show', [
            'template' => $template,
            'preview' => $preview,
            'variables' => TemplateVariables::VARIABLES,
            'canUpdate' => $request->user()->can('update', $template),
        ]);
    }

    public function edit(MessageTemplate $messageTemplate): View
    {
        $this->authorize('update', $messageTemplate);

        return view('templates.edit', [
            'template' => $messageTemplate,
            'owners' => DataScope::filterableUsers(auth()->user(), $messageTemplate->owner_id),
            'channels' => MessageTemplate::CHANNELS,
            'statuses' => MessageTemplate::STATUSES,
            'variables' => TemplateVariables::VARIABLES,
        ]);
    }

    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = DataScope::normalizeOwnerId($request->user(), $data['owner_id'] ?? null, $messageTemplate->owner_id);
        DataScope::assertCanAssignUser($request->user(), $data['owner_id'] ?? null, $messageTemplate->owner_id);

        $messageTemplate->update($data);

        return redirect()->route('templates.show', $messageTemplate)
            ->with('success', 'Plantilla actualizada correctamente.');
    }

    public function destroy(MessageTemplate $messageTemplate): RedirectResponse
    {
        $this->authorize('delete', $messageTemplate);

        $messageTemplate->delete();

        return redirect()->route('templates.index')
            ->with('success', 'Plantilla eliminada correctamente.');
    }
}
