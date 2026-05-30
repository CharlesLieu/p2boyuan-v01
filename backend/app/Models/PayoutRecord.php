<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'cashier_user_id',
        'amount',
        'status',
        'voucher_attachment_id',
        'paid_at',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function voucherAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'voucher_attachment_id');
    }
}
