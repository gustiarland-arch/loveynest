<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\CoupleSpace;
use App\Models\DateExpense;
use App\Models\SavingsGoal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A light shared wallet for dates: what was spent, who paid, who owes whom,
 * a monthly budget and savings goals. It only records numbers typed in by the
 * two of you. There is no bank access and no payment.
 */
class BudgetController extends Controller
{
    private const MAX_AMOUNT = 1_000_000_000;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $this->space($request);
        $month = $this->month($request, $user->timezone);
        $category = in_array($request->query('category'), DateExpense::CATEGORIES, true) ? $request->query('category') : null;
        $partner = $user->partner();

        $inMonth = fn () => DateExpense::where('couple_space_id', $space->id)->whereNull('voided_at')
            ->whereBetween('spent_on', [$month->toDateString(), $month->endOfMonth()->toDateString()]);

        $expenses = $inMonth()->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('spent_on')->orderByDesc('id')->limit(200)->get();
        $byCategory = $inMonth()->selectRaw('category, SUM(amount) total')->groupBy('category')->pluck('total', 'category');
        $total = (int) $byCategory->sum();

        // Net of everything not yet settled: positive = the partner owes me.
        $open = DateExpense::where('couple_space_id', $space->id)->whereNull('voided_at')->whereNull('settled_at')->where('payer_share', '<', 100)->get();
        $balance = $open->sum(fn (DateExpense $e) => $e->paid_by === $user->id ? $e->owed() : ($e->paid_by ? -$e->owed() : 0));

