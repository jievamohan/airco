<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Calendar\AppleCalendarClient;
use App\Services\Calendar\CalendarClient;
use App\Services\Calendar\GoogleCalendarClient;
use App\Services\Calendar\NullCalendarClient;
use App\Services\SettingsRepository;
use App\Services\Voice\ElevenLabsClient;
use App\Services\Voice\FakeVoiceAgentClient;
use App\Services\Voice\VoiceAgentClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);

        // In proefmodus loopt de volledige workflow, maar wordt er niet echt
        // gebeld en niet echt in een agenda geschreven.
        $this->app->bind(VoiceAgentClient::class, function ($app): VoiceAgentClient {
            $settings = $app->make(SettingsRepository::class);

            if ($settings->bool('agent.dry_run', false) || ! $settings->bool('agent.elevenlabs.enabled', false)) {
                return new FakeVoiceAgentClient;
            }

            return $app->make(ElevenLabsClient::class);
        });

        $this->app->bind(CalendarClient::class, function ($app): CalendarClient {
            $settings = $app->make(SettingsRepository::class);

            if ($settings->bool('agent.dry_run', false)) {
                return new NullCalendarClient;
            }

            return match ($settings->string('agent.calendar.provider', 'none')) {
                'google' => $app->make(GoogleCalendarClient::class),
                'apple' => $app->make(AppleCalendarClient::class),
                default => new NullCalendarClient,
            };
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::unguard(false);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
