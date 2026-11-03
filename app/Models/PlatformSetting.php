<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'value', 'is_secret', 'updated_by'])]
#[Hidden(['value'])]
class PlatformSetting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'encrypted', 'is_secret' => 'boolean'];
    }
}
