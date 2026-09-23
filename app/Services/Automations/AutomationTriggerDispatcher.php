<?php

namespace App\Services\Automations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Punto único de entrada de eventos hacia el motor.
 *
 * Ejecución AFTER COMMIT: si hay transacción abierta, el evento se procesa
 * al confirmar; si no, de inmediato. Los tests habilitan modo síncrono
 * explícito (AutomationTriggerDispatcher::$sync = true) porque
 * RefreshDatabase mantiene una transacción externa abierta.
 */
final class AutomationTriggerDispatcher
{
    public static bool $sync = false;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $chain
     */
    public static function dispatch(
        string $trigger,
        Model $subject,
        array $context = [],
        ?int $triggeredById = null,
        ?string $correlationId = null,
        int $depth = 0,
        ?string $eventUuid = null,
        array $chain = []
    ): void {
        $job = function () use ($trigger, $subject, $context, $triggeredById, $correlationId, $depth, $eventUuid, $chain) {
            // Refresca el subject tras el commit para evaluar estado final.
            $fresh = $subject->fresh();

            if (! $fresh) {
                return;
            }

            app(AutomationRunner::class)->handle(
                $trigger, $fresh, $context, $triggeredById,
                $correlationId, $depth, $eventUuid, $chain
            );
        };

        if (static::$sync || DB::transactionLevel() === 0) {
            $job();

            return;
        }

        DB::afterCommit($job);
    }

    public static function enableSync(): void
    {
        static::$sync = true;
    }

    public static function disableSync(): void
    {
        static::$sync = false;
    }
}
