<?php

use App\Routing\RouteRegistry;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;

class RoutingTest extends TestCase {
    use \Laravel\Lumen\Testing\DatabaseTransactions;

    protected $mockRoutes = ['gateway' => [
        'services' => [
            'service1' => [],
            'service2' => []
        ],

        'routes' => [
            [
                'aggregate' => true,
                'method' => 'GET',
                'path' => '/somewhere/{page}/details',
                'source' => [
                    'basic' => [
                        'service' => 'service1',
                        'method' => 'GET',
                        'path' => '/pages/{page}',
                        'sequence' => 0
                    ],
                    'settings' => [
                        'service' => 'service1',
                        'json_key' => 'details.settings',
                        'method' => 'GET',
                        'path' => '/posts/{basic_post_id}',
                        'sequence' => 1,
                        'critical' => false
                    ],
                    'clients' => [
                        'service' => 'service2',
                        'json_key' => 'details.data',
                        'method' => 'GET',
                        'path' => '/data/{basic_post_id}',
                        'sequence' => 1,
                        'critical' => false
                    ]
                ]
            ]
        ],

        'global' => [
            'prefix' => '/v1',
            'timeout' => 1.0
        ],

        'defaults' => [
            'doc_point' => '/api/doc',
            'domain' => 'localhost'
        ]
    ]];

    /**
     * @test
     */
    public function config_routes_are_parsed_correctly()
    {
        config($this->mockRoutes);
        $registry = new RouteRegistry;

        $this->assertFalse($registry->isEmpty());
        $route = $registry->getRoutes()->first();
        $this->assertEquals('/v1/somewhere/{page}/details', $route->getPath());
        $this->assertEquals(3, $route->getEndpoints()->count());
    }

    /**
     * @test
     */
    public function aggregate_route_works()
    {
        config($this->mockRoutes);

        $this->app->singleton(RouteRegistry::class, function() {
            return new RouteRegistry;
        });

        $this->app->make(RouteRegistry::class)->bind(app());

        $this->mockGuzzle();
        $this->get('/v1/somewhere/super-page/details', [
            'Authorization' => 'Bearer ' . $this->getUser()
        ]);

        $this->assertEquals(200, $this->response->getStatusCode());
    }

    /**
     * @return void
     */
    private function mockGuzzle()
    {
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], 'Lala'),
            new Response(202, ['Content-Length' => 0], 'Lala')
        ]);

        $this->app->singleton(Client::class, function() use ($mock) {
            return new Client([
                'handler' => $mock
            ]);
        });
    }

    /**
     * @return string
     */
    private function getUser()
    {
        $user = \App\User::create([
            'email' => 'taylor@laravel.com',
            'login' => 'dasdasd',
            'password' => 'my-password'
        ]);

        DB::insert('insert into oauth_clients (user_id, name, password_client) values (?, ?, ?)', [$user->id, 'Test', 1]);

        $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $this->app['db.connection']->getPdo()->lastInsertId(),
            'username' => 'taylor@laravel.com',
            'password' => 'my-password',
            'scope' => '*',
        ]);

        $token = json_decode($this->response->getContent(), true);

        return $token['access_token'];
    }
}