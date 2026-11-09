<?php

namespace App\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Short-lived cache for the public API (docs/API_CONTRACT.md).
 *
 * With a taggable store (Redis on Forge) entries are tagged per comp, so a change
 * to one comp flushes that comp's entries plus the list. Stores without tags
 * (the database store used locally) fall back to forgetting the exact keys.
 *
 * Model events bust it automatically (see AppServiceProvider). Code that writes
 * with the query builder, e.g. bulk ticket allocation, must call forgetCompetition().
 */
final class PublicApiCache
{
    private const PREFIX = 'vn:api:v1:';

    public function __construct(private readonly Factory $cache)
    {
    }

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function competitions(Closure $build): array
    {
        return $this->remember(self::PREFIX.'competitions', ['vn:list'], $build);
    }

    /**
     * Returns false for an unknown or non-public slug (cached too, so misses stay cheap).
     *
     * @param  Closure(): (array<string, mixed>|false)  $build
     * @return array<string, mixed>|false
     */
    public function competition(string $slug, Closure $build): array|false
    {
        return $this->remember(self::PREFIX."competition:{$slug}", [self::compTag($slug)], $build);
    }

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function winners(Closure $build): array
    {
        return $this->remember(self::PREFIX.'winners', ['vn:winners'], $build);
    }

    /** Forget one or more comps (by slug, include the old slug after a rename) and the list. */
    public function forgetCompetition(string ...$slugs): void
    {
        foreach (array_unique($slugs) as $slug) {
            $this->forget(self::PREFIX."competition:{$slug}", [self::compTag($slug)]);
        }
        $this->forget(self::PREFIX.'competitions', ['vn:list']);
    }

    public function forgetWinners(): void
    {
        $this->forget(self::PREFIX.'winners', ['vn:winners']);
    }

    /**
     * @template T
     *
     * @param  list<string>  $tags
     * @param  Closure(): T  $build
     * @return T
     */
    private function remember(string $key, array $tags, Closure $build): mixed
    {
        $ttl = config()->integer('vaultnine.api_cache_ttl');
        $store = $this->store();

        return $this->taggable($store)
            ? $store->tags($tags)->remember($key, $ttl, $build)
            : $store->remember($key, $ttl, $build);
    }

    /**
     * @param  list<string>  $tags
     */
    private function forget(string $key, array $tags): void
    {
        $store = $this->store();

        if ($this->taggable($store)) {
            $store->tags($tags)->flush();
        } else {
            $store->forget($key);
        }
    }

    private function store(): Repository
    {
        return $this->cache->store();
    }

    /**
     * @phpstan-assert-if-true \Illuminate\Cache\Repository $store
     */
    private function taggable(Repository $store): bool
    {
        return $store instanceof \Illuminate\Cache\Repository && $store->getStore() instanceof TaggableStore;
    }

    private static function compTag(string $slug): string
    {
        return "vn:comp:{$slug}";
    }
}
