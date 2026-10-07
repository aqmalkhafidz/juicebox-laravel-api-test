<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherUnavailableException;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    public function __invoke(WeatherService $weather): JsonResponse
    {
        try {
            return response()->json(['data' => $weather->current()]);
        } catch (WeatherUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
