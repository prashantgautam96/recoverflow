<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminder;

class InvoiceReminderScheduler
{
    public function scheduleForInvoice(Invoice $invoice): void
    {
        if ($invoice->status === Invoice::StatusPaid) {
            return;
        }

        $offsetDays = [0, 3, 7, 14];

        foreach ($offsetDays as $index => $days) {
            $sequence = $index + 1;
            $scheduledFor = $invoice->due_at->copy()->startOfDay()->addDays($days)->setTime(9, 0);

            $invoice->reminders()->updateOrCreate(
                [
                    'sequence' => $sequence,
                ],
                [
                    'api_key_id' => $invoice->api_key_id,
                    'scheduled_for' => $scheduledFor,
                    'status' => InvoiceReminder::StatusPending,
                    'channel' => 'email',
                    'subject' => $this->buildSubject($invoice, $sequence),
                    'body' => $this->buildBody($invoice, $days),
                    'sent_at' => null,
                ]
            );
        }
    }

    private function buildSubject(Invoice $invoice, int $sequence): string
    {
        return "Payment reminder #{$sequence} for {$invoice->invoice_number}";
    }

    private function buildBody(Invoice $invoice, int $daysLate): string
    {
        $lateDays = max(0, $daysLate);
        $paymentInstruction = $invoice->payment_url
            ? "Please complete payment at {$invoice->payment_url}."
            : 'Please reply with your payment date.';

        return "Invoice {$invoice->invoice_number} is now {$lateDays} days past due. {$paymentInstruction}";
    }
}
