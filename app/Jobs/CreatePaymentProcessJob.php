<?php

namespace App\Jobs;

use App\Http\Controllers\PaymentController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreatePaymentProcessJob implements ShouldQueue
{
    use Queueable;

    private $clients;
    private string $payment_comment;

    /**
     * Create a new job instance.
     */
    public function __construct($clients, string $payment_comment)
    {
        $this->clients = $clients;
        $this->payment_comment = $payment_comment;
    }

    /**
     * Execute the job.
     */
    final public function handle(): void
    {
        (new PaymentController())->initPaymentProcess($this->clients, $this->payment_comment);
    }
}
