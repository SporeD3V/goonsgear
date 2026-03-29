<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeoDbLocationProvider
{
    private const PAGE_LIMIT = 10;

    private const MAX_CITY_RESULTS = 100;

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function states(string $countryCode): array
    {
        $countryCode = strtoupper(trim($countryCode));

        if ($countryCode === '') {
            return [];
        }

        try {
            return Cache::remember("locations:states:{$countryCode}", now()->addDays(30), fn (): array => $this->fetchStates($countryCode));
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    public function cities(string $countryCode, ?string $stateCode = null): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $stateCode = $stateCode !== null ? strtoupper(trim($stateCode)) : null;
        $stateKey = $stateCode ?: 'ALL';

        if ($countryCode === '') {
            return [];
        }

        try {
            return Cache::remember("locations:cities:{$countryCode}:{$stateKey}", now()->addDays(30), fn (): array => $this->fetchCities($countryCode, $stateCode));
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    private function fetchStates(string $countryCode): array
    {
        $rows = $this->paginate("countries/{$countryCode}/regions", [
            'sort' => 'name',
        ]);

        $states = [];

        foreach ($rows as $row) {
            $code = strtoupper((string) ($row['isoCode'] ?? $row['fipsCode'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($code === '' || $name === '') {
                continue;
            }

            $states[$code] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        uasort($states, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return array_values($states);
    }

    /**
     * @return array<int, string>
     */
    private function fetchCities(string $countryCode, ?string $stateCode = null): array
    {
        $path = $stateCode !== null && $stateCode !== ''
            ? "countries/{$countryCode}/regions/{$stateCode}/cities"
            : "countries/{$countryCode}/cities";

        $rows = $this->paginate($path, [
            'sort' => '-population',
            'types' => 'CITY',
        ], self::MAX_CITY_RESULTS);

        $cities = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? $row['city'] ?? ''));

            if ($name === '') {
                continue;
            }

            $cities[mb_strtolower($name)] = $name;
        }

        natcasesort($cities);

        return array_values($cities);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paginate(string $path, array $query = [], ?int $maxItems = null): array
    {
        $offset = 0;
        $rows = [];
        $totalCount = null;

        do {
            $response = Http::baseUrl((string) config('services.geodb.base_url'))
                ->connectTimeout(3)
                ->timeout(8)
                ->retry([200, 500, 1000])
                ->get($path, array_merge($query, [
                    'offset' => $offset,
                    'limit' => self::PAGE_LIMIT,
                ]))
                ->throw();

            $payload = $response->json();
            $batch = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            foreach ($batch as $row) {
                if (is_array($row)) {
                    $rows[] = $row;

                    if ($maxItems !== null && count($rows) >= $maxItems) {
                        return $rows;
                    }
                }
            }

            $batchCount = count($batch);
            $totalCount ??= (int) ($payload['metadata']['totalCount'] ?? $batchCount);
            $offset += self::PAGE_LIMIT;

            if ($batchCount === 0) {
                break;
            }
        } while ($offset < $totalCount);

        return $rows;
    }
}
