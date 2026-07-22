<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_with_a_phone_number(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sara Ali',
            'phone' => '+201234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.phone', '+201234567890')
            ->assertJsonPath('user.is_admin', false)
            ->assertJsonPath('user.phone_verified_at', null);

        $this->assertDatabaseHas('users', ['phone' => '+201234567890']);
        // The password must never be stored in clear text.
        $this->assertNotSame('password123', User::first()->password);
    }

    public function test_registration_requires_a_valid_phone(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sara Ali',
            'phone' => 'not-a-phone',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_registration_rejects_a_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+201234567890']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sara Ali',
            'phone' => '+201234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_registration_rejects_a_weak_or_unconfirmed_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sara Ali',
            'phone' => '+201234567890',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_registration_reports_delivery_failure_and_keeps_the_unverified_account(): void
    {
        $sms = Mockery::mock(SmsSender::class);
        $sms->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Twilio unavailable'));
        $this->app->instance(SmsSender::class, $sms);

        $this->postJson('/api/auth/register', [
            'name' => 'Sara Ali',
            'phone' => '+201234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertServiceUnavailable()
            ->assertHeader('Retry-After', '60')
            ->assertExactJson([
                'message' => 'Account created, but the verification code could not be delivered. Please request a new code later.',
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '+201234567890',
            'phone_verified_at' => null,
        ]);
        $failedOtp = OtpCode::firstOrFail();
        $this->assertNotNull($failedOtp->delivery_failed_at);
        $this->assertFalse($failedOtp->expires_at->isFuture());
        $this->assertFalse($failedOtp->isUsable());
    }
}
