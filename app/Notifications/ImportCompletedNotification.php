<?php

namespace App\Notifications;

use App\Models\DataImport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ImportCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DataImport $import,
        public ?string $dedupKey = null
    ) {}

    public function via(object $notifiable): array
    {
        if (isset($notifiable->status) && $notifiable->status !== 'active') {
            return [];
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Importación completada',
            'message' => "La importación de {$this->import->module} finalizó. Exitosas: {$this->import->successful_rows}, Errores: {$this->import->failed_rows}.",
            'entity_type' => 'data_import',
            'entity_id' => $this->import->id,
            'url' => route('imports.show', $this->import->id),
            'action' => 'import.completed',
            'dedup_key' => $this->dedupKey,
        ];
    }
}
