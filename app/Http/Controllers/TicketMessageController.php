<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketMessageRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TicketMessageController extends Controller
{
    /**
     * Respuesta pública (reply) o nota interna (note). Las notas no cuentan
     * como primera respuesta. En tickets cerrados solo cabe reabrir.
     */
    public function store(StoreTicketMessageRequest $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validated();

        if ($ticket->status === 'closed') {
            return back()->with('error', 'El ticket está cerrado. Reábrelo para continuar la conversación.');
        }

        $isInternal = $data['type'] === 'note';

        DB::transaction(function () use ($request, $ticket, $data, $isInternal) {
            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'body' => $data['body'],
                'is_internal' => $isInternal,
            ]);

            // Solo respuestas reales mueven los relojes (notas y system, no).
            if (! $isInternal) {
                $ticket->update(array_merge(
                    ['last_reply_at' => now()],
                    $ticket->first_response_at ? [] : ['first_response_at' => now()]
                ));
            }
        });

        return back()->with('success', $isInternal ? 'Nota interna registrada.' : 'Respuesta registrada.');
    }
}
