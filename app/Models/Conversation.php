<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'title', 'model'];
    use HasFactory;

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isEmpty(): bool
    {
        return $this->messages()->count() === 0;
    }

    public static function cleanupEmpty(int $userId): void
    {
        self::where('user_id', $userId)
            ->whereDoesntHave('messages')
            ->where('created_at', '<', now()->subSecond(1)) // On remove les conv vides de après 1 sec
            ->delete();
    }

    public function getTemperature(): float
    {
        return $this->temperature ?? 0.7;
    }
}
