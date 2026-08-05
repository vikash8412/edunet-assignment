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
 * Tenant-only: manage this company's team (user-role) members. Route access
 * is gated by the `role:tenant` middleware group in routes/web.php.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $members = $request->user()->teamMembers()
            ->latest('created_at')
            ->paginate(10)
            ->through(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'created_at' => $member->created_at->diffForHumans(),
            ]);

        return Inertia::render('Team/Index', ['members' => $members]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $request->user()->teamMembers()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_USER,
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Team member added.');
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $this->authorizeTeamMember($request, $member);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'password' => 'nullable|string|min:8',
        ]);

        $member->name = $validated['name'];
        $member->email = $validated['email'];

        if (! empty($validated['password'])) {
            $member->password = Hash::make($validated['password']);
        }

        $member->save();

        return back()->with('success', 'Team member updated.');
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $this->authorizeTeamMember($request, $member);

        // No cascade concern: a user-role account owns no tenant_id-scoped
        // data itself (that data belongs to the tenant), so removing a
        // teammate never touches forms/generations/imports.
        $member->delete();

        return back()->with('success', 'Team member removed.');
    }

    private function authorizeTeamMember(Request $request, User $member): void
    {
        abort_unless(
            $member->role === User::ROLE_USER && $member->tenant_id === $request->user()->id,
            404,
        );
    }
}
