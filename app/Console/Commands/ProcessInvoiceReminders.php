<?php

namespace App\Console\Commands;

use App\Services\InvoiceReminderProcessor;
use Illuminate\Console\Command;

class ProcessInvoiceReminders extends Command
{
    protected $signature = 'recoverflow:process-reminders {--limit=100 : Max reminders to process per run}';

    protected $description = 'Process due invoice reminders and update invoice recovery status';

    public function __construct(private InvoiceReminderProcessor $processor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->processor->process(max(1, (int) $this->option('limit')));

        $this->line("Processed: {$result['processed']}");
        $this->line("Sent: {$result['sent']}");
        $this->line("Skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
