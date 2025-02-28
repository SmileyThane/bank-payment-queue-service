<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Upload;
use Illuminate\View\View;
use JsonException;

class ClientController extends Controller
{
    /**
     * @throws JsonException
     */
    final public function showByHash(string $hash): View
    {
        $upload = Upload::query()->where('hash', $hash)->firstOrFail();
        $id = $upload->id;
        $outstandingAmount = $upload->outstanding_amount;
        $isExecuted = $upload->is_executed;
        $isDealsCreated = $upload->is_deals_created;
        $isProcessed = $upload->is_processed;
        $clients = Client::query()->where('upload_id', $id)->get();
        $balance = (new PaymentController())->getVirtualActualBalance($upload->beneficiary_id, $upload->virtual_account_id);
        $beneficiary = (new PaymentController())->getBeneficiary($upload->beneficiary_id);

        return view('clients', compact('clients', 'balance', 'hash', 'outstandingAmount', 'isExecuted', 'upload', 'beneficiary', 'isProcessed', 'isDealsCreated'));
    }
}
