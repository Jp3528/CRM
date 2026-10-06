<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Catálogo centralizado de módulos y campos para importaciones CSV (Fase 13).
 *
 * Módulos autorizados: companies, contacts, leads.
 * Solo creación: no actualización por coincidencia ni modificación por ID.
 * Prohíbe terminantemente columnas de contraseñas, roles, conversiones o cálculos.
 */
final class ImportCatalog
{
    public const MODULES = ['companies', 'contacts', 'leads'];

    public const PROHIBITED_FIELDS = [
        'id', 'password', 'password_hash', 'roles', 'remember_token',
        'status', 'converted', 'converted_at', 'converted_contact_id',
        'converted_company_id', 'total', 'subtotal', 'tax',
    ];

    public static function label(string $module): string
    {
        return match ($module) {
            'companies' => 'Empresas',
            'contacts' => 'Contactos',
            'leads' => 'Leads / Prospectos',
            default => ucfirst($module),
        };
    }

    public static function requiredPermission(string $module): string
    {
        return match ($module) {
            'companies' => 'companies.create',
            'contacts' => 'contacts.create',
            'leads' => 'leads.create',
            default => 'imports.create',
        };
    }

    /**
     * Definición de campos admitidos por módulo.
     *
     * @return array<string, array{label: string, required: bool, type: string, description: string, sample: string}>
     */
    public static function fields(string $module): array
    {
        return match ($module) {
            'companies' => [
                'trade_name' => ['label' => 'Nombre comercial', 'required' => true, 'type' => 'string', 'description' => 'Nombre comercial o marca de la empresa', 'sample' => 'Inversiones Los Andes S.A.C.'],
                'legal_name' => ['label' => 'Razón social', 'required' => false, 'type' => 'string', 'description' => 'Razón social legal', 'sample' => 'Inversiones Los Andes Sociedad Anónima Cerrada'],
                'tax_id' => ['label' => 'NIT / RUC / Tax ID', 'required' => false, 'type' => 'string', 'description' => 'Identificador fiscal único', 'sample' => '20601234567'],
                'email' => ['label' => 'Correo corporativo', 'required' => false, 'type' => 'email', 'description' => 'Email corporativo general', 'sample' => 'contacto@losandes.com'],
                'phone' => ['label' => 'Teléfono', 'required' => false, 'type' => 'string', 'description' => 'Teléfono fijo o central', 'sample' => '01-4455667'],
                'website' => ['label' => 'Sitio web', 'required' => false, 'type' => 'string', 'description' => 'URL del sitio web', 'sample' => 'https://losandes.com'],
                'industry' => ['label' => 'Industria / Rubro', 'required' => false, 'type' => 'string', 'description' => 'Sector económico', 'sample' => 'Construcción'],
                'company_size' => ['label' => 'Tamaño de empresa', 'required' => false, 'type' => 'string', 'description' => 'Rango de colaboradores', 'sample' => '11-50'],
                'address' => ['label' => 'Dirección', 'required' => false, 'type' => 'string', 'description' => 'Dirección física', 'sample' => 'Av. Javier Prado Este 1234'],
                'city' => ['label' => 'Ciudad', 'required' => false, 'type' => 'string', 'description' => 'Ciudad o provincia', 'sample' => 'Lima'],
                'region' => ['label' => 'Región / Departamento', 'required' => false, 'type' => 'string', 'description' => 'Departamento o estado', 'sample' => 'Lima'],
                'country' => ['label' => 'País', 'required' => false, 'type' => 'string', 'description' => 'País', 'sample' => 'Perú'],
                'postal_code' => ['label' => 'Código postal', 'required' => false, 'type' => 'string', 'description' => 'Código postal', 'sample' => '15036'],
                'notes' => ['label' => 'Notas', 'required' => false, 'type' => 'string', 'description' => 'Observaciones iniciales', 'sample' => 'Cliente potencial para suministros'],
                'owner_id' => ['label' => 'ID Responsable', 'required' => false, 'type' => 'integer', 'description' => 'ID de usuario asignado (en alcance)', 'sample' => '1'],
            ],
            'contacts' => [
                'first_name' => ['label' => 'Nombres', 'required' => true, 'type' => 'string', 'description' => 'Nombres del contacto', 'sample' => 'Carlos'],
                'last_name' => ['label' => 'Apellidos', 'required' => true, 'type' => 'string', 'description' => 'Apellidos del contacto', 'sample' => 'Mendoza'],
                'email' => ['label' => 'Correo electrónico', 'required' => false, 'type' => 'email', 'description' => 'Email personal o laboral', 'sample' => 'carlos.mendoza@empresa.com'],
                'phone' => ['label' => 'Teléfono', 'required' => false, 'type' => 'string', 'description' => 'Teléfono de contacto', 'sample' => '01-2233445'],
                'mobile' => ['label' => 'Celular', 'required' => false, 'type' => 'string', 'description' => 'Número de celular o WhatsApp', 'sample' => '987654321'],
                'job_title' => ['label' => 'Cargo', 'required' => false, 'type' => 'string', 'description' => 'Cargo o puesto de trabajo', 'sample' => 'Gerente de Compras'],
                'department' => ['label' => 'Departamento / Área', 'required' => false, 'type' => 'string', 'description' => 'Área de la empresa', 'sample' => 'Operaciones'],
                'company_id' => ['label' => 'ID Empresa', 'required' => false, 'type' => 'integer', 'description' => 'ID de la empresa asociada (en alcance)', 'sample' => '1'],
                'notes' => ['label' => 'Notas', 'required' => false, 'type' => 'string', 'description' => 'Observaciones del contacto', 'sample' => 'Contacto principal de licitaciones'],
                'owner_id' => ['label' => 'ID Responsable', 'required' => false, 'type' => 'integer', 'description' => 'ID de usuario asignado (en alcance)', 'sample' => '1'],
            ],
            'leads' => [
                'first_name' => ['label' => 'Nombres', 'required' => true, 'type' => 'string', 'description' => 'Nombres del prospecto', 'sample' => 'Mariana'],
                'last_name' => ['label' => 'Apellidos', 'required' => true, 'type' => 'string', 'description' => 'Apellidos del prospecto', 'sample' => 'Ríos'],
                'company_name' => ['label' => 'Empresa declarada', 'required' => false, 'type' => 'string', 'description' => 'Empresa que representa', 'sample' => 'Distribuidora Global'],
                'email' => ['label' => 'Correo electrónico', 'required' => false, 'type' => 'email', 'description' => 'Email de contacto', 'sample' => 'mariana.rios@global.com'],
                'phone' => ['label' => 'Teléfono', 'required' => false, 'type' => 'string', 'description' => 'Teléfono o WhatsApp', 'sample' => '998877665'],
                'source' => ['label' => 'Origen', 'required' => false, 'type' => 'string', 'description' => 'Origen del lead: website, referral, campaign, social, email, phone, event, other', 'sample' => 'website'],
                'score' => ['label' => 'Puntaje (0-100)', 'required' => false, 'type' => 'integer', 'description' => 'Calificación inicial', 'sample' => '75'],
                'estimated_value' => ['label' => 'Valor estimado', 'required' => false, 'type' => 'numeric', 'description' => 'Monto estimado de la oportunidad', 'sample' => '15000.00'],
                'notes' => ['label' => 'Notas', 'required' => false, 'type' => 'string', 'description' => 'Notas comerciales del lead', 'sample' => 'Interesada en cotización para fin de mes'],
                'owner_id' => ['label' => 'ID Responsable', 'required' => false, 'type' => 'integer', 'description' => 'ID de usuario asignado (en alcance)', 'sample' => '1'],
            ],
            default => [],
        };
    }

