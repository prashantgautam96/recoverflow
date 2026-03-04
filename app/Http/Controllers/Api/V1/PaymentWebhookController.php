<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $invoice = Invoice::where('webhook_secret', $token)->first();

        if ($invoice === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($invoice->status === Invoice::StatusPaid) {
            return response()->json(['received' => true]);
        }

        $paidAt = $request->input('paid_at');

        $invoice->forceFill([
            'status' => Invoice::StatusPaid,
            'paid_at' => $paidAt ? Carbon::parse($paidAt) : now(),
        ])->save();

        $invoice->reminders()
            ->where('status', InvoiceReminder::StatusPending)
            ->update(['status' => InvoiceReminder::StatusSkipped]);

        return response()->json(['received' => true]);
    }
}
