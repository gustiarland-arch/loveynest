<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\Poll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Decide together: where to eat, what to watch. */
class PollController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $polls = Poll::where('couple_space_id', $user->activeSpaceId())->with(['options', 'votes', 'creator:id,name'])
            ->orderByRaw('closed_at IS NOT NULL')->latest('id')->limit(50)->get()
            ->map(function (Poll $p) use ($user) {
                $mine = $p->votes->firstWhere('user_id', $user->id)?->poll_option_id;

                return [
                    'id' => $p->id,
                    'question' => $p->question,
                    'closed' => $p->isClosed(),
                    'mine' => $p->created_by === $user->id,
                    'voted' => $mine,
                    'total' => $p->votes->count(),
                    'options' => $p->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'votes' => $p->votes->where('poll_option_id', $o->id)->count()])->values(),
                ];
            });

        return Inertia::render('Berdua/Polls', ['polls' => $polls]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'question' => trim((string) $request->input('question')),
            'options' => collect((array) $request->input('options'))->map(fn ($o) => trim((string) $o))->filter()->values()->all(),
        ]);
        $data = $request->validate([
            'question' => ['required', ...E2E::text(120)],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => [...E2E::text(80), 'distinct'],
        ], ['options.min' => 'Isi minimal dua pilihan.', 'options.required' => 'Isi minimal dua pilihan.', 'options.*.distinct' => 'Pilihan tidak boleh sama.'], ['question' => 'pertanyaan']);

        DB::transaction(function () use ($data, $request) {
            $poll = new Poll(['question' => $data['question']]);
            $poll->couple_space_id = $request->user()->activeSpaceId();
            $poll->created_by = $request->user()->id;
            $poll->save();
            $poll->options()->createMany(array_map(fn ($l) => ['label' => $l], $data['options']));
        });

        return back()->with('success', 'Voting dibuat.');
    }

    public function vote(Request $request, int $poll): RedirectResponse
    {
        $row = $this->find($request, $poll);
        abort_if($row->isClosed(), 422, 'Voting ini sudah ditutup.');
        $data = $request->validate(['option' => ['required', 'integer']]);
        abort_unless($row->options()->whereKey($data['option'])->exists(), 404);

        $row->votes()->updateOrCreate(['user_id' => $request->user()->id], ['poll_option_id' => $data['option']]);

        return back();
    }

    public function close(Request $request, int $poll): RedirectResponse
    {
        $row = $this->find($request, $poll);
        $row->forceFill(['closed_at' => $row->isClosed() ? null : now()])->save();

        return back();
    }

    public function destroy(Request $request, int $poll): RedirectResponse
    {
        abort_unless($this->find($request, $poll)->created_by === $request->user()->id, 404);
        $this->find($request, $poll)->delete();

        return back()->with('success', 'Voting dihapus.');
    }

    private function find(Request $request, int $id): Poll
    {
        return Poll::where('couple_space_id', $request->user()->activeSpaceId())->findOrFail($id);
    }
}
