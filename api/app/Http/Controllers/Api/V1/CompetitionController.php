<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompetitionResource;
use App\Models\Competition;
use App\Support\PublicApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetitionController extends Controller
{
    public function __construct(private readonly PublicApiCache $cache)
    {
    }

    /** Live comps only, soonest draw first. */
    public function index(Request $request): JsonResponse
    {
        $payload = $this->cache->competitions(fn () => [
            'data' => CompetitionResource::collection(
                Competition::query()->live()->forPublicApi()->orderBy('draw_at')->orderBy('sort')->orderBy('title')->get()
            )->resolve($request),
        ]);

        return self::json($payload);
    }

    /** One comp: live, sold out, closed or drawn. Anything else is a 404. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $payload = $this->cache->competition($slug, function () use ($request, $slug) {
            $comp = Competition::query()->public()->forPublicApi()->where('slug', $slug)->first();

            return $comp === null ? false : ['data' => (new CompetitionResource($comp))->resolve($request)];
        });

        abort_if($payload === false, 404, 'Competition not found.');

        return self::json($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function json(array $payload): JsonResponse
    {
        return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
