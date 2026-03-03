<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\ApiKey;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        $clients = Client::query()
            ->where('api_key_id', $apiKey->id)
            ->withCount('invoices')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        $client = $apiKey->clients()->create($request->validated());

        return response()->json($client, Response::HTTP_CREATED);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        abort_unless($client->api_key_id === $apiKey->id, Response::HTTP_NOT_FOUND);

        $client->loadCount('invoices');

        return response()->json($client);
    }

    private function resolveApiKey(Request $request): ApiKey
    {
        $apiKey = $request->attributes->get('apiKey');

        if (! $apiKey instanceof ApiKey) {
            abort(Response::HTTP_UNAUTHORIZED, 'API key context is missing.');
        }

        return $apiKey;
    }
}
