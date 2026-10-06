<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Setting::class);

        $values = [];
        foreach (SettingService::CATALOG as $groupName => $group) {
            foreach ($group as $key => $meta) {
                $values[$key] = SettingService::get($key);
            }
        }

        // Timezones recomendadas para Iberoamérica y UTC
        $commonTimezones = [
            'America/Lima' => 'America/Lima (UTC-5 - Perú)',
            'America/Bogota' => 'America/Bogota (UTC-5 - Colombia)',
            'America/Santiago' => 'America/Santiago (Chile)',
            'America/Mexico_City' => 'America/Mexico_City (México)',
            'America/Buenos_Aires' => 'America/Buenos_Aires (Argentina)',
            'America/Caracas' => 'America/Caracas (Venezuela)',
            'America/New_York' => 'America/New_York (EE.UU. Este)',
            'Europe/Madrid' => 'Europe/Madrid (España)',
            'UTC' => 'UTC (Tiempo Universal Coordinado)',
        ];

        $currencies = [
            'USD' => 'USD - Dólar estadounidense ($)',
            'PEN' => 'PEN - Sol peruano (S/)',
            'COP' => 'COP - Peso colombiano ($)',
            'EUR' => 'EUR - Euro (€)',
        ];

        return view('settings.index', [
            'catalog' => SettingService::CATALOG,
            'values' => $values,
            'timezones' => $commonTimezones,
            'currencies' => $currencies,
            'currentTime' => SettingService::now()->format('d/m/Y H:i:s T'),
            'todayRange' => SettingService::todayRangeUtc(),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            SettingService::set($key, $value);
        }

        return redirect()->route('settings.index')
            ->with('status', 'Configuración del sistema actualizada correctamente.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        foreach (SettingService::CATALOG as $group) {
            foreach ($group as $key => $meta) {
                SettingService::set($key, $meta['default']);
            }
        }

        SettingService::clearCache();

        return redirect()->route('settings.index')
            ->with('status', 'Configuración restaurada a los valores predeterminados del catálogo.');
    }
}
