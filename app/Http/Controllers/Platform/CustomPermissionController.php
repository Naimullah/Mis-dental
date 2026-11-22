<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Services\Permissions\CustomPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomPermissionController extends Controller
{
    public function store(Request $request, CustomPermissions $permissions): RedirectResponse
    {
        $permissions->save($this->actor($request), new Permission, $request->all());

        return $this->done(__('Permission created.'));
    }

    public function update(Request $request, Permission $permission, CustomPermissions $permissions): RedirectResponse
    {
        $permissions->save($this->actor($request), $permission, $request->all());

        return $this->done(__('Permission updated.'));
    }

    public function destroy(Request $request, Permission $permission, CustomPermissions $permissions): RedirectResponse
    {
        $permissions->remove($this->actor($request), $permission);

        return $this->done(__('Permission deleted.'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
