<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\CoupleSpace;
use App\Models\Dream;
use App\Services\Albums\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Dream board: shared wishes with a status, a rough progress and an optional picture. */
class DreamController extends Controller
{
    public function index(Request $request): Response
    {
        $space = $this->space($request);

        return Inertia::render('Berdua/Dreams', [
            'dreams' => Dream::where('couple_space_id', $space->id)
                ->orderByRaw("FIELD(status, 'active', 'planned', 'wish', 'done', 'cancelled')")->orderByDesc('id')->limit(200)->get()
                ->map(fn (Dream $d) => [
                    'id' => $d->id, 'title' => $d->title, 'note' => $d->note, 'category' => $d->category, 'status' => $d->status,
                    'priority' => $d->priority, 'target_date' => $d->target_date?->toDateString(), 'progress' => $d->progress,
                    'image' => $d->image_path ? "/impian/{$d->id}/foto" : null,
                ])->values(),
        ]);
    }

    public function store(Request $request, PhotoService $photos): RedirectResponse
    {
        $space = $this->space($request);
        $data = $this->rules($request, true);
        $dream = new Dream(collect($data)->except(['image', 'image_thumb', 'image_width', 'image_height'])->all());
        $dream->couple_space_id = $space->id;
        $dream->created_by = $request->user()->id;
        $this->touchStatus($dream);
        if ($request->hasFile('image')) {
            try {
                $this->attach($dream, $photos, $request);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['image' => $e->getMessage()]);
            }
        }
        $dream->save();

        return back()->with('success', 'Impian ditambahkan.');
    }

    public function update(Request $request, int $dream, PhotoService $photos): RedirectResponse
    {
        $row = Dream::where('couple_space_id', $this->space($request)->id)->findOrFail($dream);
        $data = $this->rules($request, false);
        $row->fill(collect($data)->except(['image', 'image_thumb', 'image_width', 'image_height', 'remove_image'])->all());
        $this->touchStatus($row);
        if ($request->boolean('remove_image')) {
            $this->discard($row);
        }
        if ($request->hasFile('image')) {
            try {
                $stored = $this->storeImage($photos, $request);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['image' => $e->getMessage()]);
            }
            $this->discard($row);
            $row->image_path = $stored['path'];
            $row->image_thumb_path = $stored['thumb_path'];
        }
        $row->save();

        return back();
    }

    public function destroy(Request $request, int $dream): RedirectResponse
    {
        $row = Dream::where('couple_space_id', $this->space($request)->id)->findOrFail($dream);
        $this->discard($row);
        $row->delete();

        return back()->with('success', 'Impian dihapus.');
    }

    public function image(Request $request, int $dream): HttpResponse
    {
        $row = Dream::where('couple_space_id', $this->space($request)->id)->whereNotNull('image_path')->findOrFail($dream);
        abort_unless(Storage::disk('local')->exists($row->image_path), 404);

        return Storage::disk('local')->response($row->image_path, null, E2E::fileHeaders($row->image_path, 'image/jpeg', 'private, max-age=86400'));
    }

    private function rules(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$req, ...E2E::text(120)],
            'note' => ['nullable', ...E2E::text(1000)],
            'category' => ['sometimes', Rule::in(Dream::CATEGORIES)],
            'status' => ['sometimes', Rule::in(Dream::STATUSES)],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high'])],
            'target_date' => ['nullable', 'date'],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            ...(E2E::enabled() ? PhotoService::sealedRules('image', false) : ['image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:25600']]),
            'remove_image' => ['sometimes', 'boolean'],
        ]);
    }

    private function touchStatus(Dream $dream): void
    {
        if ($dream->status === 'done') {
            $dream->progress = 100;
            $dream->done_at ??= now();
        } else {
            $dream->done_at = null;
        }
    }

    /** @return array{path: string, thumb_path: string, width: int, height: int} */
    private function storeImage(PhotoService $photos, Request $request): array
    {
        return E2E::enabled()
            ? $photos->storeSealed($request->file('image'), $request->file('image_thumb'), $request->integer('image_width'), $request->integer('image_height'), 'dreams')
            : $photos->store($request->file('image'), 'dreams');
    }

    private function attach(Dream $dream, PhotoService $photos, Request $request): void
    {
        $stored = $this->storeImage($photos, $request);
        $dream->image_path = $stored['path'];
        $dream->image_thumb_path = $stored['thumb_path'];
    }

    private function discard(Dream $dream): void
    {
        Storage::disk('local')->delete(array_filter([$dream->image_path, $dream->image_thumb_path]));
        $dream->image_path = $dream->image_thumb_path = null;
    }

    private function space(Request $request): CoupleSpace
    {
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);

        return $space;
    }
}
