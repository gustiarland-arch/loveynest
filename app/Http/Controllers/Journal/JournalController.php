<?php

namespace App\Http\Controllers\Journal;

use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\JournalRequest;
use App\Models\JournalEntry;
use App\Services\Journal\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    private const LIMIT = 100;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $hasCouple = (bool) $user->activeSpaceId();
        $tab = in_array($request->query('tab'), ['mine', 'ours', 'letters'], true) ? $request->query('tab') : 'mine';
        if ($tab !== 'mine' && ! $hasCouple) {
            $tab = 'mine';
        }

        $query = JournalEntry::query()->with('creator:id,name')
            ->inSpaceOf($user, $tab === 'mine' ? Scope::Personal : Scope::Shared)
            ->where('kind', $tab === 'letters' ? 'letter' : 'journal')
            ->orderByDesc('entry_date')->orderByDesc('id')->limit(self::LIMIT);

        return Inertia::render('Journal/Index', [
            'tab' => $tab,
            'hasCouple' => $hasCouple,
            'hasPartner' => (bool) $user->partner(),
            'entries' => $query->get()->map(fn (JournalEntry $e) => [
                'id' => $e->id,
                'kind' => $e->kind,
                'space' => $e->scope->space(),
                'title' => $e->title,
                'body' => $e->body,
                'entry_date' => $e->entry_date->toDateString(),
                'mine' => $e->created_by === $user->id,
                'author' => $e->isShared() ? $e->creator?->firstName() : null,
                'unread' => $e->isLetter() && $e->created_by !== $user->id && $e->read_at === null,
                'seen' => $e->isLetter() && $e->created_by === $user->id && $e->read_at !== null,
            ]),
        ]);
    }

    public function store(JournalRequest $request, JournalService $journal): RedirectResponse
    {
        $entry = $journal->create($request->user(), $request->scope(), $request->validated('kind'), $request->entryAttributes());

        return back()->with('success', $entry->isLetter() ? 'Suratmu terkirim.' : 'Tulisan tersimpan.');
    }

    public function update(JournalRequest $request, JournalEntry $entry, JournalService $journal): RedirectResponse
    {
        $journal->update($entry, $request->entryAttributes());

        return back()->with('success', 'Tulisan diperbarui.');
    }

    public function read(Request $request, JournalEntry $entry, JournalService $journal): JsonResponse
    {
        $this->authorize('view', $entry);
        $journal->markRead($entry, $request->user());

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);
        $entry->delete();

        return back()->with('success', 'Tulisan dihapus.');
    }
}
