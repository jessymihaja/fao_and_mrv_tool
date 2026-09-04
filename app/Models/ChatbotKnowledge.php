<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotKnowledge extends Model
{
    protected $table = 'chatbot_knowledge';

    protected $fillable = [
        'category',
        'keywords',
        'response',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}