<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

/**
 * Variables seguras para textos de acciones.
 * Whitelist cerrada, sustitución literal con strtr(), sin Blade ni eval().
 */
final class AutomationVariables
{
    public const VARIABLES = ['subject_name', 'subject_id', 'owner_name'];

    /**
     * @param  array<string, string>  $data
     */
    public static function render(string $text, array $data): string
    {
        $map = [];
        foreach (self::VARIABLES as $var) {
            $map['{{'.$var.'}}'] = (string) ($data[$var] ?? '');
        }

        return strtr($text, $map);
    }

    /** @return array<string, string> */
    public static function dataFor(Model $subject): array
    {
        $owner = $subject->owner ?? null;

        return [
            'subject_name' => self::subjectName($subject),
            'subject_id' => (string) ($subject->getKey() ?? ''),
            'owner_name' => $owner?->name ?? '',
        ];
    }

    public static function subjectName(Model $subject): string
    {
        if ($subject instanceof Lead || $subject instanceof Contact) {
            return trim(($subject->first_name ?? '').' '.($subject->last_name ?? ''));
        }

        if ($subject instanceof Opportunity) {
            return (string) ($subject->name ?? '');
        }

        if ($subject instanceof Task) {
            return (string) ($subject->title ?? '');
        }

        if ($subject instanceof Ticket) {
            return trim(($subject->number ?? '').' '.($subject->subject ?? ''));
        }

        if ($subject instanceof Quote || $subject instanceof Sale || $subject instanceof Invoice) {
            return (string) ($subject->number ?? '');
        }

        if ($subject instanceof Campaign) {
            return (string) ($subject->name ?? '');
        }

        if ($subject instanceof Activity) {
            return (string) ($subject->subject ?? '');
        }

        return 'Registro #'.($subject->getKey() ?? '');
    }
}
