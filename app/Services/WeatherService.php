<?php

namespace App\Services;

use App\Exceptions\WeatherUnavailableException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WeatherService
{
    private const CACHE_KEY = 'weather:perth:current';

    public function current(): array
    {
        return Cache::remember(self::CACHE_KEY, config('weather.cache_ttl'), fn () => $this->fetch());
    }

    public function refresh(): array
    {
        $weather = $this->fetch();

        Cache::put(self::CACHE_KEY, $weather, config('weather.cache_ttl'));

        return $weather;
    }

    private function fetch(): array
    {
        if (! config('weather.api_key')) {
            Log::warning('Weather API key is not configured.');

            throw new WeatherUnavailableException;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(5)
                ->retry(2, 100, fn (Exception $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429)))
                ->get(rtrim(config('weather.base_url'), '/').'/weather', [
                    'lat' => config('weather.latitude'),
                    'lon' => config('weather.longitude'),
                    'appid' => config('weather.api_key'),
                    'units' => 'metric',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Weather provider request failed.', [
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
            ]);

            throw new WeatherUnavailableException;
        }

        $payload = $response->json();

        if (! is_array($payload) || Validator::make($payload, [
            'main.temp' => ['required', 'numeric'],
            'main.feels_like' => ['required', 'numeric'],
            'main.humidity' => ['required', 'integer', 'between:0,100'],
            'weather.0.description' => ['required', 'string'],
            'wind.speed' => ['required', 'numeric', 'min:0'],
            'dt' => ['required', 'integer', 'min:1'],
        ])->fails()) {
            Log::warning('Weather provider returned invalid data.');

            throw new WeatherUnavailableException;
        }

        return [
            'city' => 'Perth',
            'country' => 'AU',
            'temperature' => (float) $payload['main']['temp'],
            'feels_like' => (float) $payload['main']['feels_like'],
            'humidity' => (int) $payload['main']['humidity'],
            'description' => $payload['weather'][0]['description'],
            'wind_speed' => (float) $payload['wind']['speed'],
            'units' => 'metric',
            'timezone' => 'Australia/Perth',
            'observed_at' => Carbon::createFromTimestampUTC($payload['dt'])->setTimezone('Australia/Perth')->toIso8601String(),
            'fetched_at' => now()->setTimezone('Australia/Perth')->toIso8601String(),
        ];
    }
}
