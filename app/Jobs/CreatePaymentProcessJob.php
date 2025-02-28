<?php

namespace App\Jobs;

use App\Http\Controllers\PaymentController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreatePaymentProcessJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 7200;

    private $clients;

    /**
     * Create a new job instance.
     */
    public function __construct($clients)
    {
        $this->clients = $clients;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->clients as $client) {
            (new PaymentController())->processPayment($client->id, $client->deal_id);
        }
    }
}
