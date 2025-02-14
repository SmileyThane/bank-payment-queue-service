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
    private $paymentComment;

    private string $virtualAccountId;

    /**
     * Create a new job instance.
     */
    public function __construct($clients, $virtualAccountId, $paymentComment = null)
    {
        $this->clients = $clients;
        $this->paymentComment = $paymentComment;
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
