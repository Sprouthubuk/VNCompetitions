<?php

namespace App\Providers;

use App\Models\Competition;
use App\Models\CompetitionBundle;
use App\Models\CompetitionOffer;
use App\Models\InstantWinPrize;
use App\Models\InstantWinPrizeLine;
use App\Models\Ticket;
use App\Models\Winner;
use App\Support\PublicApiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PublicApiCache::class);
    }

    public function boot(): void
    {
        // Catch N+1 queries (e.g. in API resources) everywhere but production.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->bustPublicApiCacheOnChange();
    }

    /**
     * Admin saves and ticket sales change what the public API shows. Writes made
     * with the query builder don't fire these events and must call PublicApiCache
     * themselves.
     */
    private function bustPublicApiCacheOnChange(): void
    {
        $cache = fn (): PublicApiCache => $this->app->make(PublicApiCache::class);

        $forgetComp = function (Competition $comp) use ($cache): void {
            $cache()->forgetCompetition(...array_filter([$comp->slug, $comp->getOriginal('slug')], 'is_string'));
        };
        Competition::saved($forgetComp);
        Competition::deleted($forgetComp);

        $children = [CompetitionBundle::class, CompetitionOffer::class, InstantWinPrizeLine::class, InstantWinPrize::class, Ticket::class];
        foreach ($children as $model) {
            $forgetParent = function (Model $child) use ($cache): void {
                $slug = Competition::query()->whereKey($child->getAttribute('competition_id'))->value('slug');
                if (is_string($slug)) {
                    $cache()->forgetCompetition($slug);
                }
            };
            $model::saved($forgetParent);
            $model::deleted($forgetParent);
        }

        Winner::saved(fn () => $cache()->forgetWinners());
        Winner::deleted(fn () => $cache()->forgetWinners());
    }
}
