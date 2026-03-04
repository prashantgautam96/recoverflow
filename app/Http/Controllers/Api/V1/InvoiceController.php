<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarkInvoicePaidRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\ApiKey;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Services\InvoiceReminderScheduler;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceReminderScheduler $scheduler) {}

    public function index(Request $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        $query = Invoice::query()
            ->where('api_key_id', $apiKey->id)
            ->with('client')
            ->withCount([
                'reminders as pending_reminders_count' => fn ($builder) => $builder->where('status', InvoiceReminder::StatusPending),
            ])
            ->latest('due_at');

        $status = $request->string('status')->toString();

        if (in_array($status, [Invoice::StatusPending, Invoice::StatusPaid, Invoice::StatusOverdue], true)) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);
        $validated = $request->validated();

        $client = $apiKey->clients()->whereKey($validated['client_id'])->first();

        if ($client === null) {
            return response()->json([
                'message' => 'Client does not belong to this API key.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dueAt = Carbon::parse($validated['due_at']);
        $invoiceStatus = $dueAt->isPast() ? Invoice::StatusOverdue : Invoice::StatusPending;

        try {
            $invoice = $apiKey->invoices()->create([
                'client_id' => $client->id,
                'invoice_number' => $validated['invoice_number'],
                'currency' => strtoupper($validated['currency']),
                'amount_cents' => (int) round((float) $validated['amount'] * 100),
                'issued_at' => $validated['issued_at'],
                'due_at' => $validated['due_at'],
                'status' => $invoiceStatus,
                'payment_url' => $validated['payment_url'] ?? null,
                'webhook_secret' => Str::random(40),
                'late_fee_percent' => $validated['late_fee_percent'] ?? 0,
            ]);
        } catch (QueryException) {
            return response()->json([
                'message' => 'Invoice number already exists for this API key.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->scheduler->scheduleForInvoice($invoice);

        $invoice->load('client', 'reminders');

        return response()->json($this->serializeInvoice($invoice), Response::HTTP_CREATED);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        abort_unless($invoice->api_key_id === $apiKey->id, Response::HTTP_NOT_FOUND);

        $invoice->load('client', 'reminders');

        return response()->json($this->serializeInvoice($invoice));
    }

    public function markPaid(MarkInvoicePaidRequest $request, Invoice $invoice): JsonResponse
    {
        $apiKey = $this->resolveApiKey($request);

        abort_unless($invoice->api_key_id === $apiKey->id, Response::HTTP_NOT_FOUND);

        $paidAt = $request->validated('paid_at');

        $invoice->forceFill([
            'status' => Invoice::StatusPaid,
            'paid_at' => $paidAt ? Carbon::parse($paidAt) : now(),
        ])->save();

        $invoice->reminders()
            ->where('status', InvoiceReminder::StatusPending)
            ->update([
                'status' => InvoiceReminder::StatusSkipped,
            ]);

        $invoice->load('client', 'reminders');

        return response()->json($this->serializeInvoice($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'amount' => $invoice->amount_cents / 100,
            'amount_cents' => $invoice->amount_cents,
            'currency' => $invoice->currency,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'due_at' => $invoice->due_at?->toDateString(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'payment_url' => $invoice->payment_url,
            'payment_webhook_url' => $invoice->webhook_secret
                ? url("/api/v1/webhooks/payment/{$invoice->webhook_secret}")
                : null,
            'late_fee_percent' => (float) $invoice->late_fee_percent,
            'client' => [
                'id' => $invoice->client?->id,
                'name' => $invoice->client?->name,
                'email' => $invoice->client?->email,
            ],
            'reminders' => $invoice->reminders
                ->map(fn (InvoiceReminder $reminder): array => [
                    'sequence' => $reminder->sequence,
                    'status' => $reminder->status,
                    'scheduled_for' => $reminder->scheduled_for?->toIso8601String(),
                    'sent_at' => $reminder->sent_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
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
