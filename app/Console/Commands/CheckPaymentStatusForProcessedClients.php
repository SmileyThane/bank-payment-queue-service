<?php

namespace App\Console\Commands;

use App\Http\Controllers\PaymentController;
use Illuminate\Console\Command;
use JsonException;

class CheckPaymentStatusForProcessedClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-payment-status-for-processed-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'checking payment status for clients with status \'payment_executed\' or \'payment_in_process\'';

    /**
     * Execute the console command.
     * @throws JsonException
     */
    public function handle()
    {
        $this->info('Started');
        (new PaymentController())->checkBulkPaymentStatuses();
        $this->info('Done');

        return 0;
    }
}
