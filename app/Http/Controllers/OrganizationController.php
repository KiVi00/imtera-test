<?php

namespace App\Http\Controllers;

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
                Rule::unique('organizations', 'url')->where('user_id', $request->user()->id),
            ],
        ]);

        $organization = $request->user()->organizations()->create([
            'url' => $data['url'],
            'parse_status' => 'pending',
        ]);

        // TODO: Здесь будет запуск парсинга

        return response()->json($organization, 201);
    }
}
