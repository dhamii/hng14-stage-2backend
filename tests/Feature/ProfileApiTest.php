<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $analyst;
    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyst = User::factory()->create(['role' => 'analyst']);
        $this->accessToken = $this->analyst->createToken('test-access', ['access'], now()->addMinutes(3))->plainTextToken;
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->accessToken}",
            'X-API-Version' => '1',
        ];
    }

    public function test_get_all_profiles()
    {
        \App\Models\Profile::factory()->count(15)->create();

        $response = $this->get('/api/profiles', $this->headers());

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'page',
                     'limit',
                     'total',
                     'total_pages',
                     'links' => ['self', 'next', 'prev'],
                     'data' => [
                         '*' => ['id', 'name', 'gender', 'age', 'country_id']
                     ]
                 ]);
    }

    public function test_profile_filtering()
    {
        \App\Models\Profile::factory()->create(['gender' => 'male', 'age' => 25, 'country_id' => 'NG']);
        \App\Models\Profile::factory()->create(['gender' => 'female', 'age' => 30, 'country_id' => 'US']);

        $response = $this->get('/api/profiles?gender=male&country_id=NG', $this->headers());

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('male', $response->json('data')[0]['gender']);
    }

    public function test_nlp_search()
    {
        \App\Models\Profile::factory()->create(['gender' => 'male', 'age' => 20, 'country_id' => 'NG']);
        \App\Models\Profile::factory()->create(['gender' => 'female', 'age' => 30, 'country_id' => 'NG']);

        $response = $this->get('/api/profiles/search?q=young males from nigeria', $this->headers());

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
        $response = $this->get('/api/profiles/search?q=extraterrestrials from mars', $this->headers());

        $response->assertStatus(400)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Unable to interpret query'
                 ]);
    }

    public function test_profiles_require_api_version_header(): void
    {
        $response = $this->get('/api/profiles', [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Missing or invalid X-API-Version header',
            ]);
    }
}
