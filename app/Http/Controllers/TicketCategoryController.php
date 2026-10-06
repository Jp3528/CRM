<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TicketCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', TicketCategory::class);

        $query = TicketCategory::query()->withCount('tickets');

        if ($request->filled('search')) {
            $term = mb_strtolower(trim($request->input('search')));
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $categories = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('settings.categories.tickets', [
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', TicketCategory::class);

        $data = $request->validated();
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (TicketCategory::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        TicketCategory::create([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => $data['status'],
        ]);

        return redirect()->route('settings.ticket-categories.index')
            ->with('status', "Categoría de ticket '{$data['name']}' creada correctamente.");
    }

    public function update(UpdateCategoryRequest $request, TicketCategory $ticketCategory): RedirectResponse
    {
        $this->authorize('update', $ticketCategory);

        $data = $request->validated();
        $ticketCategory->update([
            'name' => $data['name'],
            'status' => $data['status'],
        ]);

        return redirect()->route('settings.ticket-categories.index')
            ->with('status', "Categoría de ticket '{$ticketCategory->name}' actualizada correctamente.");
    }

    public function destroy(TicketCategory $ticketCategory): RedirectResponse
    {
        $this->authorize('delete', $ticketCategory);

        if ($ticketCategory->tickets()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'No es posible eliminar una categoría que cuenta con tickets de soporte registrados. Puedes archivarla cambiando su estado a inactivo.',
            ]);
        }

        $name = $ticketCategory->name;
        $ticketCategory->delete();

        return redirect()->route('settings.ticket-categories.index')
            ->with('status', "Categoría '{$name}' eliminada correctamente.");
    }
}
