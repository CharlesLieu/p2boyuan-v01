<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesAgent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'agent_code',
        'name',
        'phone',
        'region',
        'task_status',
        'status',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inspectionTasks(): HasMany
    {
        return $this->hasMany(InspectionTask::class);
    }
}
