<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    public function test_states_endpoint_returns_regions_for_country(): void
    {
        Http::fake([
            'http://geodb-free-service.wirefreethought.com/v1/geo/countries/DE/regions*' => Http::response([
                'data' => [
                    ['isoCode' => 'BE', 'name' => 'Berlin'],
                    ['isoCode' => 'BW', 'name' => 'Baden-Wurttemberg'],
                ],
                'metadata' => ['totalCount' => 2],
            ]),
        ]);

        $response = $this->getJson(route('api.locations.states', ['country' => 'DE']));

        $response->assertOk();
        $response->assertJsonPath('data.0.code', 'BW');
        $response->assertJsonPath('data.1.code', 'BE');
    }

    public function test_cities_endpoint_filters_to_city_type_via_provider(): void
    {
        Http::fake([
            'http://geodb-free-service.wirefreethought.com/v1/geo/countries/DE/regions/BE/cities*' => Http::response([
                'data' => [
                    ['type' => 'CITY', 'name' => 'Berlin'],
                    ['type' => 'CITY', 'name' => 'Pankow'],
                ],
                'metadata' => ['totalCount' => 2],
            ]),
        ]);

        $response = $this->getJson(route('api.locations.cities', ['country' => 'DE', 'state' => 'BE']));

        $response->assertOk();
        $response->assertJson([
            'data' => ['Berlin', 'Pankow'],
        ]);
    }
}
