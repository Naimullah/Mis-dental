<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['english_name', 'symbol', 'minor_units', 'is_active'])]
class Currency extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'minor_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return Attribute<string, string> */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                if ($this->exists && $value !== $this->getRawOriginal('code')) {
                    throw new LogicException('A currency code cannot be changed after creation.');
                }

                return $value;
            },
        );
    }
}