    /**
     * Genera el contenido de la plantilla CSV en UTF-8 con BOM.
     */
    public static function templateCsv(string $module): string
    {
        $fields = self::fields($module);
        if (empty($fields)) {
            return '';
        }

        $headers = array_keys($fields);
        $samples = array_map(fn ($f) => $f['sample'], array_values($fields));

        $output = fopen('php://memory', 'r+');
        // Escribir BOM UTF-8 para compatibilidad universal con Excel y Windows
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);
        fputcsv($output, $samples);
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Valida una fila individual mapeada contra las reglas de negocio y DataScope.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string> Array de campo => mensaje de error
     */
    public static function validateRow(string $module, array $row, int $rowNumber, User $user): array
    {
        $fields = self::fields($module);
        $rules = [];

        foreach ($fields as $key => $meta) {
            $fieldRules = [];
            if ($meta['required']) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($meta['type'] === 'email') {
                $fieldRules[] = 'email';
                $fieldRules[] = 'max:255';
            } elseif ($meta['type'] === 'integer') {
                $fieldRules[] = 'integer';
            } elseif ($meta['type'] === 'numeric') {
                $fieldRules[] = 'numeric';
                $fieldRules[] = 'min:0';
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:255';
            }

            if ($key === 'notes') {
                $fieldRules = ['nullable', 'string', 'max:2000'];
            }

            if ($key === 'source' && $module === 'leads') {
                $fieldRules[] = Rule::in(Lead::SOURCES);
            }

            if ($key === 'score' && $module === 'leads') {
                $fieldRules[] = 'min:0';
                $fieldRules[] = 'max:100';
            }

            $rules[$key] = $fieldRules;
        }

        $validator = Validator::make($row, $rules);
        $errors = [];

        if ($validator->fails()) {
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors[$field] = implode(' ', $messages);
            }
        }

        // Validación especial de tax_id en Companies (unicidad real sin revelar datos ajenos)
        if ($module === 'companies' && ! empty($row['tax_id'])) {
            $taxId = trim((string) $row['tax_id']);
            if (Company::where('tax_id', $taxId)->exists()) {
                $errors['tax_id'] = "El NIT/RUC '{$taxId}' ya se encuentra registrado.";
            }
        }

        // Validación de DataScope sobre owner_id
        if (! empty($row['owner_id'])) {
            $ownerId = (int) $row['owner_id'];
            $owner = User::where('status', 'active')->find($ownerId);
            if (! $owner || ! DataScope::canAccessOwner($user, $owner)) {
                $errors['owner_id'] = 'El usuario responsable asignado no existe, está inactivo o se encuentra fuera de tu alcance autorizado.';
            }
        }

        // Validación de DataScope sobre company_id en Contacts
        if ($module === 'contacts' && ! empty($row['company_id'])) {
            $companyId = (int) $row['company_id'];
            $company = Company::visibleTo($user)->find($companyId);
            if (! $company) {
                $errors['company_id'] = 'La empresa vinculada no existe o no se encuentra dentro de tu alcance autorizado.';
            }
        }

        return $errors;
    }
}
