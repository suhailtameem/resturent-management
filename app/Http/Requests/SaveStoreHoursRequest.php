<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveStoreHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orders_per_hour_limit' => ['required', 'integer', 'min:5', 'max:500'],
            'estimated_prep_time_minutes' => ['required', 'integer', 'min:10', 'max:360', 'multiple_of:5'],
            'rush_extra_time_enabled' => ['required', 'boolean'],
            'weekly_schedule' => ['required', 'array', 'size:7'],
            'weekly_schedule.*' => ['required', 'array:day_of_week,is_active,sessions'],
            'weekly_schedule.*.day_of_week' => [
                'required',
                'string',
                'distinct',
                'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            ],
            'weekly_schedule.*.is_active' => ['required', 'boolean'],
            'weekly_schedule.*.sessions' => ['present', 'array'],
            'weekly_schedule.*.sessions.*' => ['required', 'array:name,opens_at,closes_at,closes_next_day'],
            'weekly_schedule.*.sessions.*.name' => ['required', 'string', 'max:30'],
            'weekly_schedule.*.sessions.*.opens_at' => ['required', 'date_format:H:i'],
            'weekly_schedule.*.sessions.*.closes_at' => ['required', 'date_format:H:i'],
            'weekly_schedule.*.sessions.*.closes_next_day' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = [
                    'sunday' => 0,
                    'monday' => 1,
                    'tuesday' => 2,
                    'wednesday' => 3,
                    'thursday' => 4,
                    'friday' => 5,
                    'saturday' => 6,
                ];
                $intervals = [];

                foreach ($this->input('weekly_schedule') as $dayIndex => $day) {
                    if (! $day['is_active']) {
                        continue;
                    }

                    $dayOffset = $days[$day['day_of_week']] * 1440;

                    foreach ($day['sessions'] as $sessionIndex => $session) {
                        [$openHour, $openMinute] = array_map('intval', explode(':', $session['opens_at']));
                        [$closeHour, $closeMinute] = array_map('intval', explode(':', $session['closes_at']));
                        $start = $dayOffset + ($openHour * 60) + $openMinute;
                        $end = $dayOffset + ($closeHour * 60) + $closeMinute
                            + ($session['closes_next_day'] ? 1440 : 0);
                        $field = "weekly_schedule.{$dayIndex}.sessions.{$sessionIndex}.closes_at";

                        if ($end <= $start || $end - $start > 1440) {
                            $validator->errors()->add($field, 'The closing time must be after the opening time and within 24 hours.');

                            continue;
                        }

                        foreach ($intervals as [$existingStart, $existingEnd]) {
                            foreach ([-10080, 0, 10080] as $weekOffset) {
                                if ($start < $existingEnd + $weekOffset && $end > $existingStart + $weekOffset) {
                                    $validator->errors()->add($field, 'Operating sessions must not overlap.');

                                    continue 3;
                                }
                            }
                        }

                        $intervals[] = [$start, $end];
                    }
                }
            },
        ];
    }
}
