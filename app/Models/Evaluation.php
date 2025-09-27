<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'cv',
        'project',
        'status',
        'result',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
