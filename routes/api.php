<?php

use App\Http\Controllers\Api\V1\Customer\CategoryController;
use App\Http\Controllers\Api\V1\Customer\CustomerAddressController;
use App\Http\Controllers\Api\V1\Customer\CustomerAuthController;
use App\Http\Controllers\Api\V1\Customer\CustomerCheckoutController;
use App\Http\Controllers\Api\V1\Customer\CustomerNotificationController;
use App\Http\Controllers\Api\V1\Customer\CustomerOfferController;
use App\Http\Controllers\Api\V1\Customer\CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\CustomerProfileController;
use App\Http\Controllers\Api\V1\Customer\CustomerStoreSettingsController;
use App\Http\Controllers\Api\V1\Customer\MenuController;
use App\Http\Controllers\Api\V1\Customer\NotificationOpenController;
use App\Http\Controllers\Api\V1\Customer\ProductController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\Manager\ManagerAuthController;
use App\Http\Controllers\Api\V1\Manager\ManagerCategoryController;
use App\Http\Controllers\Api\V1\Manager\ManagerCourierController;
use App\Http\Controllers\Api\V1\Manager\ManagerCustomerController;
use App\Http\Controllers\Api\V1\Manager\ManagerDeliveryCityController;
use App\Http\Controllers\Api\V1\Manager\ManagerDeliveryNeighborhoodController;
use App\Http\Controllers\Api\V1\Manager\ManagerDriverController;
use App\Http\Controllers\Api\V1\Manager\ManagerNotificationController;
use App\Http\Controllers\Api\V1\Manager\ManagerOfferController;
use App\Http\Controllers\Api\V1\Manager\ManagerOptionGroupController;
use App\Http\Controllers\Api\V1\Manager\ManagerOrderController;
use App\Http\Controllers\Api\V1\Manager\ManagerProductController;
use App\Http\Controllers\Api\V1\Manager\ManagerProductSizeController;
use App\Http\Controllers\Api\V1\Manager\ManagerProfileController;
use App\Http\Controllers\Api\V1\Manager\ManagerStoreOperationController;
use App\Http\Controllers\Api\V1\Manager\ManagerStoreSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // List active menu categories.
    Route::get('/categories', [CategoryController::class, 'index']);

    // Browse and search available menu products.
    Route::get('/products', [ProductController::class, 'index']);

    // Get a product with its sizes, flavors, and add-ons.
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Customer startup lookups: store settings, order intake status, currency, operations, and delivery zones.
    Route::get('/startup-lookups', [CustomerStoreSettingsController::class, 'show']);

    // Legacy alias for startup lookups.
    Route::get('/customer/store/settings', [CustomerStoreSettingsController::class, 'show']);

    // Request a customer OTP; delivery is enabled only in local/testing until a provider is configured.
    Route::post('/auth/login-phone', [CustomerAuthController::class, 'sendOtp'])->middleware('throttle:5,1');

    // Verify a one-time code and issue a customer token.
    Route::post('/auth/verify-otp', [CustomerAuthController::class, 'verifyOtp'])->middleware('throttle:5,1');

    // Exchange a rotating customer refresh token for a new access token.
    Route::post('/auth/refresh', [CustomerAuthController::class, 'refresh'])->middleware('throttle:10,1');

    // Upgrade a still-valid legacy access token to a rotating refresh-token session.
    Route::post('/auth/renew', [CustomerAuthController::class, 'renew'])->middleware(['auth:sanctum', 'throttle:10,1']);

    Route::prefix('manager')->group(function () {
        // Authenticate a manager and issue a staff Sanctum token.
        Route::post('/auth/login', [ManagerAuthController::class, 'login'])->middleware('throttle:5,1');

        // Revoke the current manager access token.
        Route::post('/auth/logout', [ManagerAuthController::class, 'logout'])->middleware('auth:staff_api');

        // Return the configured currency labels for authenticated staff screens.
        Route::get('/store/currency-labels', [ManagerStoreSettingsController::class, 'showCurrency'])->middleware('auth:staff_api');

        Route::prefix('courier')->middleware(['auth:staff_api', 'staff.role:courier'])->group(function () {
            // Return the signed-in courier's availability, assigned orders, and daily totals.
            Route::get('/dashboard', [ManagerCourierController::class, 'dashboard']);

            // Update whether the signed-in courier can receive new assignments.
            Route::patch('/availability', [ManagerCourierController::class, 'updateAvailability']);

            // Confirm pickup of an assigned ready order and start its delivery.
            Route::post('/orders/{order}/pickup', [ManagerCourierController::class, 'pickUp']);

            // Confirm delivery of an assigned order and record COD collection when applicable.
            Route::post('/orders/{order}/deliver', [ManagerCourierController::class, 'deliver']);

            // Record a delivery issue without cancelling or changing the order status.
            Route::post('/orders/{order}/issues', [ManagerCourierController::class, 'reportIssue']);
        });

        Route::middleware(['auth:staff_api', 'staff.role:super_admin'])->group(function () {
            // Read the authenticated manager's profile.
            Route::get('/profile', [ManagerProfileController::class, 'show']);

            // Update the authenticated manager's contact and account details.
            Route::patch('/profile', [ManagerProfileController::class, 'update']);

            // Change the authenticated manager's password after verifying the current password.
            Route::put('/profile/password', [ManagerProfileController::class, 'updatePassword'])
                ->middleware('throttle:5,1');

            // Return customer-device reach counts for push notifications.
            Route::get('/notifications/reach', [ManagerNotificationController::class, 'reach']);
            // List notification history and delivery statistics.
            Route::get('/notifications', [ManagerNotificationController::class, 'index']);
            // Delete selected notification history and cancel queued recipient records, or clear all push history.
            Route::delete('/notifications', [ManagerNotificationController::class, 'destroyMany']);
            // Queue notification delivery to selected or all active customers.
            Route::post('/notifications', [ManagerNotificationController::class, 'store']);
            // Show delivery results for a notification.
            Route::get('/notifications/{notification}', [ManagerNotificationController::class, 'show']);
            // Register or remove this manager device's FCM token.
            Route::post('/device-tokens/fcm', [DeviceTokenController::class, 'store']);
            Route::delete('/device-tokens/fcm', [DeviceTokenController::class, 'destroy']);

            // Read branch/store profile and payment settings for the single configured store.
            Route::get('/settings', [ManagerStoreSettingsController::class, 'show']);

            // Update branch/store profile and payment settings.
            Route::patch('/settings', [ManagerStoreSettingsController::class, 'update']);

            // Update display labels without changing the store currency or any monetary amounts.
            Route::patch('/store/settings', [ManagerStoreSettingsController::class, 'updateCurrency']);

            // Read current store status, capacity, preparation time, and weekly operating schedule.
            Route::get('/hours', [ManagerStoreOperationController::class, 'show']);

            // Replace the weekly operating schedule and capacity configuration.
            Route::put('/hours', [ManagerStoreOperationController::class, 'update']);

            // Pause order intake for a duration or until the next local calendar day.
            Route::post('/hours/pause', [ManagerStoreOperationController::class, 'pause']);

            // Close order intake until a manager manually resumes it.
            Route::post('/hours/close', [ManagerStoreOperationController::class, 'close']);

            // Resume order intake and clear any temporary pause.
            Route::post('/hours/resume', [ManagerStoreOperationController::class, 'resume']);

            // List delivery cities and nested neighborhoods.
            Route::get('/delivery-cities', [ManagerDeliveryCityController::class, 'index']);

            // Create a delivery city.
            Route::post('/delivery-cities', [ManagerDeliveryCityController::class, 'store']);

            // Update a delivery city's customer-facing service status.
            Route::patch('/delivery-cities/{city}', [ManagerDeliveryCityController::class, 'update']);

            // Add a neighborhood to a delivery city.
            Route::post('/delivery-cities/{city}/neighborhoods', [ManagerDeliveryNeighborhoodController::class, 'store']);

            // Update a neighborhood's name, pricing, or service availability.
            Route::patch('/delivery-neighborhoods/{neighborhood}', [ManagerDeliveryNeighborhoodController::class, 'update']);

            // List reusable product sizes.
            Route::get('/product-sizes', [ManagerProductSizeController::class, 'index']);

            // Create a reusable product size.
            Route::post('/product-sizes', [ManagerProductSizeController::class, 'store']);

            // Get a reusable product size.
            Route::get('/product-sizes/{size}', [ManagerProductSizeController::class, 'show']);

            // Update a reusable product size and its attached product variants.
            Route::match(['put', 'patch'], '/product-sizes/{size}', [ManagerProductSizeController::class, 'update']);

            // Delete a reusable product size when no products use it.
            Route::delete('/product-sizes/{size}', [ManagerProductSizeController::class, 'destroy']);

            // List reusable option groups and their choices.
            Route::get('/option-groups', [ManagerOptionGroupController::class, 'index']);

            // Create a reusable option group with its choices.
            Route::post('/option-groups', [ManagerOptionGroupController::class, 'store']);

            // Get a reusable option group and its choices.
            Route::get('/option-groups/{optionGroup}', [ManagerOptionGroupController::class, 'show']);

            // Update a reusable option group and optionally replace its choices.
            Route::match(['put', 'patch'], '/option-groups/{optionGroup}', [ManagerOptionGroupController::class, 'update']);

            // Delete a reusable option group when no products use it.
            Route::delete('/option-groups/{optionGroup}', [ManagerOptionGroupController::class, 'destroy']);

            // List all categories, including inactive categories.
            Route::get('/categories', [ManagerCategoryController::class, 'index']);

            // Create a category.
            Route::post('/categories', [ManagerCategoryController::class, 'store']);

            // Show a category.
            Route::get('/categories/{category}', [ManagerCategoryController::class, 'show']);

            // Update a category.
            Route::match(['put', 'patch'], '/categories/{category}', [ManagerCategoryController::class, 'update']);

            // Delete a category that has no products.
            Route::delete('/categories/{category}', [ManagerCategoryController::class, 'destroy']);

            // List products with their sizes and option groups.
            Route::get('/products', [ManagerProductController::class, 'index']);

            // Create a product with nested sizes and option groups.
            Route::post('/products', [ManagerProductController::class, 'store']);

            // Show a product with nested sizes and option groups.
            Route::get('/products/{product}', [ManagerProductController::class, 'show']);

            // Update product fields and optionally replace nested sizes or option groups.
            Route::match(['put', 'patch'], '/products/{product}', [ManagerProductController::class, 'update']);

            // Upload or replace a product image.
            Route::post('/products/{product}/image', [ManagerProductController::class, 'uploadImage']);

            // Remove a product image.
            Route::delete('/products/{product}/image', [ManagerProductController::class, 'deleteImage']);

            // Delete a product only when no order history references it.
            Route::delete('/products/{product}', [ManagerProductController::class, 'destroy']);

            // List active couriers, or include inactive couriers for management.
            Route::get('/drivers', [ManagerDriverController::class, 'index']);

            // Create a courier account.
            Route::post('/drivers', [ManagerDriverController::class, 'store']);

            // Update a courier profile or active status.
            Route::patch('/drivers/{driver}', [ManagerDriverController::class, 'update']);

            // Deactivate a courier account while preserving order history.
            Route::delete('/drivers/{driver}', [ManagerDriverController::class, 'destroy']);

            // List a courier's delivered orders for the selected accounting period.
            Route::get('/drivers/{driver}/accounting', [ManagerDriverController::class, 'accounting']);

            // Reconcile all pending cash collected by a courier.
            Route::post('/drivers/{driver}/cash-settlements', [ManagerDriverController::class, 'settleCash']);

            // Export customers using the current list filters as UTF-8 CSV.
            Route::get('/customers/export', [ManagerCustomerController::class, 'export']);

            // Return customer KPI statistics for a selected date period.
            Route::get('/customers/stats', [ManagerCustomerController::class, 'stats']);

            // Create a customer profile that must still complete phone OTP verification.
            Route::post('/customers', [ManagerCustomerController::class, 'store']);

            // List customers with search, segment, status, sorting, and pagination.
            Route::get('/customers', [ManagerCustomerController::class, 'index']);

            // Show customer profile, addresses, and computed order summary.
            Route::get('/customers/{customer}', [ManagerCustomerController::class, 'show']);

            // List paginated orders that belong to the selected customer.
            Route::get('/customers/{customer}/orders', [ManagerCustomerController::class, 'orders']);

            // Update customer identity details and VIP status.
            Route::patch('/customers/{customer}', [ManagerCustomerController::class, 'update']);

            // Record or clear the manager's administrative customer verification.
            Route::patch('/customers/{customer}/verification', [ManagerCustomerController::class, 'updateVerification']);

            // Block or reactivate a customer account and revoke tokens when blocked.
            Route::patch('/customers/{customer}/status', [ManagerCustomerController::class, 'updateStatus']);

            // KPI statistics for orders dashboard.
            Route::get('/orders/stats', [ManagerOrderController::class, 'stats']);

            // Real-time polling endpoint for orders updates.
            Route::get('/orders/poll', [ManagerOrderController::class, 'poll']);

            // List customer orders for management with filters and pagination.
            Route::get('/orders', [ManagerOrderController::class, 'index']);
            // Delete selected orders and their dependent records.
            Route::delete('/orders', [ManagerOrderController::class, 'destroyMany']);

            // Show order details for management.
            Route::get('/orders/{order}', [ManagerOrderController::class, 'show']);

            // Update order status lifecycle and log transition.
            Route::patch('/orders/{order}/status', [ManagerOrderController::class, 'updateStatus']);

            // Assign delivery driver to an order.
            Route::post('/orders/{order}/assign-driver', [ManagerOrderController::class, 'assignDriver']);

            // Cancel/reject order with reason.
            Route::post('/orders/{order}/cancel', [ManagerOrderController::class, 'cancel']);

            // Thermal printer ticket and ESC/POS payload for kitchen and invoice.
            Route::get('/orders/{order}/print-ticket', [ManagerOrderController::class, 'printTicket']);

            // KPI summary for the offers and discounts dashboard.
            Route::get('/offers/stats', [ManagerOfferController::class, 'stats']);

            // List offers with filters (status, type, search) and pagination.
            Route::get('/offers', [ManagerOfferController::class, 'index']);

            // Create a new promotional offer or coupon code.
            Route::post('/offers', [ManagerOfferController::class, 'store']);

            // Show full offer details for viewing or editing.
            Route::get('/offers/{id}', [ManagerOfferController::class, 'show']);

            // Update an existing offer configuration and limits.
            Route::match(['put', 'patch'], '/offers/{id}', [ManagerOfferController::class, 'update']);

            // Toggle active status for an offer immediately.
            Route::patch('/offers/{id}/toggle-status', [ManagerOfferController::class, 'toggleStatus']);

            // Delete or soft-delete/archive an offer.
            Route::delete('/offers/{id}', [ManagerOfferController::class, 'destroy']);
        });
    });

    // List currently active offers for the customer home page popup.
    Route::get('/customer/offers', [CustomerOfferController::class, 'activeOffers']);

    // Customer checkout: validate and apply coupon code to cart.
    Route::post('/customer/cart/apply-coupon', [CustomerOfferController::class, 'applyCoupon']);
    Route::post('/cart/apply-coupon', [CustomerOfferController::class, 'applyCoupon']);

    // Customer catalog: menu items with active automatic discounts.
    Route::get('/customer/menu/discounted-items', [CustomerOfferController::class, 'discountedItems']);
    Route::get('/menu/discounted-items', [CustomerOfferController::class, 'discountedItems']);

    // List the authenticated customer's orders.
    Route::get('/orders', [CustomerOrderController::class, 'index'])->middleware('auth:sanctum');

    // Load full order details screen belonging to the authenticated customer.
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->middleware('auth:sanctum');

    // Lightweight live order tracking endpoint for polling in the customer app.
    Route::get('/orders/{order}/track', [CustomerOrderController::class, 'track'])->middleware('auth:sanctum');

    Route::prefix('customer')->group(function () {
        // Backward-compatible endpoint for requesting an OTP.
        Route::post('/auth/send-otp', [CustomerAuthController::class, 'sendOtp'])->middleware('throttle:5,1');

        // Backward-compatible endpoint for verifying an OTP.
        Route::post('/auth/verify-otp', [CustomerAuthController::class, 'verifyOtp'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            // Backward-compatible customer profile endpoint.
            Route::get('/profile', [CustomerProfileController::class, 'show']);

            // Backward-compatible profile update endpoint.
            Route::put('/profile', [CustomerProfileController::class, 'update']);

            // Backward-compatible menu endpoint.
            Route::get('/menu', [MenuController::class, 'index']);

            // Backward-compatible product details endpoint.
            Route::get('/products/{product}', [MenuController::class, 'show']);
        });
    });

    // Revoke the current customer access token.
    Route::post('/auth/logout', [CustomerAuthController::class, 'logout'])->middleware('auth:sanctum');

    // Register or remove this customer's FCM device token.
    Route::post('/device-tokens/fcm', [DeviceTokenController::class, 'store'])->middleware('auth:sanctum');
    Route::delete('/device-tokens/fcm', [DeviceTokenController::class, 'destroy'])->middleware('auth:sanctum');
    // List manager notifications addressed to the signed-in customer.
    Route::get('/notifications', [CustomerNotificationController::class, 'index'])->middleware('auth:sanctum');

    // Record an open event for a notification sent to the signed-in customer.
    Route::post('/notifications/{notification}/opened', NotificationOpenController::class)->middleware('auth:sanctum');

    // Return the authenticated customer and saved addresses.
    Route::get('/me', [CustomerProfileController::class, 'show'])->middleware('auth:sanctum');

    // Update customer profile fields.
    Route::patch('/me', [CustomerProfileController::class, 'update'])->middleware('auth:sanctum');

    // List saved addresses for the authenticated customer.
    Route::get('/addresses', [CustomerAddressController::class, 'index'])->middleware('auth:sanctum');

    // Save an address for the authenticated customer.
    Route::post('/addresses', [CustomerAddressController::class, 'store'])->middleware('auth:sanctum');

    // Edit a saved address owned by the authenticated customer.
    Route::patch('/addresses/{address}', [CustomerAddressController::class, 'update'])->middleware('auth:sanctum');

    // Delete a saved address owned by the authenticated customer.
    Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy'])->middleware('auth:sanctum');

    // Calculate authoritative totals for a pickup cart.
    Route::post('/checkout/quote', [CustomerCheckoutController::class, 'quote'])->middleware('auth:sanctum');

    // Place a COD order from a valid quote.
    Route::post('/orders', [CustomerCheckoutController::class, 'store'])->middleware('auth:sanctum');

    // Cancel the authenticated customer's order before preparation.
    Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});
