<?php

namespace App\Providers;

use App\Support\Display;
use Illuminate\Support\Facades\Blade;
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
        $this->registerDisplayDirectives();
    }

    /**
     * @grams($weight), @datetime($time, 'Never') and so on, for the formats in
     * Display. A directive compiles to one escaped echo, so a table row costs no
     * more than writing the call out.
     */
    private function registerDisplayDirectives(): void
    {
        $directives = [
            'datetime' => 'dateTime',
            'datetimeseconds' => 'dateTimeWithSeconds',
            'date' => 'date',
            'grams' => 'grams',
            'kilograms' => 'kilograms',
            'points' => 'points',
            'wholenumber' => 'wholeNumber',
            'percent' => 'percent',
            'faultlabel' => 'faultLabel',
        ];

        foreach ($directives as $directive => $method) {
            Blade::directive($directive, fn (string $expression): string => '<?php echo e('.Display::class."::{$method}({$expression})); ?>");
        }
    }
}
