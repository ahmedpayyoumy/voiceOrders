<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebhookEndpointRequest;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;

class WebhookEndpointController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->webhookEndpoints()->latest()->paginate(20);
    }

    public function store(WebhookEndpointRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $endpoint = WebhookEndpoint::create($data);

        return response()->json($endpoint, 201);
    }

    public function show(Request $request, WebhookEndpoint $webhookEndpoint)
    {
        if ($webhookEndpoint->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($webhookEndpoint);
    }

    public function update(WebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint)
    {
        if ($webhookEndpoint->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $webhookEndpoint->update($request->validated());

        return response()->json($webhookEndpoint);
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint)
    {
        if ($webhookEndpoint->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $webhookEndpoint->delete();

        return response()->json(['message' => 'Webhook endpoint deleted'], 200);
    }
}
