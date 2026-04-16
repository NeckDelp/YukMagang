<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySupervisor extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'name',
        'position',
        'phone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
