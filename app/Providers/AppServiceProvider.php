<?php

namespace App\Providers;

use App\Services\AppMenu;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production', 'staging')
            || request()->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || request()->isSecure()) {
            URL::forceScheme('https');
        }

        // Blade directive untuk render path TTD user (dua format kolom
        // myuser_ttd_image: filename saja vs legacy 'UserTtd/abc.png') —
        // logikanya terpusat di App\Support\TtdUser (diselaraskan dari sirus).
        // Pemakaian: <img src="@ttdSrc($user->myuser_ttd_image)" />
        Blade::directive('ttdSrc', function ($expression) {
            return "<?php echo \\App\\Support\\TtdUser::pathWeb($expression); ?>";
        });

        // Share $sidebarMenus (grouped + filtered by user role) ke sidebar layout.
        // Tidak query DB di guest pages — guard via auth check.
        View::composer('layouts.app-sidebar', function ($view) {
            $roles = auth()->check()
                ? auth()->user()->getRoleNames()->map(fn($r) => trim(strtolower($r)))->values()->toArray()
                : [];

            $view->with('sidebarMenus', AppMenu::grouped($roles));
        });
    }
}
