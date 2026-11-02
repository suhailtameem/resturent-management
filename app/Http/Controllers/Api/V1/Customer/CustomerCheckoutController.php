<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\QuoteRequest;
use App\Http\Resources\OrderResource;
use App\Services\CustomerCheckoutService;
use Illuminate\Http\JsonResponse;

class CustomerCheckoutController extends Controller
{
    public function quote(QuoteRequest $request, CustomerCheckoutService $checkout): JsonResponse
    {
        return response()->json([
            'data' => $checkout->createQuote($request->user(), $request->validated()),
        ]);
    }

    public function store(PlaceOrderRequest $request, CustomerCheckoutService $checkout): JsonResponse
    {
        $result = $checkout->placeOrder($request->user(), $request->validated());
        $status = $result['created'] ? 201 : 200;

        return (new OrderResource($result['order']))->response()->setStatusCode($status);
    }
}
