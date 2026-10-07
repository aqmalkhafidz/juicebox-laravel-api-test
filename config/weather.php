<?php

return [
    'api_key' => env('WEATHER_API_KEY'),
    'base_url' => env('WEATHER_BASE_URL', 'https://api.openweathermap.org/data/2.5'),
    'latitude' => (float) env('WEATHER_LATITUDE', -31.9523),
    'longitude' => (float) env('WEATHER_LONGITUDE', 115.8613),
    'cache_ttl' => (int) env('WEATHER_CACHE_TTL', 900),
];
