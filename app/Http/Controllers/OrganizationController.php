<?php

namespace App\Http\Controllers;

use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->organizations()->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => [
                'required',
                'string',
                'url',
                'max:2048',
                'regex:~yandex\.(ru|com)/maps/org/.+/\d+~',
                Rule::unique('organizations', 'url')->where('user_id', $request->user()->id),
            ],
        ]);

        $organization = $request->user()->organizations()->create([
            'url' => $data['url'],
            'parse_status' => 'pending',
        ]);

        ParseOrganizationJob::dispatch($organization->id);

        return response()->json($organization, 201);
    }

    public function show(Request $request, Organization $organization)
    {
        abort_unless($organization->user_id === $request->user()->id, 403);

        return $organization;
    }

    public function reviews(Request $request, Organization $organization)
    {
        abort_unless($organization->user_id === $request->user()->id, 403);

        $perPage = min((int) $request->input('per_page', 50), 100);

        return $organization->reviews()
            ->orderByDesc('date')
            ->paginate($perPage);
    }

    public function refresh(Request $request, Organization $organization)
    {
        abort_unless($organization->user_id === $request->user()->id, 403);

        $organization->update([
            'parse_status' => 'pending',
            'parse_error' => null,
        ]);

        ParseOrganizationJob::dispatch($organization->id);

        return response()->json($organization);
    }
}
