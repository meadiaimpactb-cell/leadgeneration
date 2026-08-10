<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RecordMediaDimensions;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One Settings instance per request so its in-memory cache is shared.
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // Inertia props are consumed directly by Vue components as arrays.
        // The default "data" envelope would turn every :items="sectors" into
        // an object and silently render nothing.
        JsonResource::withoutWrapping();

        // Every uploaded image records its own pixel size, so <img> can carry
        // width/height and the browser reserves the box (§15.1).
        Event::listen(MediaHasBeenAddedEvent::class, RecordMediaDimensions::class);

        $this->configureRateLimiting();
        $this->configureGates();
        $this->configureModels();
        $this->configureUrls();
    }

    /**
     * §15.3: rate limiting on POST /leads.
     *
     * Keyed on IP because there is no visitor account to key on. The limit is
     * per minute and generous enough that a person correcting a typo three
     * times is never blocked — the target is scripted abuse, not humans.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('leads', fn (Request $request) => Limit::perMinute(
            (int) config('site.leads.rate_limit', 5)
        )->by($request->ip())->response(
            fn () => back()->withErrors(['contact' => __('leads.too_many')])
        ));

        // §9.2: lockout after 5 login attempts. Keyed on email AND IP so one
        // attacker cannot lock a real administrator out of their own account
        // by hammering their address from elsewhere.
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinutes(15, 20)->by($request->ip()),
        ]);
    }

    /**
     * super-admin passes every gate (§9.1: "everything"), so individual
     * policies never need a special case for it.
     */
    private function configureGates(): void
    {
        Gate::before(fn (User $user) => $user->hasRole(User::ROLE_SUPER_ADMIN) ? true : null);
    }

    private function configureModels(): void
    {
        // Fail loudly in development when a relation was not eager-loaded,
        // rather than shipping the N+1 that §7.4 forbids.
        Model::preventLazyLoading($this->app->isLocal());
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());
    }

    private function configureUrls(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
