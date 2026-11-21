<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'english_name',
    'text_direction',
    'is_active',
    'native_name',
    'default_calendar_code',
    'default_numbering_system_code',
])]
class Locale extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return Attribute<string, string> */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                if ($this->exists && $value !== $this->getRawOriginal('code')) {
                    throw new LogicException('A locale code cannot be changed after creation.');
                }

                return $value;
            },
        );
    }
}
