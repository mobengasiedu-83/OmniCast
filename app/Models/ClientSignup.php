<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $otp_expires_at */
#[Fillable(['email', 'phone', 'otp_channel', 'registration_data', 'otp_hash', 'otp_expires_at', 'verified_at', 'attempts'])]
#[Hidden(['otp_hash'])]
class ClientSignup extends Model
{
    protected function casts(): array
    {
        return ['registration_data' => 'array', 'otp_expires_at' => 'datetime', 'verified_at' => 'datetime'];
    }
}
