<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\RecordMediaDimensions;
use App\Models\MediaAttachment;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Section;
use App\Models\SectionItem;
use App\Models\SectionItemTranslation;
use App\Models\SectionTranslation;
use App\Models\User;
use App\Observers\PageContentObserver;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
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
        /*
         * Destructive artisan commands are refused unless the database name
         * ends in `_test`.
         *
         * This exists because `php artisan migrate:fresh --env=testing --force`
         * wiped the development database. `--env=testing` only loads a
         * `.env.testing` file; there wasn't one, so Laravel fell back to `.env`
         * and the command ran against live data. The settings in `phpunit.xml`
         * are no protection — they apply only when phpunit runs.
         *
         * `.env.testing` now exists, which fixes that specific path. This
         * guard is the second line: it does not care how the connection was
         * chosen, only what it points at. `migrate:fresh`, `db:wipe` and
         * `migrate:reset` all refuse on `amadcraft_b2b` and allow on
         * `amadcraft_b2b_test`.
         *
         * To wipe a non-test database deliberately, name it in the command's
         * own connection or drop it in the client's own tooling — this refuses
         * to make destruction the accidental default.
         */
        DB::prohibitDestructiveCommands(
            ! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')
        );

        // Inertia props are consumed directly by Vue components as arrays.
        // The default "data" envelope would turn every :items="sectors" into
        // an object and silently render nothing.
        JsonResource::withoutWrapping();

        // Every uploaded image records its own pixel size, so <img> can carry
        // width/height and the browser reserves the box (§15.1).
        Event::listen(MediaHasBeenAddedEvent::class, RecordMediaDimensions::class);

        $this->recordSignIns();

        /*
         * A page's keyword scores follow the page.
         *
         * Registered on every model that can change what a page says — its
         * copy, its cards, and which images it shows — so an editor who adds
         * their target phrase to a headline sees the bar turn green without
         * running anything. The observer decides what is worth dispatching;
         * see it for why a section owned by a sector is skipped.
         */
        foreach ([
            Page::class,
            PageTranslation::class,
            Section::class,
            SectionTranslation::class,
            SectionItem::class,
            SectionItemTranslation::class,
            MediaAttachment::class,
        ] as $model) {
            $model::observe(PageContentObserver::class);
        }

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
    /**
     * Who signed in, who signed out, and who was turned away (§9.1 audit trail).
     *
     * Failed attempts are recorded as well as successful ones, and that is the
     * half worth having: a successful login tells you what an administrator
     * did, but a run of failures against one account is the only warning the
     * panel gives that someone is trying to get in. §9.2 already locks the
     * account after five — this is what makes the attempt visible afterwards.
     *
     * No password is touched here. `Failed` carries the submitted credentials;
     * only the email is read from it, never `$event->credentials['password']`.
     */
    private function recordSignIns(): void
    {
        Event::listen(Login::class, fn (Login $event) => activity('auth')
            ->causedBy($event->user)
            ->event('login')
            ->log('login'));

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user !== null) {
                activity('auth')->causedBy($event->user)->event('logout')->log('logout');
            }
        });

        Event::listen(Failed::class, fn (Failed $event) => activity('auth')
            ->event('login_failed')
            ->withProperties(['email' => (string) ($event->credentials['email'] ?? '')])
            ->log('login_failed'));
    }

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
