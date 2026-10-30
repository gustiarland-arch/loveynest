<?php

namespace App\Http\Requests\Tasks;

use App\Support\E2E;
use App\Http\Requests\Concerns\ChoosesSpace;
use App\Models\Task;
use App\Support\LocalDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    use ChoosesSpace;

    /**
     * Checked before validation, so a stranger's id answers 404 rather than
     * revealing anything through validation errors.
     */
    public function authorize(): bool
    {
        if ($item = $this->route('task')) {
            Gate::authorize('update', $item);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => ($d = trim((string) $this->input('description'))) === '' ? null : $d,
            'due_date' => $this->input('due_date') ?: null,
            'category' => ($c = trim((string) $this->input('category'))) === '' ? null : $c,
            'due_time' => $this->input('due_time') ?: null,
        ]);
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            // The space of an existing task cannot change.
            'space' => $creating ? $this->spaceRules() : ['prohibited'],
            'title' => ['required', ...E2E::text(120)],
            'description' => ['nullable', ...E2E::text(2000)],
            'status' => ['sometimes', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'category' => ['nullable', 'string', 'max:30'],
            'repeat' => ['sometimes', Rule::in(Task::REPEATS)],
            'subtasks' => ['sometimes', 'array', 'max:20'],
            'subtasks.*.title' => ['required', ...E2E::text(120)],
            'subtasks.*.done' => ['sometimes', 'boolean'],
            // Only a member of the couple can take a shared task; personal tasks have no assignee.
            'assignee_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if ($value !== null && ! $this->user()->activeSpace()?->activeMembers()->where('users.id', $value)->exists()) {
                    $fail('Pilih salah satu dari kalian berdua.');
                }
            }],
        ];
    }

    public function attributes(): array
    {
        return ['due_date' => 'tanggal tenggat', 'due_time' => 'jam tenggat', 'space' => 'ruang'];
    }

    /** @return list<array{title: string, done: bool}>|null null = leave the checklist alone */
    public function subtaskRows(): ?array
    {
        if (! $this->has('subtasks')) {
            return null;
        }

        return collect($this->validated('subtasks') ?? [])->map(fn ($row) => ['title' => trim($row['title']), 'done' => (bool) ($row['done'] ?? false)])
            ->filter(fn ($row) => $row['title'] !== '')->values()->all();
    }

    /** @return array<string, mixed> */
    public function taskAttributes(): array
    {
        $timezone = $this->user()->timezone;
        $date = $this->validated('due_date');
        $time = $date ? $this->validated('due_time') : null;

        $attributes = [
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'priority' => $this->validated('priority'),
            'category' => $this->validated('category'),
            // A repeating task needs a due date to repeat from.
            'repeat' => $date ? ($this->validated('repeat') ?? 'none') : 'none',
            'due_at' => $date ? LocalDateTime::toUtc($date, $time, $timezone, endOfDay: true) : null,
            'due_has_time' => $time !== null,
            'due_timezone' => $date ? $timezone : null,
        ];

        $task = $this->route('task');
        $shared = $task ? $task->isShared() : $this->scope()->space() === 'ours';
        if ($this->has('assignee_id')) {
            $attributes['assignee_id'] = $shared ? $this->validated('assignee_id') : null;
        }

        if ($this->has('status')) {
            $attributes['status'] = $this->validated('status');
        }

        return $attributes;
    }
}
