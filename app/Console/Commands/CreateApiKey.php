<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Console\Command;

class CreateApiKey extends Command
{
    protected $signature = 'recoverflow:create-api-key
                            {name : Display name for this key}
                            {--plan=starter : Plan tag attached to this key}
                            {--quota=5000 : Monthly request quota}
                            {--owner-email= : Owner email for billing/contact}
                            {--user-id= : Optional owning user id}';

    protected $description = 'Create an API key for RecoverFlow invoice recovery endpoints';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        $user = null;

        if ($userId !== null && $userId !== '') {
            $user = User::query()->find((int) $userId);

            if ($user === null) {
                $this->components->error('The specified user id does not exist.');

                return self::FAILURE;
            }
        }

        $plainTextKey = ApiKey::generatePlainTextKey();

        $apiKey = ApiKey::query()->create([
            'user_id' => $user?->id,
            'name' => (string) $this->argument('name'),
            'owner_email' => $this->option('owner-email') ?: $user?->email,
            'plan' => (string) $this->option('plan'),
            'monthly_quota' => max(1, (int) $this->option('quota')),
            'used_this_month' => 0,
            'key_hash' => ApiKey::hashPlainTextKey($plainTextKey),
            'active' => true,
        ]);

        $this->components->info('API key created successfully. Save this now; it cannot be viewed again.');
        $this->line("Key ID: {$apiKey->id}");
        $this->line("Plan: {$apiKey->plan}");
        $this->line("Monthly quota: {$apiKey->monthly_quota}");
        $this->line('Owner User ID: '.($apiKey->user_id ?? 'none'));
        $this->newLine();
        $this->line($plainTextKey);

        return self::SUCCESS;
    }
}
