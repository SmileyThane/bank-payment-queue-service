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

    final public function getVirtualActualBalance(string $beneficiaryId, string $virtualAccountId): float
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/beneficiaries/' . $beneficiaryId . '/virtual-accounts/' . $virtualAccountId,
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

        if ($result && $result['isSuccess'] === true && isset($result['value']['virtualAccount'])) {
            return $result['value']['virtualAccount']['availableFunds'];
        }

        return 0;
    }

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
    final public function initPayment(Request $request, string $hash): RedirectResponse
    {
        $upload = Upload::query()->where('hash', $hash)->firstOrFail();
        if ($upload) {
            $clients = Client::query()->where('upload_id', $upload->id)->get();
            CreatePaymentProcessJob::dispatch($clients, $upload->virtual_account_id, $request->payment_comment);
            $upload->is_executed = 1;
            $upload->save();
        }

        return redirect()->back();
    }

    public function initPaymentProcess($clients, string $virtualAccountId, $paymentComment = null): void
    {
        foreach ($clients as $client) {
            $purpose = ($paymentComment ?? 'Отправка на карту. ') . $client->name . ' ' . $client->surname;
            $dealId = $this->createPayment($client->id, $virtualAccountId, $client->card_number, $client->amount, $purpose);
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
    private function createPayment(int $id, string $virtualAccountId, string $cardNumber, float $amount, string $purpose = ''): string|null
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
                                                "virtualAccountNumber": "' . $virtualAccountId . '",
                                                "amount": ' . $amount . '
                                            }
                                        ],
                                        "recipients": [
                                            {
                                                "number": 1,
                                                "type": "PaymentToCard",
                                                "amount": ' . $amount . ',
                                                "cardNumber": "' . $cardNumber . '",
                                                "purpose": "' . $purpose . '",
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

    private function processPayment(int $clientId, string $dealId): void
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

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

    /**
     * @throws JsonException
     */
    public function checkBulkPaymentStatuses(): void
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

                if ($deal['status'] === 'Rejected' || $deal['status'] === 'Correction') {
                    $client->status = Client::STATUSES[6];
                }
            } else {
//                $client->status = Client::STATUSES[7];
            }

            $client->save();
        }
    }

    public function getDeal(string $dealId): array|null
    {

        if (!Cache::has('access_token')) {
            $this->authorize();
        }

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

    public function getVirtualAccountsList()
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/virtual-accounts/search?perPage=100',
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

        if ($result && $result['isSuccess'] === true && isset($result['value']['virtualAccounts'])) {
            return $result['value']['virtualAccounts'];
        }

        return [];
    }

    public function createBeneficiary(Request $request)
    {

        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        if ($request->type === 'business') {
            $result = $this->createBeneficearyBusinessProcess($request);
        } else {
            $result = $this->createBeneficearyPersonalProcess($request);
        }

        dd($result);
//        TODO: add validation errors
//        if ($result && $result['isSuccess'] === true) {
//            return redirect()->route('upload');
//        }

        return redirect()->route('upload');
    }

    private function createBeneficearyBusinessProcess(Request $request)
    {
        $curl = curl_init();

        $jsonString = json_encode([
            "inn" => $request->inn,
            "name" => "Standard",
            "beneficiaryData" => [
                "kpp" => $request->kpp,
                "name" => $request->name,
            ],
        ], JSON_UNESCAPED_UNICODE);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/beneficiaries/createLegalAndVirtualAccount',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonString,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . Cache::get('access_token')
            ),
        ));

        curl_close($curl);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        return $result;
    }

    private function createBeneficearyPersonalProcess(Request $request)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/beneficiaries/createPhysicalAndVirtualAccount',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                                        "inn": "' . $request->inn . '",
                                        "accountType": "Standard",
                                        "beneficiaryData": {
                                            "birthDate": "' . $request->birth_date . '",
                                            "birthPlace": "' . $request->birth_place . '",
                                            "email": "' . $request->email . '",
                                            "firstName": "' . $request->first_name . '",
                                            "isResident": true,
                                            "lastName": "' . $request->last_name . '",
                                            "middleName": "' . $request->middle_name . '",
                                            "passportDate": "' . $request->passport_date . '",
                                            "passportIssuerCode": "' . $request->passport_issuer_code . '",
                                            "passportIssuerName": "' . $request->passport_issuer_name . '",
                                            "passportNumber": "' . $request->passport_number . '",
                                            "passportSeries": "' . $request->passport_series . '",
                                            "phoneNumber": "' . $request->phone_number . '",
                                            "registrationAddress": "' . $request->address . '"
                                        }
                                    }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . Cache::get('access_token')
            ),
        ));

        curl_close($curl);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        return $result;
    }

    public function getBeneficiary(string $beneficiaryId)
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/beneficiaries/' . $beneficiaryId,
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

        if ($result && $result['isSuccess'] === true && isset($result['value'])) {
            return $result['value']['beneficiary'];
        }

        return [];
    }

    public function activateBeneficiary($beneficiaryId)
    {

        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/beneficiaries/' . $beneficiaryId . '/state',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                                        "Value": true
                                    }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . Cache::get('access_token')
            ),
        ));

        curl_close($curl);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true) {
            return redirect()->back();
        }

        return null;
    }

    public function getListOfNotIdentifiedPayments()
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/payments?PerPage=10&Filters.IsIdentified=false',
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

        if ($result && $result['isSuccess'] === true && isset($result['value']['payments'])) {
            return $result['value']['payments'];
        }

        return [];

    }

    public function getListNotIdentifiedPayment(string $identificationNumber)
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/payments/' . $identificationNumber,
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

        if ($result && $result['isSuccess'] === true && isset($result['value'])) {
            return $result['value'];
        }

        return null;
    }

    public function paymentIdentificationProcess(string $identificationNumber, string $virtualAccountNumber, float $amount)
    {
        if (!Cache::has('access_token')) {
            $this->authorize();
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-gate.vestabankdev.ru/release/api/nominalaccounts-service/v2/partner/accounts/' . env('BANK_ACCOUNT_NUMBER') . '/payments/' . $identificationNumber . '/identification',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                                       "isReturnedPayment": false,
                                       "paymentOwners": [
                                           {
                                               "virtualAccountNumber": "' . $virtualAccountNumber . '",
                                               "amount": ' . $amount . '
                                           }
                                       ]
                                   }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . Cache::get('access_token')
            ),
        ));

        curl_exec($curl);

        return redirect()->back();
    }

    public function addBalance(string $virtualAccountId)
    {
        $paymentIds = $this->getListOfNotIdentifiedPayments();
        $payments = [];
        foreach ($paymentIds as $value) {
            $payments[] = $this->getListNotIdentifiedPayment($value);
        }


        return view('beneficiaries.add-balance', compact('payments', 'virtualAccountId'));
    }

    public function processBalance(Request $request, string $virtualAccountId)
    {
        $payment = json_decode($request->payment, true);

        $this->paymentIdentificationProcess($payment['id'], $virtualAccountId, $payment['amount']);
    }


}
