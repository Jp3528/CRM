<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PrivateDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateDocument>
 */
class PrivateDocumentFactory extends Factory
{
    protected $model = PrivateDocument::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'documentable_type' => Company::class,
            'documentable_id' => Company::factory(),
            'original_name' => 'contrato_ejemplo.pdf',
            'file_path' => 'documents/company/fake-uuid.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 102400,
            'description' => 'Documento de prueba',
        ];
    }
}
