<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Locale;
use App\Models\Permission;
use App\Services\Permissions\PermissionTranslations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermissionTranslationController extends Controller
{
    public function update(Request $request, Permission $permission, Locale $locale, PermissionTranslations $translations): RedirectResponse
    {
        $translations->save($permission, $locale, $request->only(['display_name', 'description']));

        return $this->done(__('Translations for :name saved.', ['name' => $permission->code]));
    }

    public function destroy(Permission $permission, Locale $locale, PermissionTranslations $translations): RedirectResponse
    {
        $translations->remove($permission, $locale);

        return $this->done(__('Translation removed.'));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
