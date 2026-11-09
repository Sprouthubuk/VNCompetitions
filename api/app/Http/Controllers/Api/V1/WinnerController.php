<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WinnerResource;
use App\Models\Winner;
use App\Support\PublicApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WinnerController extends Controller
{
    public const LIMIT = 50;

    public function __construct(private readonly PublicApiCache $cache)
    {
    }

    /** The latest 50 announced winners. */
    public function index(Request $request): JsonResponse
    {
        $payload = $this->cache->winners(fn () => [
            'data' => WinnerResource::collection(
                Winner::query()->announced()->with('ticket:id,number')
                    ->orderByDesc('announced_at')->orderByDesc('id')->limit(self::LIMIT)->get()
            )->resolve($request),
        ]);

        return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
