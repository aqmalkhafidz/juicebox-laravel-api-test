<?php

namespace Tests\Feature;

use App\Jobs\RefreshWeather;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['weather.api_key' => 'test-api-key']);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    public function test_the_endpoint_returns_current_weather_for_perth(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response($this->weatherPayload())]);

        $this->getJson('/api/weather')->assertOk()
            ->assertJsonPath('data.city', 'Perth')
            ->assertJsonPath('data.country', 'AU')
            ->assertJsonPath('data.temperature', 21.5)
            ->assertJsonPath('data.units', 'metric')
            ->assertJsonPath('data.timezone', 'Australia/Perth')
            ->assertJsonPath('data.observed_at', '2026-10-06T08:00:00+08:00');

        Http::assertSent(fn ($request) => $request['appid'] === 'test-api-key'
            && (float) $request['lat'] === -31.9523
            && (float) $request['lon'] === 115.8613
            && $request['units'] === 'metric');
    }

    public function test_repeated_requests_use_the_cache(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response($this->weatherPayload())]);

        $first = $this->getJson('/api/weather')->assertOk();
        $second = $this->getJson('/api/weather')->assertOk();

        $this->assertSame($first->json(), $second->json());
        Http::assertSentCount(1);
    }

    public function test_the_cache_expires_after_fifteen_minutes(): void
    {
        Http::fakeSequence()->push($this->weatherPayload())
            ->push($this->weatherPayload(25.0));

        $this->getJson('/api/weather')->assertOk()->assertJsonPath('data.temperature', 21.5);
        $this->travel(14)->minutes();
        $this->getJson('/api/weather')->assertOk()->assertJsonPath('data.temperature', 21.5);
        Http::assertSentCount(1);

        $this->travel(2)->minutes();
        $this->getJson('/api/weather')->assertOk()->assertJsonPath('data.temperature', 25);
        Http::assertSentCount(2);
    }

    public function test_a_transient_provider_error_is_retried(): void
    {
        Http::fakeSequence()->pushStatus(500)->push($this->weatherPayload());

        $this->getJson('/api/weather')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_a_provider_failure_returns_a_safe_error_without_caching_it(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response(['message' => 'Upstream error'], 500)]);

        $this->getJson('/api/weather')->assertServiceUnavailable()
            ->assertExactJson(['message' => 'Weather data is temporarily unavailable.']);

        $this->assertNull(Cache::get('weather:perth:current'));
        Http::assertSentCount(2);
    }

    public function test_invalid_api_credentials_are_not_retried(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response(['message' => 'Invalid key'], 401)]);

        $this->getJson('/api/weather')->assertServiceUnavailable();

        Http::assertSentCount(1);
    }

    public function test_connection_failures_return_service_unavailable(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::failedConnection()]);

        $this->getJson('/api/weather')->assertServiceUnavailable()
            ->assertExactJson(['message' => 'Weather data is temporarily unavailable.']);
    }

    public function test_invalid_provider_data_is_not_cached(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response(['main' => ['temp' => 'invalid']])]);

        $this->getJson('/api/weather')->assertServiceUnavailable();

        $this->assertNull(Cache::get('weather:perth:current'));
    }

    public function test_a_missing_api_key_returns_service_unavailable(): void
    {
        config(['weather.api_key' => null]);
        Http::fake();

        $this->getJson('/api/weather')->assertServiceUnavailable();

        Http::assertNothingSent();
    }

    public function test_the_job_refreshes_weather_even_when_the_cache_is_fresh(): void
    {
        Http::fakeSequence()->push($this->weatherPayload())->push($this->weatherPayload(25.0));
        $this->getJson('/api/weather')->assertOk();

        RefreshWeather::dispatchSync();

        $this->getJson('/api/weather')->assertOk()->assertJsonPath('data.temperature', 25);
        Http::assertSentCount(2);
    }

    public function test_the_weather_job_is_scheduled_hourly(): void
    {
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-10-06 01:00:00', 'UTC'));

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => $event->description === RefreshWeather::class);

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $events->first()->run($this->app);

        Queue::assertPushed(RefreshWeather::class);
    }

    private function weatherPayload(float $temperature = 21.5): array
    {
        return [
            'main' => ['temp' => $temperature, 'feels_like' => 20.0, 'humidity' => 60],
            'weather' => [['description' => 'clear sky']],
            'wind' => ['speed' => 3.2],
            'dt' => Carbon::parse('2026-10-06T00:00:00Z')->timestamp,
        ];
    }
}
