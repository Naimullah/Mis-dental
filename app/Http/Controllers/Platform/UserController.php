<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Locale;
use App\Models\User;
use App\Services\Users\PlatformAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('q');
        $search = is_string($search) ? mb_substr(trim($search), 0, 120) : '';
        $sort = match ($request->query('sort')) {
            'email' => 'email',
            'is_active' => 'is_active',
            'last_login_at' => 'last_login_at',
            default => 'name',
        };
        $status = match ($request->query('status')) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => 'all',
        };
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query = User::query()->where('scope', 'platform');
        if ($search !== '') {
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn (Builder $matches) => $matches
                ->whereRaw("LOWER(username) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(email) LIKE LOWER(?) ESCAPE '!'", [$pattern]));
        }
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }
        if ($sort === 'last_login_at') {
            $query->orderByRaw('CASE WHEN last_login_at IS NULL THEN 1 ELSE 0 END');
        }

        $filters = ['q' => $search, 'sort' => $sort, 'status' => $status, 'direction' => $direction];

        return Inertia::render('platform/security/users/index', [
            'accounts' => $query->orderBy($sort, $direction)->orderBy('id')->paginate(25, [
                'id', 'username', 'name', 'email', 'scope', 'locale_code', 'is_active', 'last_login_at',
                'email_verified_at', 'created_at',
            ])->appends($filters),
            'filters' => $filters,
            // Not named "locales": that shared prop feeds the interface language switcher.
            'languageOptions' => Locale::query()->where('is_active', true)->orderBy('code')->get(['code', 'native_name', 'english_name']),
        ]);
    }

    public function store(Request $request, PlatformAccounts $accounts): RedirectResponse
    {
        $accounts->save($this->actor($request), new User, $request->all());

        return $this->done(__('User created.'));
    }

    public function update(Request $request, User $account, PlatformAccounts $accounts): RedirectResponse
    {
        $accounts->save($this->actor($request), $account, $request->all());

        return $this->done(__('User updated.'));
    }

    public function status(Request $request, User $account, PlatformAccounts $accounts): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $accounts->setActive($this->actor($request), $account, (bool) $data['is_active']);

        return $this->done(__('User status updated.'));
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
