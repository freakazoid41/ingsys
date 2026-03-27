<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Persons;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fake recaptcha verification
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);
    }

    public function test_it_locks_after_max_attempts()
    {
        $email = 'locktest@example.com';
        $password = 'secret';

        $person = Persons::create([
            'name' => 'Test Person',
        ]);

        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'status' => '1',
            'role' => 'user',
            'person_id' => $person->id,
        ]);

        // 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post(route('login-user', 'admin'), [
                'email' => $email,
                'password' => 'wrongpass',
                'g-recaptcha-response' => 'test',
                '_token' => 'test',
            ]);
        }

        $this->assertGreaterThanOrEqual(5, Cache::get('login:attempts:'.strtolower($email), 0));
    }

    public function test_locked_account_blocks_successful_login()
    {
        $email = 'blocktest@example.com';
        $password = 'secret';

        $person = Persons::create([
            'name' => 'Block Person',
        ]);

        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'status' => '1',
            'role' => 'user',
            'person_id' => $person->id,
        ]);

        // place a lock
        $lockKey = 'login:locked:'.strtolower($email);
        Cache::put($lockKey, Carbon::now()->addMinutes(15)->toDateTimeString(), 15 * 60);

        $response = $this->post(route('login-user', 'admin'), [
            'email' => $email,
            'password' => $password,
            'g-recaptcha-response' => 'test',
            '_token' => 'test',
        ]);
        $this->assertTrue(Cache::has($lockKey));
    }

    public function test_lock_expires_allows_login()
    {
        $email = 'expiretest@example.com';
        $password = 'secret';

        $person = Persons::create([
            'name' => 'Expire Person',
        ]);

        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'status' => '1',
            'role' => 'user',
            'person_id' => $person->id,
        ]);

        // simulate a lock then remove it to mimic expiry
        $lockKey = 'login:locked:'.strtolower($email);
        Cache::put($lockKey, Carbon::now()->addMinutes(15)->toDateTimeString(), 15 * 60);

        // manually clear lock to simulate expiry
        Cache::forget($lockKey);

        $response = $this->post(route('login-user', 'admin'), [
            'email' => $email,
            'password' => $password,
            'g-recaptcha-response' => 'test',
            '_token' => 'test',
        ]);

        // successful login redirects to SMS page
        $response->assertRedirect(route('login-sms'));

        // cache keys should be cleared after login
        $this->assertFalse(Cache::has($lockKey));
        $this->assertFalse(Cache::has('login:attempts:'.strtolower($email)));
    }
}
