<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\GuestLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['reputation' => 50]);
    }

    private function nonAdmin(): User
    {
        return User::factory()->create(['reputation' => 0]);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->nonAdmin());
        $this->getJson('/admin/api/settings/guest-limits')->assertForbidden();
        $this->putJson('/admin/api/settings/guest-limits')->assertForbidden();
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/admin/api/settings/guest-limits')->assertStatus(401);
    }

    public function test_admin_can_read_guest_limits(): void
    {
        $this->actingAs($this->admin());

        $data = $this->getJson('/admin/api/settings/guest-limits')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('sokwe', $data);
        $this->assertArrayHasKey('hera', $data);
        $this->assertArrayHasKey('tuja', $data);

        $this->assertSame(GuestLimits::default('sokwe'), $data['sokwe']['limit']);
    }

    public function test_admin_can_update_guest_limits(): void
    {
        $this->actingAs($this->admin());

        $this->putJson('/admin/api/settings/guest-limits', [
            'sokwe' => 7,
            'hera' => 4,
            'tuja' => 2,
        ])->assertOk();

        $this->assertSame(7, GuestLimits::limit('sokwe'));
        $this->assertSame(4, GuestLimits::limit('hera'));
        $this->assertSame(2, GuestLimits::limit('tuja'));

        $data = $this->getJson('/admin/api/settings/guest-limits')->json('data');
        $this->assertSame(7, $data['sokwe']['limit']);
        $this->assertTrue($data['sokwe']['configured']);
    }

    public function test_admin_update_rejects_negative_limits(): void
    {
        $this->actingAs($this->admin());

        $this->putJson('/admin/api/settings/guest-limits', [
            'sokwe' => -1,
            'hera' => 4,
            'tuja' => 2,
        ])->assertStatus(422);
    }

    public function test_admin_can_reset_guest_limits_to_defaults(): void
    {
        $this->actingAs($this->admin());

        GuestLimits::setLimit('sokwe', 9);
        $this->assertSame(9, GuestLimits::limit('sokwe'));

        $this->postJson('/admin/api/settings/guest-limits/reset')->assertOk();

        $this->assertSame(GuestLimits::default('sokwe'), GuestLimits::limit('sokwe'));
        $this->assertFalse(GuestLimits::hasOverride('sokwe'));
    }
}