<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Communication;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Envíos simulados: registro interno, sin proveedor externo.
 *
 * - Una comunicación individual: DB::transaction (communication + member).
 * - Masivo: límite de 500 miembros por operación, en chunks de 100
 *   dentro de una transacción razonable (documentado, sin queues aún).
 */
final class CampaignCommunicationService
{
    public const BULK_LIMIT = 500;

    public const BULK_CHUNK = 100;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function simulateSingle(Communication $communication, User $actor): Communication
    {
        abort_unless(in_array($communication->status, ['draft', 'queued'], true), 422, 'Solo se puede simular desde borrador o cola.');

        return DB::transaction(function () use ($communication, $actor) {
            $communication->update([
                'status' => 'simulated_sent',
                'sent_at' => now(),
                'failed_at' => null,
                'metadata' => array_merge($communication->metadata ?? [], [
                    'simulated' => true,
                    'simulated_by' => $actor->id,
                    'simulated_at' => now()->toIso8601String(),
                    'provider' => null,
                ]),
            ]);

            if ($communication->campaign_member_id) {
                $member = CampaignMember::find($communication->campaign_member_id);

                if ($member && $member->status === 'pending') {
                    $member->update(['status' => 'sent']);
                }
            }

            return $communication->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  channel/subject/body/template_id/owner_id
     * @return array{created: int, skipped: int}
     */
    public static function bulkSimulate(Campaign $campaign, Collection $members, array $data, User $actor): array
    {
        if ($members->count() > self::BULK_LIMIT) {
            abort(422, 'Límite superado: máximo '.self::BULK_LIMIT.' miembros por operación.');
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($campaign, $members, $data, $actor, &$created, &$skipped) {
            foreach ($members->chunk(self::BULK_CHUNK) as $chunk) {
                foreach ($chunk as $member) {
                    /** @var CampaignMember $member */
                    if ($member->status === 'unsubscribed') {
                        $skipped++;
                        continue;
                    }

                    if (! DataScope::canViewCampaignMember($actor, $member)) {
                        $skipped++;
                        continue;
                    }

                    $target = $member->target();

                    if (! $target) {
                        $skipped++;
                        continue;
                    }

                    Communication::create([
                        'campaign_id' => $campaign->id,
                        'campaign_member_id' => $member->id,
                        'contact_id' => $member->member_type === 'contact' ? $member->member_id : null,
                        'lead_id' => $member->member_type === 'lead' ? $member->member_id : null,
                        'template_id' => $data['template_id'] ?? null,
                        'channel' => $data['channel'],
                        'direction' => 'outbound',
                        'subject' => $data['subject'] ?? null,
                        'body' => $data['body'],
                        'status' => 'simulated_sent',
                        'owner_id' => $data['owner_id'] ?? $campaign->owner_id ?? $actor->id,
                        'created_by' => $actor->id,
                        'sent_at' => now(),
                        'metadata' => [
                            'simulated' => true,
                            'simulated_by' => $actor->id,
                            'simulated_at' => now()->toIso8601String(),
                            'bulk_campaign_id' => $campaign->id,
                            'provider' => null,
                        ],
                    ]);

                    if ($member->status === 'pending') {
                        $member->update(['status' => 'sent']);
                    }

                    $created++;
                }
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }
}
