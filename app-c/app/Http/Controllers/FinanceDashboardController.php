<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PDOException;

class FinanceDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['SUCCESS', 'FAILED'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $timezone = config('app.timezone');
        $state = null;
        $unavailable = false;
        $summary = ['count' => 0, 'success' => 0, 'amount' => 'Rp 0,00'];
        $transactions = new LengthAwarePaginator([], 0, 25, $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->query()]);

        try {
            $connection = DB::connection(config('finance_sync.target_connection'));
            [$state, $summary, $transactions] = $connection->transaction(function () use ($connection, $filters, $timezone): array {
                if ($connection->getDriverName() === 'pgsql') {
                    $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }
                $state = $connection->table('mirror_b_sync_state')->where('id', 1)->first();
                $query = $connection->table('mirror_b_transaksi');
                if (! empty($filters['q'])) {
                    $query->whereRaw('LOWER(kode_transaksi) LIKE ?', ['%'.mb_strtolower(trim($filters['q'])).'%']);
                }
                if (! empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }
                if (! empty($filters['from'])) {
                    $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc()->format('Y-m-d H:i:sP'));
                }
                if (! empty($filters['to'])) {
                    $query->where('created_at', '<', CarbonImmutable::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc()->format('Y-m-d H:i:sP'));
                }
                $summary = [
                    'count' => (clone $query)->count(),
                    'success' => (clone $query)->where('status', 'SUCCESS')->count(),
                    'amount' => $this->rupiah((string) (clone $query)->where('status', 'SUCCESS')->sum('total_nominal')),
                ];
                $transactions = $query->orderByDesc('created_at')->orderByDesc('source_id')->paginate(25)->withQueryString();
                $transactions->through(function (object $row) use ($timezone): object {
                    $row->nominal_display = $this->rupiah((string) $row->total_nominal);
                    $row->date_display = CarbonImmutable::parse($row->created_at, 'UTC')->setTimezone($timezone)->format('d M Y · H:i');

                    return $row;
                });

                return [$state, $summary, $transactions];
            });
        } catch (QueryException|PDOException $exception) {
            $unavailable = true;
        }

        $lastSync = $state ? CarbonImmutable::parse($state->synced_at, 'UTC')->setTimezone($timezone) : null;
        $stale = $lastSync && $lastSync->lessThan(now()->subHours(2));
        $dataAccess = $request->session()->get('finance_data_access');
        $accessCheckedAt = $dataAccess ? CarbonImmutable::parse($dataAccess['checked_at'])->setTimezone($timezone)->format('d M Y, H:i') : null;

        return view('finance.dashboard', compact('filters', 'state', 'unavailable', 'summary', 'transactions', 'lastSync', 'stale', 'timezone', 'dataAccess', 'accessCheckedAt'));
    }

    private function rupiah(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');

        return 'Rp '.($negative ? '-' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
