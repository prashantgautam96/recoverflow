<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminder;

class InvoiceReminderProcessor
{
    /**
     * @return array{processed:int, sent:int, skipped:int}
     */
    public function process(int $limit = 100): array
    {
        $counters = [
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
        ];

        $reminders = InvoiceReminder::query()
            ->where('status', InvoiceReminder::StatusPending)
            ->where('scheduled_for', '<=', now())
            ->with('invoice.client')
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        foreach ($reminders as $reminder) {
            $counters['processed']++;

            $invoice = $reminder->invoice;

            if ($invoice === null || $invoice->status === Invoice::StatusPaid) {
                $reminder->forceFill([
                    'status' => InvoiceReminder::StatusSkipped,
                ])->save();

                $counters['skipped']++;

                continue;
            }

            if ($invoice->status === Invoice::StatusPending && $invoice->due_at->isPast()) {
                $invoice->forceFill([
                    'status' => Invoice::StatusOverdue,
                ])->save();
            }

            $reminder->forceFill([
                'status' => InvoiceReminder::StatusSent,
                'sent_at' => now(),
            ])->save();

            $invoice->forceFill([
                'last_reminder_sent_at' => now(),
            ])->save();

            $counters['sent']++;
        }

        return $counters;
    }
}
