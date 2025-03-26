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

    private $userId;

    private string $virtualAccountId;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $upload, $clients, $virtualAccountId, $paymentComment = null)
    {
        $this->userId = $userId;
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
        (new PaymentController())->initPayment($this->userId, $this->upload, $this->clients, $this->virtualAccountId, $this->paymentComment);
    }
}
