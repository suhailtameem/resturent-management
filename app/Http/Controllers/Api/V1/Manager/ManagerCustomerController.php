<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Manager\StoreManagerCustomerRequest;
use App\Http\Requests\Api\V1\Manager\UpdateManagerCustomerRequest;
use App\Http\Requests\Api\V1\Manager\UpdateManagerCustomerStatusRequest;
use App\Http\Requests\Api\V1\Manager\UpdateManagerCustomerVerificationRequest;
use App\Http\Resources\ManagerCustomerResource;
use App\Http\Resources\ManagerOrderSummaryResource;
use App\Models\Order;
use App\Models\StaffUser;
use App\Models\User;
use App\Services\ManagerCustomerMetrics;
use App\Support\SaudiPhone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerCustomerController extends Controller
{
    private const COMPLETED_ORDER_STATUSES = ['delivered', 'completed'];

    public function __construct(private readonly ManagerCustomerMetrics $metrics) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'segment' => ['sometimes', 'in:all,vip,frequent,new,inactive'],
            'status' => ['sometimes', 'in:all,active,blocked'],
            'sort_by' => ['sometimes', 'in:last_order_at,total_orders_count,total_spent_amount,created_at'],
            'sort_order' => ['sometimes', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'notification_eligible' => ['sometimes', 'boolean'],
        ]);

        $sortColumn = match ($filters['sort_by'] ?? 'last_order_at') {
            'total_orders_count' => 'completed_orders_count',
            'total_spent_amount' => 'completed_spend',
            'created_at' => 'users.created_at',
            default => 'last_order_at_calc',
        };
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $customers = $this->customerQuery($filters)
            ->withCount(['deviceTokens as notification_device_count' => fn ($query) => $query->whereNull('staff_user_id')])
            ->orderBy($sortColumn, $sortOrder)
            ->orderBy('users.id', $sortOrder)
            ->paginate((int) ($filters['per_page'] ?? 15));

        $this->attachMetrics($customers->getCollection());

        return ManagerCustomerResource::collection($customers);
    }

    public function store(StoreManagerCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $customer = DB::transaction(fn (): User => User::query()->create([
            'name' => $validated['full_name'],
            'full_name' => $validated['full_name'],
            'phone' => SaudiPhone::normalize($validated['phone']),
            'email' => $validated['email'] ?? null,
            'password' => Str::random(48),
            'preferred_language' => 'ar',
            'is_vip' => false,
            'status' => 'active',
            'phone_verified_at' => null,
            'manager_verified_at' => null,
            'manager_verified_by_staff_user_id' => null,
        ]));

        $customer->load('addresses');
        $this->attachMetrics(collect([$customer]));

        return (new ManagerCustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(User $customer): ManagerCustomerResource
    {
        $customer->load(['addresses' => fn ($query) => $query
            ->orderByDesc('is_default')
            ->orderBy('id')]);
        $this->attachMetrics(collect([$customer]));

        return new ManagerCustomerResource($customer);
    }

    public function orders(Request $request, User $customer): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', 'in:all,pending,preparing,ready,out_for_delivery,delivered,completed,cancelled,rejected'],
        ]);
        $query = $customer->orders()->getQuery()->with(['items', 'deliveryAddress', 'courier', 'user']);
        $this->applyOrderStatus($query, $filters['status'] ?? 'all');

        return ManagerOrderSummaryResource::collection(
            $query->latest('created_at')->latest('id')->paginate((int) ($filters['per_page'] ?? 15)),
        );
    }

    public function update(UpdateManagerCustomerRequest $request, User $customer): ManagerCustomerResource
    {
        $validated = $request->validated();
        $phoneChanged = isset($validated['phone'])
            && SaudiPhone::normalize($validated['phone']) !== $customer->phone;

        DB::transaction(function () use ($customer, $validated, $phoneChanged): void {
            $attributes = $validated;
            if (isset($attributes['phone'])) {
                $attributes['phone'] = SaudiPhone::normalize($attributes['phone']);
            }
            if (isset($attributes['full_name'])) {
                $attributes['name'] = $attributes['full_name'];
            }
            if ($phoneChanged) {
                $attributes['phone_verified_at'] = null;
                $attributes['manager_verified_at'] = null;
                $attributes['manager_verified_by_staff_user_id'] = null;
            }

            $customer->update($attributes);
            if ($phoneChanged) {
                $customer->tokens()->delete();
            }
        });

        $customer->refresh()->load('addresses');
        $this->attachMetrics(collect([$customer]));

        return new ManagerCustomerResource($customer);
    }

    public function updateVerification(
        UpdateManagerCustomerVerificationRequest $request,
        User $customer,
    ): JsonResponse {
        /** @var StaffUser $staffUser */
        $staffUser = $request->user('staff_api');
        $isVerified = (bool) $request->validated('is_verified');

        $customer->forceFill([
            'manager_verified_at' => $isVerified ? now() : null,
            'manager_verified_by_staff_user_id' => $isVerified ? $staffUser->id : null,
        ])->save();

        return response()->json(['data' => [
            'id' => (int) $customer->id,
            'phone_verified_at' => $customer->phone_verified_at?->toIso8601String(),
            'manager_verified_at' => $customer->manager_verified_at?->toIso8601String(),
            'manager_verified_by_staff_user_id' => $customer->manager_verified_by_staff_user_id,
            'is_manager_verified' => $customer->manager_verified_at !== null,
        ]]);
    }

    public function updateStatus(UpdateManagerCustomerStatusRequest $request, User $customer): JsonResponse
    {
        $status = $request->validated('status');
        DB::transaction(function () use ($customer, $status): void {
            $customer->update(['status' => $status]);
            if ($status === 'blocked') {
                $customer->tokens()->delete();
            }
        });

        return response()->json(['data' => ['id' => (int) $customer->id, 'status' => $customer->status]]);
    }

    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])->endOfDay()
            : CarbonImmutable::today()->endOfDay();
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])->startOfDay()
            : $to->subDays(29)->startOfDay();
        $durationDays = $from->diffInDays($to->startOfDay()) + 1;
        $previousTo = $from->subSecond();
        $previousFrom = $from->subDays($durationDays)->startOfDay();

        $current = $this->orderPeriodMetrics($from, $to);
        $previous = $this->orderPeriodMetrics($previousFrom, $previousTo);
        $activeChange = $previous['active_customers'] === 0
            ? null
            : round((($current['active_customers'] - $previous['active_customers']) / $previous['active_customers']) * 100, 1);

        return response()->json(['data' => [
            'total_customers' => User::query()->count(),
            'active_customers' => $current['active_customers'],
            'vip_customers' => User::query()->where('is_vip', true)->count(),
            'average_order_value' => number_format($current['average_order_value'], 2, '.', ''),
            'currency' => 'SAR',
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'comparison' => [
                'active_customers_change_percent' => $activeChange,
                'average_order_value_change' => number_format($current['average_order_value'] - $previous['average_order_value'], 2, '.', ''),
            ],
        ]]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'segment' => ['sometimes', 'in:all,vip,frequent,new,inactive'],
            'status' => ['sometimes', 'in:all,active,blocked'],
        ]);

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Name', 'Phone', 'Email', 'Account status', 'VIP', 'Primary address', 'Completed orders', 'Completed spend (SAR)', 'Last order at', 'Favorite item']);

            $this->customerQuery($filters)->orderBy('users.id')->chunkById(200, function (Collection $customers) use ($output): void {
                $this->attachMetrics($customers);
                foreach ($customers as $customer) {
                    $metrics = $customer->getAttribute('manager_metrics') ?? [];
                    $address = $customer->addresses->first();
                    $row = [
                        $customer->full_name ?: $customer->name,
                        $customer->phone,
                        $customer->email,
                        $customer->status,
                        $customer->is_vip ? 'Yes' : 'No',
                        $address ? implode(', ', array_filter([$address->city_ar, $address->district_ar, $address->street_details_ar])) : '',
                        (string) ($metrics['completed_orders_count'] ?? 0),
                        (string) ($metrics['completed_spend'] ?? '0.00'),
                        $metrics['last_order_at'] ? CarbonImmutable::parse($metrics['last_order_at'])->toIso8601String() : '',
                        $metrics['favorite_item'] ?? '',
                    ];
                    fputcsv($output, array_map($this->safeCsvCell(...), $row));
                }
            }, 'users.id', 'id');

            fclose($output);
        }, 'customers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<string, mixed> $filters */
    private function customerQuery(array $filters): Builder
    {
        $completedOrders = Order::query()->whereIn('order_status', self::COMPLETED_ORDER_STATUSES);
        $query = User::query()
            ->with(['addresses' => fn ($addressQuery) => $addressQuery
                ->orderByDesc('is_default')
                ->orderBy('id')])
            ->addSelect([
                'completed_orders_count' => (clone $completedOrders)->selectRaw('COUNT(*)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'completed_spend' => (clone $completedOrders)->selectRaw('COALESCE(SUM(total_final_amount), 0)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'last_order_at_calc' => Order::query()->select('created_at')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->latest('created_at')
                    ->limit(1),
            ]);

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('users.status', $filters['status']);
        }
        if (($filters['notification_eligible'] ?? false) === true || ($filters['notification_eligible'] ?? null) === '1') {
            $query->whereHas('deviceTokens', fn (Builder $devices) => $devices->whereNull('staff_user_id'));
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('users.full_name', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhereHas('addresses', fn (Builder $addressQuery) => $addressQuery
                        ->where('city_ar', 'like', "%{$search}%")
                        ->orWhere('city_en', 'like', "%{$search}%")
                        ->orWhere('district_ar', 'like', "%{$search}%")
                        ->orWhere('district_en', 'like', "%{$search}%")
                        ->orWhere('street_details_ar', 'like', "%{$search}%")
                        ->orWhere('street_details_en', 'like', "%{$search}%"));
            });
        }

        $recentCutoff = now()->subDays(30);
        match ($filters['segment'] ?? 'all') {
            'vip' => $query->where('users.is_vip', true),
            'frequent' => $query->whereHas('orders', fn (Builder $orders) => $orders
                ->whereIn('order_status', self::COMPLETED_ORDER_STATUSES), '>=', ManagerCustomerMetrics::FREQUENT_ORDER_THRESHOLD),
            'new' => $query->where('users.created_at', '>=', $recentCutoff),
            'inactive' => $query->whereDoesntHave('orders', fn (Builder $orders) => $orders
                ->whereIn('order_status', self::COMPLETED_ORDER_STATUSES)
                ->whereRaw('COALESCE(delivered_at, updated_at) >= ?', [$recentCutoff])),
            default => null,
        };

        return $query;
    }

    private function attachMetrics(Collection $customers): void
    {
        $customerIds = $customers->map(fn (User $customer): int => (int) $customer->getKey())->all();
        $metrics = $this->metrics->forCustomers($customerIds);
        foreach ($customers as $customer) {
            $customer->setAttribute('manager_metrics', $metrics[$customer->id] ?? []);
        }
    }

    private function applyOrderStatus(Builder $query, string $status): void
    {
        match ($status) {
            'delivered', 'completed' => $query->whereIn('order_status', self::COMPLETED_ORDER_STATUSES),
            'cancelled' => $query->whereIn('order_status', ['cancelled', 'rejected']),
            'all' => null,
            default => $query->where('order_status', $status),
        };
    }

    /** @return array{active_customers:int,average_order_value:float} */
    private function orderPeriodMetrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = Order::query()
            ->whereIn('order_status', self::COMPLETED_ORDER_STATUSES)
            ->whereRaw('COALESCE(delivered_at, updated_at) >= ?', [$from])
            ->whereRaw('COALESCE(delivered_at, updated_at) <= ?', [$to]);
        $aggregate = (clone $query)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_final_amount), 0) as revenue')
            ->first();

        return [
            'active_customers' => (clone $query)->distinct()->count('user_id'),
            'average_order_value' => (int) $aggregate->order_count > 0
                ? (float) $aggregate->revenue / (int) $aggregate->order_count
                : 0.0,
        ];
    }

    private function safeCsvCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');
        if ($cell !== '' && preg_match('/^[=+\-@\t\r]/', $cell)) {
            return "'".$cell;
        }

        return $cell;
    }
}
