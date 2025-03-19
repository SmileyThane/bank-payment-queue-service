<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;

class ClientController extends Controller
{
    /**
     * @throws JsonException
     */
    final public function showByHash(Request $request, string $hash): View
    {
        $upload = Upload::query()->where('hash', $hash)->firstOrFail();
        $id = $upload->id;
        $outstandingAmount = $upload->outstanding_amount;
        $isExecuted = $upload->is_executed;
        $isDealsCreated = $upload->is_deals_created;
        $isProcessed = $upload->is_processed;
        $clientsQuery = Client::query()->where('upload_id', $id);
        if ($search = $request->search) {
            $clientsQuery->where(static function ($query) use ($search) {
                $query->orWhere('name', 'like', '%' . $search . '%');
                $query->orWhere('surname', 'like', '%' . $search . '%');
                $query->orWhere('patronymic', 'like', '%' . $search . '%');
                $query->orWhere('card_number', 'like', '%' . $search . '%');
                $query->orWhere('amount', 'like', '%' . $search . '%');
            });
        }
        $clients = $clientsQuery->get();
        $processedClientsCount = Client::query()->where('upload_id', $id)->where('status', '=', Client::STATUSES[5])->count();

        $balance = (new PaymentController())->getVirtualActualBalance($upload->beneficiary_id, $upload->virtual_account_id);
        $beneficiary = (new PaymentController())->getBeneficiary($upload->beneficiary_id);

        return view('clients', compact(
            'clients',
            'balance',
            'hash',
            'outstandingAmount',
            'isExecuted',
            'upload',
            'beneficiary',
            'isProcessed',
            'isDealsCreated',
            'search',
            'processedClientsCount',
        ));
    }
}
