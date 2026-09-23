<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\Tickets\TicketStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketStatusController extends Controller
{
    public function __construct(private TicketStatusService $service) {}

    public function open(Request $request, Ticket $ticket): RedirectResponse
    {
        return $this->move($request, $ticket, 'open', 'en atención');
    }

    public function pending(Request $request, Ticket $ticket): RedirectResponse
    {
        return $this->move($request, $ticket, 'pending', 'en espera');
    }

    public function resolve(Request $request, Ticket $ticket): RedirectResponse
    {
        return $this->move($request, $ticket, 'resolved', 'resuelto');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        return $this->move($request, $ticket, 'open', 'reabierto');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        return $this->move($request, $ticket, 'closed', 'cerrado');
    }

    private function move(Request $request, Ticket $ticket, string $to, string $label): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $result = $this->service->transition($ticket, $to, $request->user());

        if (! $result['changed']) {
            return back()->with('status', "El ticket ya estaba {$label}.");
        }

        return back()->with('success', "Ticket {$result['ticket']->number} {$label} correctamente.");
    }
}
