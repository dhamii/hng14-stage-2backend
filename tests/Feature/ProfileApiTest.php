<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    // use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Profile::truncate();
    }

    public function test_get_all_profiles()
    {
        \App\Models\Profile::factory()->count(15)->create();

        $response = $this->get('/api/profiles');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'page',
                     'limit',
                     'total',
                     'data' => [
                         '*' => ['id', 'name', 'gender', 'age', 'country_id']
                     ]
                 ]);
    }

    public function test_profile_filtering()
    {
        \App\Models\Profile::factory()->create(['gender' => 'male', 'age' => 25, 'country_id' => 'NG']);
        \App\Models\Profile::factory()->create(['gender' => 'female', 'age' => 30, 'country_id' => 'US']);

        $response = $this->get('/api/profiles?gender=male&country_id=NG');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('male', $response->json('data')[0]['gender']);
    }

    public function test_nlp_search()
    {
        \App\Models\Profile::factory()->create(['gender' => 'male', 'age' => 20, 'country_id' => 'NG']);
        \App\Models\Profile::factory()->create(['gender' => 'female', 'age' => 30, 'country_id' => 'NG']);

        $response = $this->get('/api/profiles/search?q=young males from nigeria');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        // Verify constraint applied: gender=male, age=16-24, country=NG
        foreach ($data as $profile) {
            $this->assertEquals('male', $profile['gender']);
            $this->assertTrue($profile['age'] >= 16 && $profile['age'] <= 24);
            $this->assertEquals('NG', $profile['country_id']);
        }
    }

    public function test_nlp_search_uninterpretable_query()
    {
        $response = $this->get('/api/profiles/search?q=extraterrestrials from mars');

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Unable to interpret query'
                 ]);
    }
}
