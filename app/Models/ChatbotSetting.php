<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotSetting extends Model
{
    protected $table = 'chatbot_settings';

    protected $fillable = [
        'is_active',
        'welcome_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}