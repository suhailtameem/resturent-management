<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Services\NotificationAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationOpenController extends Controller
{
    public function __invoke(Request $request, int $notification, NotificationAnalyticsService $analytics): JsonResponse
    {
        $analytics->open($notification, (int) $request->user()->getAuthIdentifier());

        return response()->json(['data' => ['opened' => true]]);
    }
}
