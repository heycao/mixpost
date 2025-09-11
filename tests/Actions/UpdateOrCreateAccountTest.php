<?php

use Illuminate\Support\Str;
use Inovector\Mixpost\Actions\UpdateOrCreateAccount;
use Inovector\Mixpost\Concerns\UsesSocialProviderManager;
use Inovector\Mixpost\Facades\ServiceManager;
use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\Models\Service;

it('can create new account', function () {
    $providerId = Str::random();

    $data = [
        'id' => $providerId,
        'name' => 'Name of Account',
        'username' => 'username',
//        'image' => fake()->imageUrl() TODO: find a solution to test an image
        'image' => ''
    ];

    $service = Service::factory()->create();

    (new UpdateOrCreateAccount())('twitter', $data, ['access_token' => ['auth_token' => 'my-token']], $service->id);

    $account = Account::where('provider_id', $providerId)->first();

//    expect($account)->toBeObject()->and($account->image())->toBeString();

    expect($account)->toBeObject()->and($account->name)->toBe($data['name']);
});

it('can update the account', function () {
    $account = Account::factory()->create();

    $data = [
        'id' => $account->provider_id,
        'name' => 'Updated name',
        'username' => 'updated_username',
        'image' => '',
    ];

    (new UpdateOrCreateAccount())($account->provider, $data, ['access_token' => ['auth_token' => 'my-token']], $account->service_id);

    $account->refresh();

    expect($account->name)->toEqual($data['name'])
        ->and($account->username)->toEqual($data['username']);
});

it('can create two accounts with the same provider but different services', function () {
    $providerId = Str::random();

    $data = [
        'id' => $providerId,
        'name' => 'Name of Account',
        'username' => 'username',
        'image' => ''
    ];

    $service1 = Service::factory()->create();
    $service2 = Service::factory()->create();

    (new UpdateOrCreateAccount())('twitter', $data, ['access_token' => ['auth_token' => 'my-token']], $service1->id);
    (new UpdateOrCreateAccount())('twitter', $data, ['access_token' => ['auth_token' => 'my-token']], $service2->id);

    $this->assertDatabaseCount('mixpost_accounts', 2);
});

it('uses the correct service configuration based on service_id', function () {
    // Arrange: Create two services for the same provider
    $service1 = Service::factory()->create(['name' => 'twitter']);
    $service2 = Service::factory()->create(['name' => 'twitter']);

    // Create an account linked to the second service
    $account = Account::factory()->create([
        'provider' => 'twitter',
        'service_id' => $service2->id,
        'access_token' => [
            'oauth_token' => 'fake-token',
            'oauth_token_secret' => 'fake-secret'
        ]
    ]);

    // Create a dummy class that uses the trait we want to test
    $managerUser = new class {
        use UsesSocialProviderManager;
    };

    // Act & Assert: Mock the ServiceManager to ensure it's called with the correct service_id
    ServiceManager::shouldReceive('getById')
        ->with($service2->id, 'configuration')
        ->once()
        ->andReturn([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
        ]);

    // This will trigger the SocialProviderManager and instantiate the TwitterProvider
    $managerUser->connectProvider($account);
});
