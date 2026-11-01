<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthChallenge extends Model
{
    protected $fillable = [
        'email',
        'captcha_token',
        'captcha_solution',
        'otp_code',
        'otp_expires_at',
        'is_verified',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'is_verified' => 'boolean',
    ];
}