<?php

namespace App\Services;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InvoiceReminderProcessor
{
    /**
     * @return array{processed:int, sent:int, skipped:int, failed:int}
     */
    public function process(int $limit = 100): array
    {
        $counters = [
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if (! $this->isTransactionalMailerConfigured()) {
            return $counters;
        }

        $reminders = InvoiceReminder::query()
            ->where('status', InvoiceReminder::StatusPending)
            ->where('attempts', '<', InvoiceReminder::MaxAttempts)
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

            $recipientEmail = trim((string) ($invoice->client?->email ?? ''));

            if ($recipientEmail === '' || $reminder->channel !== 'email') {
                $reminder->forceFill([
                    'status' => InvoiceReminder::StatusSkipped,
                ])->save();

                $counters['skipped']++;

                continue;
            }

            try {
                Mail::to($recipientEmail)->send(new InvoiceReminderMail($reminder));

                $reminder->forceFill([
                    'status' => InvoiceReminder::StatusSent,
                    'sent_at' => now(),
                ])->save();

                $invoice->forceFill([
                    'last_reminder_sent_at' => now(),
                ])->save();
            } catch (Throwable $throwable) {
                report($throwable);

                // Atomic increment so concurrent runs can't race past the cap.
                InvoiceReminder::where('id', $reminder->id)->increment('attempts');
                $reminder->refresh();

                if ($reminder->attempts >= InvoiceReminder::MaxAttempts) {
                    $reminder->forceFill(['status' => InvoiceReminder::StatusFailed])->save();
                }

                $counters['failed']++;

                continue;
            }

            $counters['sent']++;
        }

        return $counters;
    }

    private function isTransactionalMailerConfigured(): bool
    {
        if (App::environment('testing')) {
            return true;
        }

        $mailer = (string) config('mail.default');

        return ! in_array($mailer, ['log', 'array'], true);
    }
}
