<?php

namespace App\Http\Controllers;

use App\Jobs\CreatePaymentProcessJob;
use App\Models\Client;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;

class PaymentController extends Controller
{
    /**
     * @throws JsonException
     */
    final public function authorize(): void
    {


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/sso/connect/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'client_id=' . env('BANK_CLIENT') . '&client_secret=' . env('BANK_SECRET') . '&scope=' . env('BANK_SCOPE') . '&grant_type=' . env('BANK_GRANT_TYPE'),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($response, true);

        if ($result && $result['access_token']) {
            Cache::add('access_token', $result['access_token'], 60);
        }
    }

    /**
     * @throws JsonException
     */
    final public function getActualBalance(): float
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/balance',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token')
            ),
        ));

        $response = curl_exec($curl);

        $result = json_decode($response, true);

        if ($result && $result['value'] && $result['value']['endBalance']) {
            return $result['value']['endBalance'];
        }

        return 0;
    }

    /**
     * @throws JsonException
     */
    final public function initPayment(Request $request, string $hash): RedirectResponse
    {
        $upload = Upload::query()->where('hash', $hash)->firstOrFail();
        if ($upload) {
            $clients  = Client::query()->where('upload_id', $upload->id)->get();
            CreatePaymentProcessJob::dispatch($clients, $request->payment_comment);
            $upload->is_executed = 1;
            $upload->save();
        }

        return redirect()->back();
    }

    public function initPaymentProcess($clients, string $payment_comment):void
    {
        foreach ($clients as $client) {
            $purpose = ( $payment_comment ?? 'Отправка на карту. ' ) . $client->name . ' ' . $client->surname;
            $dealId = $this->createPayment($client->id, $client->card_number, $client->amount, $purpose);
            if ($dealId) {
                $client->deal_id = $dealId;
                $client->status = Client::STATUSES[1];
                $client->save();

                $this->processPayment($client->id, $dealId);
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function createPayment(int $id, string $cardNumber, float $amount, string $purpose = ''):string|null
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/deals',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                                        "id": "' . Str::uuid() . '",
                                        "amount": ' . $amount . ',
                                        "payers": [
                                            {
                                                "virtualAccountNumber": "6bd5adea-d1e1-4938-9ebe-d4962d50c160",
                                                "amount": ' . $amount . '
                                            }
                                        ],
                                        "recipients": [
                                            {
                                                "number": 1,
                                                "type": "PaymentToCard",
                                                "amount": ' . $amount . ',
                                                "cardNumber": "' . $cardNumber . '",
                                                "purpose": "' . $purpose. '",
                                                "PaymentReceiverType": "SelfEmployed"
                                            }
                                        ]
                                    }',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . Cache::get('access_token')
            ],
        ]);

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && $result['value']['dealId']) {
            return $result['value']['dealId'];
        }

        if ($result && $result['isSuccess'] === false) {
            $this->setPaymentError($id, Client::STATUSES[2], $result['error']['message']);
        } else {
            $this->setPaymentError($id, Client::STATUSES[2], null, $response);
        }

        return null;
    }

    private function processPayment(int $clientId, string $dealId): void
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/deals/' . $dealId . '/execute',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token')
            ),
        ));

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true) {
            Client::query()->where('id', $clientId)->update([
                'status' => Client::STATUSES[3],
            ]);
            return;
        }

        if ($result && $result['isSuccess'] === false) {
            $this->setPaymentError($clientId, Client::STATUSES[6], $result['error']['message']);
        } else {
            $this->setPaymentError($clientId, Client::STATUSES[6], null, $response);
        }
    }

    private function setPaymentError(int $clientId, string $status, string $description = null, string $trace = null): void
    {
        $client = Client::query()->find($clientId);
        if ($client) {
            $client->status = $status;
            if ($description) {
                $client->status_description = $description;
            } else {
                $client->status_description = Client::PROBLEM_DEFAULT_MESSAGES[$status];
                Log::error($trace);
            }
            $client->save();
        }
    }

    /**
     * @throws JsonException
     */
    public function checkBulkPaymentStatuses():void
    {
        $clients = Client::query()
            ->where('status', Client::STATUSES[3])
            ->orWhere('status', Client::STATUSES[4])
            ->get();

        foreach ($clients as $client) {
            $deal = $this->getDeal($client->deal_id);
            if ($deal) {
                $client->status_description = $deal;
                if ($deal['status'] === 'InProcess') {
                    $client->status = Client::STATUSES[4];
                }

                if ($deal['status'] === 'Closed') {
                    $client->status = Client::STATUSES[5];

                }

                if ($deal['status'] === 'Rejected' || $deal['status'] === 'Сorrection') {
                    $client->status = Client::STATUSES[6];
                }
            } else {
//                $client->status = Client::STATUSES[7];
            }

            $client->save();
        }
    }

    public function getDeal(string $dealId):array|null
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/deals/' . $dealId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token')
            ),
        ));

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && $result['value']) {
            return $result['value'];
        }

        return null;
    }
}
