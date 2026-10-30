<?php

namespace App\Http\Requests\Calendar;

use App\Support\E2E;
use App\Http\Requests\Concerns\ChoosesSpace;
use App\Models\Event;
use App\Support\LocalDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventRequest extends FormRequest
{
    use ChoosesSpace;

    /**
     * Checked before validation, so a stranger's id answers 404 rather than
     * revealing anything through validation errors.
     */
    public function authorize(): bool
    {
        if ($item = $this->route('event')) {
            Gate::authorize('update', $item);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => ($d = trim((string) $this->input('description'))) === '' ? null : $d,
            'all_day' => $this->boolean('all_day'),
            'start_time' => $this->input('start_time') ?: null,
            'end_date' => $this->input('end_date') ?: null,
            'end_time' => $this->input('end_time') ?: null,
            'reminder_minutes' => $this->input('reminder_minutes') === '' ? null : $this->input('reminder_minutes'),
            'repeat_until' => $this->input('repeat_until') ?: null,
            'category' => $this->input('category') ?: null,
        ]);
    }

    public function rules(): array
    {
        $dateRule = ['date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'];

        return [
            'space' => $this->isMethod('post') ? $this->spaceRules() : ['prohibited'],
            'title' => ['required', ...E2E::text(120)],
            'description' => ['nullable', ...E2E::text(2000)],
            'all_day' => ['boolean'],
            'start_date' => ['required', ...$dateRule],
            'start_time' => ['nullable', 'required_if:all_day,false', 'date_format:H:i'],
            'end_date' => ['nullable', ...$dateRule],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reminder_minutes' => ['nullable', 'integer', Rule::in(Event::REMINDER_OPTIONS)],
            'repeat' => ['nullable', Rule::in(Event::REPEATS)],
            'repeat_until' => ['nullable', ...$dateRule, 'after_or_equal:start_date'],
            'category' => ['nullable', Rule::in(Event::CATEGORIES)],
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'tanggal mulai', 'start_time' => 'jam mulai',
            'end_date' => 'tanggal selesai', 'end_time' => 'jam selesai',
            'reminder_minutes' => 'pengingat', 'space' => 'ruang',
        ];
    }

    public function messages(): array
    {
        return ['start_time.required_if' => 'Jam mulai wajib diisi, kecuali acara sepanjang hari.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            [$start, $end] = $this->range();
            if ($end && $end->lessThan($start)) {
                $validator->errors()->add('end_time', 'Waktu selesai tidak boleh sebelum waktu mulai.');
            }
        }];
    }

    /** @return array{0: CarbonImmutable, 1: ?CarbonImmutable} */
    private function range(): array
    {
        $tz = $this->user()->timezone;
        $allDay = $this->boolean('all_day');
        $start = LocalDateTime::toUtc($this->input('start_date'), $allDay ? null : $this->input('start_time'), $tz);

        $end = null;
        if ($this->input('end_date') || $this->input('end_time')) {
            $endDate = $this->input('end_date') ?: $this->input('start_date');
            $end = LocalDateTime::toUtc($endDate, $allDay ? null : $this->input('end_time'), $tz, endOfDay: $allDay || ! $this->input('end_time'));
        } elseif ($allDay) {
            $end = LocalDateTime::toUtc($this->input('start_date'), null, $tz, endOfDay: true);
        }

        return [$start, $end];
    }

    /** @return array<string, mixed> */
    public function eventAttributes(): array
    {
        [$start, $end] = $this->range();

        return [
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'all_day' => $this->boolean('all_day'),
            'starts_at' => $start,
            'ends_at' => $end,
            'timezone' => $this->user()->timezone,
            'reminder_minutes' => $this->validated('reminder_minutes') === null ? null : (int) $this->validated('reminder_minutes'),
            'repeat' => $this->validated('repeat') ?: 'none',
            'repeat_until' => ($this->validated('repeat') ?: 'none') === 'none' ? null : $this->validated('repeat_until'),
            'category' => $this->validated('category'),
        ];
    }
}
