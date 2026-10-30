<?php

namespace App\Http\Requests\Journal;

use App\Support\E2E;
use App\Http\Requests\Concerns\ChoosesSpace;
use App\Models\JournalEntry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class JournalRequest extends FormRequest
{
    use ChoosesSpace;

    public function authorize(): bool
    {
        if ($item = $this->route('entry')) {
            Gate::authorize('update', $item);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'body' => trim((string) $this->input('body')),
            // Letters always go to the partner.
            'space' => $this->input('kind') === 'letter' ? 'ours' : $this->input('space'),
        ]);
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'space' => $creating ? $this->spaceRules() : ['prohibited'],
            'kind' => $creating ? ['required', Rule::in(JournalEntry::KINDS), function (string $a, mixed $v, Closure $fail) {
                if ($v === 'letter' && ! $this->user()->partner()) {
                    $fail('Surat hanya bisa dikirim setelah pasanganmu bergabung.');
                }
            }] : ['prohibited'],
            'title' => ['required', ...E2E::text(120)],
            'body' => ['required', ...E2E::text(10000)],
            'entry_date' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'judul', 'body' => 'isi', 'entry_date' => 'tanggal', 'space' => 'ruang'];
    }

    /** @return array{title: string, body: string, entry_date: string} */
    public function entryAttributes(): array
    {
        return $this->safe()->only(['title', 'body', 'entry_date']);
    }
}
