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
        $clients = Client::query()->where('upload_id', $id)->get();
        $balance = (new PaymentController())->getActualBalance();

        return view('clients', compact('clients', 'balance', 'hash', 'outstandingAmount', 'isExecuted'));
    }
}
