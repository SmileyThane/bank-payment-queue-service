<?php

namespace App\Jobs;

use App\Http\Controllers\PaymentController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreatePaymentInitiationJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 7200;

    private $clients;
    private $paymentComment;
    private $upload;

    private string $virtualAccountId;

    /**
     * Create a new job instance.
     */
    public function __construct($upload, $clients, $virtualAccountId, $paymentComment = null)
    {
        $this->upload = $upload;
        $this->clients = $clients;
        $this->paymentComment = $paymentComment;
        $this->virtualAccountId = $virtualAccountId;
    }

    /**
     * Execute the job.
     */
    final public function handle(): void
    {
        (new PaymentController())->initPayment($this->upload, $this->clients, $this->virtualAccountId, $this->paymentComment);
    }
}
