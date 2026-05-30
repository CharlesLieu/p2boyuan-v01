<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoDataService
{
    /**
     * @return array<string, mixed>
     */
    public function reset(): array
    {
        Storage::disk('public')->deleteDirectory('demo-attachments');

        DB::transaction(function (): void {
            app(DemoSeeder::class)->run();
        });

        return [
            'reset' => true,
            'accountsRestored' => User::query()->count(),
            'applicationsRestored' => Application::query()->count(),
        ];
    }
}
