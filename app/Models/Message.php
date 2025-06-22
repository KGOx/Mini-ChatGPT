<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'role', 'content'];
    use HasFactory;

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function toApiFormat(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}
