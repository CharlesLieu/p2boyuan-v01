<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SalesAgentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = SalesAgent::query()
            ->where('status', 'ACTIVE')
            ->orderBy('agent_code')
            ->get()
            ->map(fn (SalesAgent $salesAgent): array => [
                'id' => $salesAgent->id,
                'code' => $salesAgent->agent_code,
                'name' => $salesAgent->name,
                'phone' => $salesAgent->phone,
                'status' => $salesAgent->status,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
            ],
            'message' => null,
            'requestId' => $request->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ]);
    }
}
