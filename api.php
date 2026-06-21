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
    $userAgent = 'CuacaKota-App/1.0';
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Gagal menghubungi API eksternal (cURL error).');
        }
        if ($httpCode >= 400) {
            throw new RuntimeException('API eksternal mengembalikan respons error ' . $httpCode);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "User-Agent: {$userAgent}\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('Gagal menghubungi API eksternal.');
        }
    }

    $data = json_decode((string) $response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respons API tidak valid.');
    }
    return $data;
}

try {
    // Request up to 10 results to search for an Indonesian city match
    $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?name=' . rawurlencode($city) . '&count=10&language=id&format=json';
    $geoData = fetchJson($geoUrl);

    $location = null;
    if (!empty($geoData['results'])) {
        foreach ($geoData['results'] as $res) {
            if (
                (isset($res['country_code']) && strtolower($res['country_code']) === 'id') ||
                (isset($res['country']) && strcasecmp($res['country'], 'Indonesia') === 0)
            ) {
                $location = $res;
                break;
            }
        }

        // If no explicit Indonesia match is found, check if the query matches a city but didn't resolve country info
        if (!$location) {
            http_response_code(404);
            echo json_encode(['error' => 'Kota di Indonesia tidak ditemukan.']);
            exit;
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Kota tidak ditemukan.']);
        exit;
    }

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
        'country' => $location['country'] ?? 'Indonesia',
        'timezone' => $weatherData['timezone'] ?? $timezone,
        'current' => $weatherData['current'] ?? [],
        'daily' => $weatherData['daily'] ?? [],
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()]);
}
