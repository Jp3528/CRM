<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataImportError extends Model
{
    protected $fillable = [
        'data_import_id', 'row_number', 'field', 'message',
    ];
}
