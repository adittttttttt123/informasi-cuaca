<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$city = trim((string) ($_GET['city'] ?? ''));
if ($city === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Nama kota wajib diisi.']);
    exit;
}

function fetchJson(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header' => "User-Agent: CuacaKota-UAS/1.0\r\n",
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException('Gagal menghubungi API eksternal.');
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respons API tidak valid.');
    }
    return $data;
}

try {
    $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?name=' . rawurlencode($city) . '&count=1&language=id&format=json';
    $geoData = fetchJson($geoUrl);

    if (empty($geoData['results'][0])) {
        http_response_code(404);
        echo json_encode(['error' => 'Kota tidak ditemukan.']);
        exit;
    }

    $location = $geoData['results'][0];
    $lat = $location['latitude'];
    $lon = $location['longitude'];
    $timezone = $location['timezone'] ?? 'auto';
    $weatherUrl = 'https://api.open-meteo.com/v1/forecast?latitude=' . rawurlencode((string) $lat)
        . '&longitude=' . rawurlencode((string) $lon)
        . '&current=temperature_2m,relative_humidity_2m,is_day,weather_code,wind_speed_10m,surface_pressure'
        . '&daily=weather_code,temperature_2m_max,temperature_2m_min'
        . '&timezone=' . rawurlencode((string) $timezone);
    $weatherData = fetchJson($weatherUrl);

    echo json_encode([
        'city' => $location['name'] ?? $city,
        'country' => $location['country'] ?? '',
        'timezone' => $weatherData['timezone'] ?? $timezone,
        'current' => $weatherData['current'] ?? [],
        'daily' => $weatherData['daily'] ?? [],
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()]);
}
