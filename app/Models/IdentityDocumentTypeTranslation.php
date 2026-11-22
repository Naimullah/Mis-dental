<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IdentityDocumentTypeTranslation extends Pivot
{
    protected $table = 'identity_document_type_translations';

    public $timestamps = false;
}
