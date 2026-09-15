<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Services\AlertService;
use App\Services\AuditLogService;
use App\Services\BranchService;
use App\Services\CustomerService;
use App\Services\LoanService;
use App\Services\TransactionService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lightweight grouped search used as a bridge while the client-side search
 * index is building: one request, grouped results, minimal payloads.
 * Role/branch scoping is delegated to the same services the list endpoints
 * use, so results always match what each role may see.
 */
class SearchController extends Controller
{
    private const PER_RESOURCE = 5;

    public function __construct(
        protected CustomerService $customerService,
        protected AccountService $accountService,
        protected TransactionService $transactionService,
        protected LoanService $loanService,
        protected AlertService $alertService,
        protected UserService $userService,
        protected BranchService $branchService,
        protected AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $user = $request->user();
        $filters = ['search' => $query, 'per_page' => self::PER_RESOURCE];

        $groups = [];

        $groups['customers'] = $this->safe(fn () => $this->customerService
            ->getPaginatedCustomers($filters, null, $user)
            ?->getCollection()
            ->map(fn ($c) => $this->row($c->id, $c->full_name, '#'.($c->customer_number ?? ''), $c->email))
            ->all());

        $groups['accounts'] = $this->safe(fn () => $this->accountService
            ->getPaginatedAccounts($filters, null, $user)
            ?->getCollection()
            ->map(fn ($a) => $this->row(
                $a->id,
                $a->account_number,
                trim(Str::headline((string) $a->account_type).' · '.($a->customer?->full_name ?? ''), ' ·'),
                number_format((float) $a->balance, 3).' '.$a->currency.' · '.Str::headline((string) $a->status),
            ))
            ->all());

        $groups['transactions'] = $this->safe(fn () => $this->transactionService
            ->getPaginatedTransactions($filters, null, $user)
            ?->getCollection()
            ->map(fn ($t) => $this->row(
                $t->id,
                $t->transaction_number,
                trim(Str::headline((string) $t->transaction_type).' · '.($t->account?->account_number ?? ''), ' ·'),
                number_format((float) $t->amount, 3).' · '.optional($t->transaction_date)?->format('M j, Y'),
            ))
            ->all());

        $groups['loans'] = $this->safe(fn () => $this->loanService
            ->getPaginatedLoans($filters, null, $user)
            ?->getCollection()
            ->map(fn ($l) => $this->row(
                $l->id,
                $l->loan_number,
                trim(Str::headline((string) $l->loan_type).' · '.($l->customer?->full_name ?? ''), ' ·'),
                number_format((float) $l->principal_amount, 3).' · '.Str::headline((string) $l->status),
            ))
            ->all());

        $groups['alerts'] = $this->safe(fn () => $this->alertService
            ->getPaginatedAlerts($filters, null, $user)
            ?->getCollection()
            ->map(fn ($a) => $this->row(
                $a->id,
                $a->alert_number ?? Str::headline((string) $a->alert_type),
                Str::limit((string) $a->description, 90),
                Str::headline((string) $a->severity).' · '.Str::headline((string) $a->status),
            ))
            ->all());

        if ($user && in_array($user->role, ['admin', 'manager', 'compliance', 'analyst', 'auditor'], true)) {
            $groups['users'] = $this->safe(fn () => $this->userService
                ->getPaginatedUsers($filters, null, $user)
                ?->getCollection()
                ->map(fn ($u) => $this->row(
                    $u->id,
                    $u->name,
                    $u->email,
                    Str::headline((string) $u->role).' · '.Str::headline((string) $u->status),
                ))
                ->all());
        }

        $groups['branches'] = $this->safe(fn () => $this->branchService
            ->getPaginatedBranches($filters, null, $user)
            ?->getCollection()
            ->map(fn ($b) => $this->row(
                $b->id,
                $b->branch_name,
                $b->branch_code ? '#'.$b->branch_code : null,
                trim(($b->city ?? '').' · '.Str::headline((string) $b->status), ' ·'),
            ))
            ->all());

        if ($user && in_array($user->role, ['admin', 'manager', 'compliance', 'auditor'], true)) {
            $groups['audit'] = $this->safe(fn () => $this->auditLogService
                ->getPaginatedLogs($filters, null, $user)
                ?->getCollection()
                ->map(fn ($log) => $this->row(
                    $log->id,
                    trim(Str::headline((string) $log->action).' · '.Str::headline((string) $log->table_name), ' ·'),
                    $log->user?->name ? 'by '.$log->user->name : null,
                    optional($log->created_at)?->format('M j, Y g:i A'),
                ))
                ->all());
        }

        // Drop kinds that failed or came back empty
        $data = array_filter($groups, fn ($rows) => is_array($rows) && count($rows) > 0);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * A single display row: only id + the three strings the UI renders.
     */
    private function row($id, string $title, ?string $subtitle = null, ?string $meta = null): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle !== '' ? $subtitle : null,
            'meta' => $meta !== '' ? $meta : null,
        ];
    }

    /**
     * A failing resource must never take down the whole response.
     */
    private function safe(callable $fetch): ?array
    {
        try {
            $rows = $fetch();

            return is_array($rows) ? $rows : null;
        } catch (Throwable) {
            return null;
        }
    }
}
