<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-only: manage the list of companies (tenant-role accounts). Route
 * access is gated by the `role:super` middleware group in routes/web.php.
 */
class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $companies = User::where('role', User::ROLE_TENANT)
            ->withCount('teamMembers')
            ->withCount('forms')
            ->latest('created_at')
            ->paginate(10)
            ->through(fn (User $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'team_members_count' => $tenant->team_members_count,
                'forms_count' => $tenant->forms_count,
                'disabled' => $tenant->disabled_at !== null,
                'created_at' => $tenant->created_at->diffForHumans(),
            ]);

        return Inertia::render('Companies/Index', ['companies' => $companies]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_TENANT,
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Company created.');
    }

    public function update(Request $request, User $tenant): RedirectResponse
    {
        abort_unless($tenant->role === User::ROLE_TENANT, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($tenant->id)],
            'password' => 'nullable|string|min:8',
        ]);

        $tenant->name = $validated['name'];
        $tenant->email = $validated['email'];

        if (! empty($validated['password'])) {
            $tenant->password = Hash::make($validated['password']);
        }

        $tenant->save();

        return back()->with('success', 'Company updated.');
    }

    /**
     * Suspends the company — does not delete it. Forms, submissions and
     * team members are all preserved; logins and public fill URLs are
     * blocked while disabled. See restore() to reverse this.
     */
    public function destroy(Request $request, User $tenant): RedirectResponse
    {
        abort_unless($tenant->role === User::ROLE_TENANT, 404);

        // disabled_at is deliberately not mass-assignable (a client should
        // never be able to influence it) — set it directly instead.
        $tenant->disabled_at = now();
        $tenant->save();

        return back()->with('success', 'Company disabled.');
    }

    public function restore(Request $request, User $tenant): RedirectResponse
    {
        abort_unless($tenant->role === User::ROLE_TENANT, 404);

        $tenant->disabled_at = null;
        $tenant->save();

        return back()->with('success', 'Company restored.');
    }
}
