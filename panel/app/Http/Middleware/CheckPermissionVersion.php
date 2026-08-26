<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActiveSession;
use App\Services\PermissionService;

class CheckPermissionVersion
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if(!$user) return $next($request);

        // find active session by sanctum token id or session id
        $tokenId = null;
        if($request->bearerToken()){
            // Sanctum tokens are stored as plain text tokens; extract token id if possible
            $parts = explode('|', $request->bearerToken());
            $tokenId = $parts[0] ?? null;
        }

        $sessionId = session()->getId();

        $active = ActiveSession::where('user_id', $user->id)
            ->when($tokenId, function($q) use ($tokenId){ return $q->where('token_id', $tokenId); })
            ->when(!$tokenId && $sessionId, function($q) use ($sessionId){ return $q->where('session_id', $sessionId); })
            ->first();

        $permissionService = new PermissionService();
        $currentVersion = $permissionService->getCachedUserPermissionVersion($user->person_id);

        // If versions mismatch, allow the permissions-refresh endpoint to run a soft refresh
        $isRefreshEndpoint = $request->is('v1/getpermissions') || $request->routeIs('get-permissions') || $request->path() === 'v1/getpermissions';




        if ($active && $active->force_logout) {
            $reason = $active->force_logout_reason;

            try {
                if ($active->token_id) {
                    $user->tokens()->where('id', $active->token_id)->delete();
                }
            } catch (\Throwable $e) {}

            try {
                auth('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } catch (\Throwable $e) {}

            $active->delete();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'force_logout',
                    'reason' => $reason ?? 'Oturumunuz sistem tarafından sonlandırıldı. Lütfen tekrar giriş yapın.',
                ], 401);
            }

            return redirect()->route('login')->with('login-error', 'Oturumunuz sistem tarafından sonlandırıldı. Lütfen tekrar giriş yapın.'); 
        }

        if($active && $active->permission_version && $currentVersion && $active->permission_version !== (string)$currentVersion){
            try{
                $permissionService->loadPermissionsToSession($user);
                $newVersion = $permissionService->getCachedUserPermissionVersion($user->person_id);
                $active->permission_version = (string)$newVersion;
                try{ $active->current_status = session('currentStatus') ?? $active->current_status; } catch(\Throwable $_){}
                $active->last_seen = now();
                $active->save();
            }catch(\Throwable $e){
                // swallow
            }
            return $next($request);
        }

        // update last seen and continue
        if($active){
            $active->last_seen = now();
            $active->save();
        }

        return $next($request);
    }
}