        return Inertia::render('Berdua/Budget', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->locale('id')->translatedFormat('F Y'),
            'category' => $category,
            'budget' => $space->date_budget,
            'total' => $total,
            'byCategory' => $byCategory->map(fn ($v) => (int) $v)->all(),
            'balance' => (int) $balance,
            'unsettled' => $open->count(),
            'partner' => $partner ? ['id' => $partner->id, 'name' => $partner->firstName()] : null,
            'me' => ['id' => $user->id, 'name' => $user->firstName()],
            'expenses' => $expenses->map(fn (DateExpense $e) => [
                'id' => $e->id, 'title' => $e->title, 'note' => $e->note, 'amount' => $e->amount, 'payer_share' => $e->payer_share,
                'category' => $e->category, 'spent_on' => $e->spent_on->toDateString(), 'paid_by' => $e->paid_by,
                'settled' => $e->settled_at !== null, 'owed' => $e->owed(),
            ])->values(),
            'voided' => DateExpense::where('couple_space_id', $space->id)->whereNotNull('voided_at')->whereBetween('spent_on', [$month->toDateString(), $month->endOfMonth()->toDateString()])
                ->orderByDesc('id')->limit(20)->get(['id', 'title', 'amount'])->all(),
            'goals' => SavingsGoal::where('couple_space_id', $space->id)->orderBy('id')->limit(30)->get()->map(fn (SavingsGoal $g) => [
                'id' => $g->id, 'title' => $g->title, 'kind' => $g->kind, 'target' => $g->target_amount, 'saved' => $g->saved_amount, 'deadline' => $g->deadline?->toDateString(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $space = $this->space($request);
        $expense = new DateExpense(['title' => $data['title'], 'note' => $data['note'] ?? null, 'amount' => $data['amount'], 'payer_share' => $data['payer_share'], 'category' => $data['category'], 'spent_on' => $data['spent_on']]);
        $expense->couple_space_id = $space->id;
        $expense->paid_by = $data['paid_by'];
        $expense->save();

        return back()->with('success', 'Pengeluaran dicatat.');
    }

    public function update(Request $request, int $expense): RedirectResponse
    {
        $row = $this->find($request, $expense);
        $data = $this->validated($request);
        $row->fill(['title' => $data['title'], 'note' => $data['note'] ?? null, 'amount' => $data['amount'], 'payer_share' => $data['payer_share'], 'category' => $data['category'], 'spent_on' => $data['spent_on']]);
        $row->paid_by = $data['paid_by'];
        // A corrected amount or split has to be settled again.
        if ($row->isDirty(['amount', 'payer_share', 'paid_by'])) {
            $row->settled_at = null;
        }
        $row->save();

        return back()->with('success', 'Pengeluaran diperbarui.');
    }

    /** Cancelling keeps the row (for the history) but removes it from every total. */
    public function void(Request $request, int $expense): RedirectResponse
    {
        $row = $this->find($request, $expense);
        $row->forceFill(['voided_at' => $row->voided_at ? null : now()])->save();

        return back()->with('success', $row->voided_at ? 'Pengeluaran dibatalkan.' : 'Pengeluaran dipulihkan.');
    }

    public function settle(Request $request): RedirectResponse
    {
        $space = $this->space($request);
        DB::transaction(fn () => DateExpense::where('couple_space_id', $space->id)->whereNull('voided_at')->whereNull('settled_at')->update(['settled_at' => now()]));

        return back()->with('success', 'Semua tagihan ditandai lunas.');
    }

    public function budget(Request $request): RedirectResponse
    {
        $data = $request->validate(['budget' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT]]);
        $space = $this->space($request);
        $space->forceFill(['date_budget' => ($data['budget'] ?? 0) > 0 ? $data['budget'] : null])->save();

        return back()->with('success', 'Anggaran bulanan disimpan.');
    }

    public function storeGoal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', ...E2E::text(80)],
            'kind' => ['required', Rule::in(['tabungan', 'liburan', 'lainnya'])],
            'target_amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['title' => 'nama target', 'target_amount' => 'jumlah target']);
        $space = $this->space($request);
        abort_if(SavingsGoal::where('couple_space_id', $space->id)->count() >= 30, 422, 'Maksimal 30 target tabungan.');
        $goal = new SavingsGoal(['title' => trim($data['title']), 'kind' => $data['kind'], 'target_amount' => $data['target_amount'], 'deadline' => $data['deadline'] ?? null]);
        $goal->couple_space_id = $space->id;
        $goal->created_by = $request->user()->id;
        $goal->save();

        return back()->with('success', 'Target tabungan dibuat.');
    }

    /** Adds to (or, with a negative number, takes from) what has been saved. */
    public function saveToGoal(Request $request, int $goal): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT, 'not_in:0']]);
        $space = $this->space($request);
        DB::transaction(function () use ($space, $goal, $data) {
            $row = SavingsGoal::where('couple_space_id', $space->id)->whereKey($goal)->lockForUpdate()->firstOrFail();
            $row->saved_amount = max(0, $row->saved_amount + $data['amount']);
            $row->save();
        });

        return back();
    }

    public function destroyGoal(Request $request, int $goal): RedirectResponse
    {
        $space = $this->space($request);
        SavingsGoal::where('couple_space_id', $space->id)->whereKey($goal)->firstOrFail()->delete();

        return back()->with('success', 'Target dihapus.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $space = $this->space($request);
        $names = $space->members()->whereNull('left_at')->with('user:id,name')->get()->mapWithKeys(fn ($m) => [$m->user_id => $m->user?->firstName()])->all();
        $month = $request->query('month') ? $this->month($request, $user->timezone) : null;
        $rows = DateExpense::where('couple_space_id', $space->id)->whereNull('voided_at')
            ->when($month, fn ($q) => $q->whereBetween('spent_on', [$month->toDateString(), $month->endOfMonth()->toDateString()]))
            ->orderBy('spent_on')->orderBy('id')->limit(5000)->get();

        // Spreadsheet programs run cells that start with = + - @ as formulas.
        $safe = fn ($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;

        return response()->streamDownload(function () use ($rows, $names, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Tanggal', 'Judul', 'Kategori', 'Jumlah', 'Dibayar oleh', 'Bagian pembayar (%)', 'Ditagih ke pasangan', 'Lunas', 'Catatan']);
            foreach ($rows as $e) {
                fputcsv($out, [$e->spent_on->toDateString(), $safe($e->title), $e->category, $e->amount, $names[$e->paid_by] ?? '', $e->payer_share, $e->owed(), $e->settled_at ? 'ya' : 'belum', $safe($e->note ?? '')]);
            }
            fclose($out);
        }, 'anggaran-'.($month?->format('Y-m') ?? 'semua').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validated(Request $request): array
    {
        $space = $this->space($request);
        $request->merge(['title' => trim((string) $request->input('title')), 'note' => ($n = trim((string) $request->input('note'))) === '' ? null : $n]);
        $memberIds = $space->members()->whereNull('left_at')->pluck('user_id')->all();

        return $request->validate([
            'title' => ['required', ...E2E::text(80)],
            'note' => ['nullable', ...E2E::text(200)],
            'amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            'category' => ['required', Rule::in(DateExpense::CATEGORIES)],
            'spent_on' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'paid_by' => ['required', 'integer', Rule::in($memberIds)],
            // Share of the cost carried by the payer: 50 = split in half, 100 = paid for themselves.
            'payer_share' => ['required', 'integer', 'between:0,100'],
        ], [], ['title' => 'judul', 'amount' => 'jumlah', 'spent_on' => 'tanggal', 'paid_by' => 'pembayar']);
    }

    private function find(Request $request, int $id): DateExpense
    {
        return DateExpense::where('couple_space_id', $this->space($request)->id)->whereKey($id)->firstOrFail();
    }

    private function space(Request $request): CoupleSpace
    {
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);

        return $space;
    }

    private function month(Request $request, string $timezone): CarbonImmutable
    {
        $value = (string) $request->query('month');
        $parsed = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? CarbonImmutable::createFromFormat('!Y-m', $value, $timezone) : null;

        return ($parsed ?: CarbonImmutable::now($timezone))->startOfMonth();
    }
}
