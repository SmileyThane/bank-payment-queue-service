<?php

namespace App\Jobs;

use App\Http\Controllers\PaymentController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreatePaymentProcessJob implements ShouldQueue
{
    use Queueable;

    private $clients;
    private string $paymentComment;

    private string $virtualAccountId;

    /**
     * Create a new job instance.
     */
    public function __construct($clients, $virtualAccountId, string $paymentComment = null)
    {
        $this->clients = $clients;
        $this->paymentComment = $paymentComment ?? null;
        $this->virtualAccountId = $virtualAccountId;
    }

    /**
     * Execute the job.
     */
    final public function handle(): void
    {
        (new PaymentController())->initPaymentProcess($this->clients, $this->virtualAccountId, $this->paymentComment);
    }
}
