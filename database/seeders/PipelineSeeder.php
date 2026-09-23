<?php

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    /**
     * Etapas del pipeline "Ventas" en orden exacto.
     * [nombre, probabilidad, is_won, is_lost]
     */
    public const STAGES = [
        ['Prospecto', 10, false, false],
        ['Contactado', 20, false, false],
        ['Necesidad identificada', 40, false, false],
        ['Propuesta', 60, false, false],
        ['Negociación', 80, false, false],
        ['Ganada', 100, true, false],
        ['Perdida', 0, false, true],
    ];

    public function run(): void
    {
        $pipeline = Pipeline::firstOrCreate(
            ['name' => 'Ventas'],
            ['description' => 'Pipeline comercial principal.', 'is_default' => true, 'status' => 'active']
        );

        foreach (self::STAGES as $i => [$name, $probability, $isWon, $isLost]) {
            PipelineStage::updateOrCreate(
                ['pipeline_id' => $pipeline->id, 'name' => $name],
                [
                    'position' => $i + 1,
                    'probability' => $probability,
                    'status' => 'active',
                    'is_won' => $isWon,
                    'is_lost' => $isLost,
                ]
            );
        }
    }
}
