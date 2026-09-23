<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertLeadRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Services\Leads\LeadConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeadConversionController extends Controller
{
    public function create(Lead $lead): View
    {
        $this->authorize('convert', $lead);

        if ($lead->isConverted()) {
            abort(422, 'Este lead ya fue convertido.');
        }

        return view('leads.convert', [
            'lead' => $lead->load('owner:id,name'),
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'contacts' => Contact::with('company:id,trade_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_id', 'email']),
            'owners' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'suggestedOpportunityName' => 'Oportunidad — '.($lead->company_name ?: $lead->full_name),
        ]);
    }

    public function store(ConvertLeadRequest $request, Lead $lead, LeadConversionService $service): RedirectResponse
    {
        $result = $service->convert($lead, $request->user(), $request->validated());

        return redirect()->route('leads.show', $lead->fresh())
            ->with('success', 'Lead convertido correctamente a '
                .$result['company']->trade_name.' / '.$result['contact']->full_name
                .($result['opportunity'] ? ' con oportunidad '.$result['opportunity']->name.'.' : '.'));
    }
}
