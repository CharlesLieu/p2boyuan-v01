<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'store_code',
        'name',
        'contact_name',
        'contact_phone',
        'address',
        'status',
        'onboarding_status',
        'payment_method',
        'payment_account',
        'payment_account_name',
        'payment_bank_or_channel',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function onboardingApplications(): HasMany
    {
        return $this->hasMany(MerchantOnboardingApplication::class);
    }

    public function merchantPaymentVouchers(): HasMany
    {
        return $this->hasMany(MerchantPaymentVoucher::class);
    }
}
