<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantPaymentVoucher extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'voucher_no',
        'store_id',
        'payout_record_id',
        'related_business_no',
        'amount',
        'status',
        'paid_at',
        'payee_name',
        'payee_account_masked',
        'payer_name',
        'voucher_file',
        'remark',
        'void_reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voucher_file' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function payoutRecord(): BelongsTo
    {
        return $this->belongsTo(PayoutRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
