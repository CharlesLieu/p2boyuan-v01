<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantOnboardingApplication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'store_id',
        'applicant_name',
        'applicant_phone',
        'applicant_id_number',
        'merchant_name',
        'merchant_address',
        'contact_name',
        'contact_phone',
        'payment_method',
        'payment_account',
        'payment_account_name',
        'payment_bank_or_channel',
        'id_card_front_file',
        'id_card_back_file',
        'qualification_file',
        'status',
        'reviewer_user_id',
        'reviewed_at',
        'review_note',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'id_card_front_file' => 'array',
            'id_card_back_file' => 'array',
            'qualification_file' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
