<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FillBankDataToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fill-bank-data-to-user {--email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = User::query()->where('email', $this->option('email'))->first();
        if (!$user) {
            $this->warn('USER NOT FOUND!');
            return;
        }
        $clientID = $this->ask('Enter Client ID?');
        $clientSecret = $this->ask('Enter Client Secret?');
        $bankAccount = $this->ask('Enter Client Bank Account?');

        $user->bank_data = base64_encode(json_encode([
            'BANK_CLIENT' => $clientID,
            'BANK_SECRET' => $clientSecret,
            'BANK_ACCOUNT_NUMBER' => $bankAccount,
        ]));

        $user->save();
        $this->info('USER ID: ' . $user->id . ' UPDATED');
    }
}
