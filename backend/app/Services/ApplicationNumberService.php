<?php

namespace App\Services;

use App\Models\Application;

class ApplicationNumberService
{
    public function next(): string
    {
        $prefix = 'A'.now()->format('Ymd');
        $latestSequence = Application::query()
            ->where('application_no', 'like', $prefix.'%')
            ->orderByDesc('application_no')
            ->pluck('application_no')
            ->map(function (string $applicationNo) use ($prefix): ?int {
                if (! preg_match('/^'.preg_quote($prefix, '/').'(\d{4})$/', $applicationNo, $matches)) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter()
            ->max();

        $sequence = ((int) $latestSequence) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
