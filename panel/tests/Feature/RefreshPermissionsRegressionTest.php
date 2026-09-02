<?php

namespace Tests\Feature;

use App\Models\ActiveSession;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshPermissionsRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_all_user_permissions_bumps_cache_version_and_active_sessions(): void
    {
        $user = User::create([
            'name'      => 'Tedarikçi',
            'role'      => 'reseller',
            'email'     => 'reseller@test.local',
            'password'  => bcrypt('password'),
            'person_id' => 4242,
            'grp_code'  => 'GDZ',
        ]);

        ActiveSession::create([
            'user_id'            => $user->id,
            'session_id'         => 'test-session',
            'ip'                 => '127.0.0.1',
            'permission_version' => '0',
        ]);

        $service = new PermissionService();
        $service->cacheUserPermissions(4242, ['per-01-01']);
        $oldVersion = $service->getCachedUserPermissionVersion(4242);

        $this->assertTrue(refreshAllUserPermissions());

        $this->assertNotSame($oldVersion, $service->getCachedUserPermissionVersion(4242));
        $this->assertNotSame(
            '0',
            ActiveSession::where('user_id', $user->id)->first()->permission_version
        );
    }
}
