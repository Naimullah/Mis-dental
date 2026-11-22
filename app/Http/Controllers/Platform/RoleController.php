<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Roles\RoleQuery;
use App\Services\Roles\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** P03.04 roles (platform administration). */
class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly RoleQuery $query,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->query->filters($request);

        return Inertia::render('platform/security/roles/index', [
            'roles' => $this->query->build($filters)->paginate($filters['per_page'])->withQueryString()
                ->through(fn (Role $role): array => [
                    'id' => $role->id,
                    'code' => $role->code,
                    'name' => $role->name,
                    'scope' => $role->scope,
                    'is_system' => $role->is_system,
                ]),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = $this->roles->create($this->actor($request), $request->only(['code', 'name', 'scope', 'clinic_id', 'is_system']));

        return $this->done(__('Role :code created.', ['code' => $role->code]));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->roles->rename($this->actor($request), $role->id, $request->only(['code', 'name', 'scope', 'clinic_id', 'is_system']));

        return $this->done(__('Role :code updated.', ['code' => $role->code]));
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
