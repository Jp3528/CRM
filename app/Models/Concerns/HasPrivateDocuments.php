<?php

namespace App\Models\Concerns;

use App\Models\PrivateDocument;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPrivateDocuments
{
    /**
     * Documentos privados asociados a la entidad.
     *
     * @return MorphMany<PrivateDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(PrivateDocument::class, 'documentable')->latest();
    }
}
