<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        $outstandingCents = $apiKey->invoices()
            ->whereIn('status', [Invoice::StatusPending, Invoice::StatusOverdue])
            ->sum('amount_cents');

        $overdueCents = $apiKey->invoices()
            ->where('status', Invoice::StatusOverdue)
            ->sum('amount_cents');

        $recoveredThisMonthCents = $apiKey->invoices()
            ->where('status', Invoice::StatusPaid)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_cents');

        $remindersDueNow = $apiKey->invoiceReminders()
            ->where('status', InvoiceReminder::StatusPending)
            ->where('scheduled_for', '<=', now())
            ->count();

        return response()->json([
            'outstanding_cents' => $outstandingCents,
            'overdue_cents' => $overdueCents,
            'recovered_this_month_cents' => $recoveredThisMonthCents,
            'open_invoice_count' => $apiKey->invoices()->whereIn('status', [Invoice::StatusPending, Invoice::StatusOverdue])->count(),
            'paid_invoice_count' => $apiKey->invoices()->where('status', Invoice::StatusPaid)->count(),
            'reminders_due_now' => $remindersDueNow,
            'api_usage' => [
                'used_this_month' => $apiKey->currentUsageForMonth(),
                'monthly_quota' => $apiKey->monthly_quota,
            ],
        ]);
    }

    private function resolveApiKey(Request $request): ApiKey
    {
        $apiKey = $request->attributes->get('apiKey');

        if (! $apiKey instanceof ApiKey) {
            abort(Response::HTTP_UNAUTHORIZED, 'API key context is missing.');
        }

        return $apiKey;
    }
}
