<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerOptionGroupResource;
use App\Models\OptionsGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManagerOptionGroupController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ManagerOptionGroupResource::collection(
            OptionsGroup::query()
                ->where('is_reusable', true)
                ->withCount('productRelations')
                ->with(['options' => fn ($query) => $query->orderBy('id')])
                ->orderBy('name_ar')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $group = DB::transaction(function () use ($validated): OptionsGroup {
            $group = OptionsGroup::create([
                ...Arr::except($validated, ['options']),
                'is_reusable' => true,
            ]);
            $group->options()->createMany($validated['options']);

            return $group->load('options')->loadCount('productRelations');
        });

        return (new ManagerOptionGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    public function show(OptionsGroup $optionGroup): ManagerOptionGroupResource
    {
        abort_unless($optionGroup->is_reusable, 404);

        return new ManagerOptionGroupResource(
            $optionGroup->load(['options' => fn ($query) => $query->orderBy('id')])
                ->loadCount('productRelations')
        );
    }

    public function update(Request $request, OptionsGroup $optionGroup): ManagerOptionGroupResource
    {
        abort_unless($optionGroup->is_reusable, 404);

        $validated = $request->validate($this->rules(updating: true));
        DB::transaction(function () use ($optionGroup, $validated): void {
            $optionGroup->update(Arr::except($validated, ['options']));

            if (array_key_exists('options', $validated)) {
                $optionGroup->options()->delete();
                $optionGroup->options()->createMany($validated['options']);
            }
        });

        return new ManagerOptionGroupResource(
            $optionGroup->refresh()
                ->load(['options' => fn ($query) => $query->orderBy('id')])
                ->loadCount('productRelations')
        );
    }

    public function destroy(OptionsGroup $optionGroup): JsonResponse
    {
        abort_unless($optionGroup->is_reusable, 404);

        if ($optionGroup->productRelations()->exists()) {
            return response()->json([
                'message' => 'This reusable option group cannot be deleted while products use it.',
                'code' => 'OPTION_GROUP_IN_USE',
                'errors' => (object) [],
            ], 409);
        }

        $optionGroup->delete();

        return response()->json([], 204);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';
        $optionsRequired = $updating ? 'sometimes' : 'required';

        return [
            'name_ar' => [$required, 'string', 'max:100'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type' => [$required, Rule::in(['single', 'multiple'])],
            'option_category' => [$required, Rule::in(['flavor', 'add_on'])],
            'is_required' => ['sometimes', 'boolean'],
            'options' => [$optionsRequired, 'array', 'min:1', 'max:50'],
            'options.*.name_ar' => ['required_with:options', 'string', 'max:100'],
            'options.*.name_en' => ['sometimes', 'nullable', 'string', 'max:100'],
            'options.*.description_ar' => ['sometimes', 'nullable', 'string'],
            'options.*.description_en' => ['sometimes', 'nullable', 'string'],
            'options.*.price' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'options.*.is_available' => ['sometimes', 'boolean'],
        ];
    }
}
