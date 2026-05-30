<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_no',
        'source_type',
        'store_id',
        'created_by_user_id',
        'current_owner_role',
        'current_owner_user_id',
        'status',
        'customer_name',
        'customer_phone',
        'id_type',
        'id_number',
        'customer_address',
        'brand',
        'model',
        'color',
        'capacity',
        'imei',
        'device_condition',
        'sale_price',
        'loan_amount',
        'periods',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'sale_price' => 'decimal:2',
            'loan_amount' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function currentOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_owner_user_id');
    }

    public function inspectionTasks(): HasMany
    {
        return $this->hasMany(InspectionTask::class);
    }

    public function reviewRecords(): HasMany
    {
        return $this->hasMany(ReviewRecord::class);
    }

    public function payoutRecords(): HasMany
    {
        return $this->hasMany(PayoutRecord::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(StatusLog::class);
    }
}
