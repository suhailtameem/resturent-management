<?php

namespace App\Http\Requests;

use App\Models\StoreOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class PlaceOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'uuid'],
            'payment_method' => ['required', 'in:cod,online'],
            'special_notes' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'scheduled_day' => ['nullable', 'required_with:scheduled_start_time,scheduled_end_time', 'in:today,tomorrow'],
            'scheduled_start_time' => ['nullable', 'required_with:scheduled_day,scheduled_end_time', 'date_format:H:i'],
            'scheduled_end_time' => ['nullable', 'required_with:scheduled_day,scheduled_start_time', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $day = $this->input('scheduled_day');
                $startTime = $this->input('scheduled_start_time');
                $endTime = $this->input('scheduled_end_time');
                if ($day === null && $startTime === null && $endTime === null) {
                    return;
                }
                if (! is_string($day) || ! is_string($startTime) || ! is_string($endTime)) {
                    return;
                }

                $timezone = StoreOperation::current()->timezone ?: config('app.timezone');
                $now = Carbon::now($timezone);
                $date = $day === 'tomorrow'
                    ? $now->copy()->addDay()->startOfDay()
                    : $now->copy()->startOfDay();
                $scheduledStart = Carbon::parse($date->toDateString().' '.$startTime, $timezone);
                $scheduledEnd = Carbon::parse($date->toDateString().' '.$endTime, $timezone);

                if (! $scheduledStart->isAfter($now)) {
                    $validator->errors()->add('scheduled_start_time', 'Choose a time later than now.');
                }
                if (! $scheduledEnd->isAfter($scheduledStart)) {
                    $validator->errors()->add('scheduled_end_time', 'The end time must be later than the start time.');
                }
            },
        ];
    }
}
