<?php

namespace App\Http\Controllers;

use App\Jobs\CreatePaymentInitiationJob;
use App\Jobs\CreatePaymentProcessJob;
use App\Models\Client;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;

class PaymentController extends Controller
{
    /**
     * @throws JsonException
     */
    final public function getActualBalance(): float
    {
        $userId = Auth::id();
        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }


        $bankData = $this->getBankData($userId);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/balance',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp()) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);

        $result = json_decode($response, true);

        if ($result && $result['value'] && $result['value']['endBalance']) {
            return $result['value']['endBalance'];
        }

        return 0;
    }

    final public function getVirtualActualBalance(string $beneficiaryId, string $virtualAccountId): float
    {
        $userId = Auth::id();
        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/beneficiaries/' . $beneficiaryId . '/virtual-accounts/' . $virtualAccountId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp()) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

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
    final public function authorize(int $userId = null): void
    {
        $curl = curl_init();

        $bankData = $this->getBankData($userId);
        $bankClient = $bankData['BANK_CLIENT'];
        $bankSecret = $bankData['BANK_SECRET'];

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/sso/connect/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'client_id=' . $bankClient . '&client_secret=' . $bankSecret . '&scope=' . env('BANK_SCOPE') . '&grant_type=' . env('BANK_GRANT_TYPE'),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($response, true);

        if ($result && isset($result['access_token'])) {
            Cache::add('access_token_' . $userId ?? Auth::id(), $result['access_token'], 60);
        } else {
            Log::warning($response);
        }
    }

    /**
     * @throws JsonException
     */
    final public function initDeals(Request $request, string $hash): RedirectResponse
    {
        $upload = Upload::query()->where('hash', $hash)->firstOrFail();
        if ($upload) {
            $clients = Client::query()->where('upload_id', $upload->id)->get();
            CreatePaymentInitiationJob::dispatch(Auth::id(), $upload, $clients, $upload->virtual_account_id, $request->payment_comment);
            $upload->is_executed = 1;
            $upload->save();
        }

        return redirect()->back();
    }

    final public function initPaymentProcess(Request $request, string $hash): RedirectResponse
    {
        $upload = Upload::query()->where('hash', $hash)->firstOrFail();
        if ($upload) {
            $clients = Client::query()
                ->where('upload_id', '=', $upload->id)
                ->where('status', '=', Client::STATUSES[1])
                ->get();
            CreatePaymentProcessJob::dispatch(Auth::id(), $clients);
            $upload->is_processed = 1;
            $upload->save();
        }

        return redirect()->back();

    }

    public function initPayment(int $userId, $upload, $clients, string $virtualAccountId, $paymentComment = null): void
    {
        foreach ($clients as $client) {
            if (!$client->deal_id) {
                $client->deal_id = Str::uuid();
                $client->save();

                Log::info('Process initiated: client ID: ' . $client->id . '; Upload ID: ' . $client->upload_id . '; Deal ID: ' . $client->deal_id . ';');

                $purpose = ($paymentComment ?? 'Отправка на карту. ') . $client->name . ' ' . $client->surname;
                $dealId = $this->createPayment($userId, $client->id, $client->deal_id, $virtualAccountId, $client->card_number, $client->amount, $purpose);
                if ($dealId) {
                    $client->status = Client::STATUSES[1];
                    $client->save();
                }
            }
        }

        $upload->is_deals_created = true;
        $upload->save();
    }

    /**
     * @throws JsonException
     */
    private function createPayment(int $userId, int $id, string $dealId, string $virtualAccountId, string $cardNumber, float $amount, string $purpose = ''): string|null
    {
        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/deals',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                                        "id": "' . $dealId . '",
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
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ],
        ]);

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && $result['value']['dealId']) {
            Log::info('Deal created: client ID: ' . $id . '; Deal ID: ' . $result['value']['dealId'] . ';');

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

    public function processPayment(int $userId, int $clientId, string $dealId): void
    {
        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/deals/' . $dealId . '/execute',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true) {
            Client::query()->where('id', $clientId)->update([
                'status' => Client::STATUSES[3],
            ]);

            Log::info('Processed: client ID: ' . $clientId . '; Deal ID: ' . $dealId . ';');

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
            ->where('deal_id', '!=', null)
            ->whereIn('status', [Client::STATUSES[3],Client::STATUSES[4]])
            ->get();

        foreach ($clients as $client) {
            $deal = $this->getDeal($client->upload->user_id, $client->deal_id);
            if ($deal) {
                $client->status_description = $deal;
                if ($deal['status'] === 'InProcess') {
                    $client->status = Client::STATUSES[4];
                }

                if ($deal['status'] === 'Closed') {
                    $client->status = Client::STATUSES[5];

                }

                if (
                    $deal['status'] === 'Rejected'
//                    || $deal['status'] === 'Correction' blocked to make double check
                ) {
                    $client->status = Client::STATUSES[6];
                }
            } else {
//                $client->status = Client::STATUSES[7];
            }

            $client->save();
        }
    }

    public function getDeal(int $userId, string $dealId): array|null
    {

        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/deals/' . $dealId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && $result['value']) {
            return $result['value'];
        }

        return null;
    }

    public function getVirtualAccountsList()
    {
        $userId = Auth::id();
        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize();
        }

        $bankData = $this->getBankData();
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/virtual-accounts/search?perPage=100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp()) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && isset($result['value']['virtualAccounts'])) {
            return $result['value']['virtualAccounts'];
        }

        return [];
    }

    public function createBeneficiary(Request $request)
    {
        $userId = Auth::id();
        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        if ($request->type === 'business') {
            $result = $this->createBeneficearyBusinessProcess($request);
        } else {
            $result = $this->createBeneficearyPersonalProcess($request);
        }

        Cache::clear();

//        TODO: add validation errors
//        if ($result && $result['isSuccess'] === true) {
//            return redirect()->route('upload');
//        }

        return redirect()->route('home');
    }

    private function createBeneficearyBusinessProcess(Request $request)
    {
        $userId = Auth::id();
        $bankData = $this->getBankData($userId);
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
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/beneficiaries/createLegalAndVirtualAccount',
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
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        curl_close($curl);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        return $result;
    }

    private function createBeneficearyPersonalProcess(Request $request)
    {
        $userId = Auth::id();
        $bankData = $this->getBankData($userId);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/beneficiaries/createPhysicalAndVirtualAccount',
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
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

//        curl_close($curl);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        return $result;
    }

    public function getBeneficiary(string $beneficiaryId)
    {
        $beneficiary = Cache::get($beneficiaryId);
        if ($beneficiary) {
            return $beneficiary;
        }

        $beneficiary = [];
        $userId = Auth::id();

        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/beneficiaries/' . $beneficiaryId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && isset($result['value'])) {
            $beneficiary = $result['value']['beneficiary'];
        }

        Cache::put($beneficiaryId, $beneficiary, 7200);

        return $beneficiary;
    }

    public function activateBeneficiary($beneficiaryId)
    {
        $userId = Auth::id();

        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/beneficiaries/' . $beneficiaryId . '/state',
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
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        curl_close($curl);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true) {
            Cache::clear();

        }

        if ($result && $result['isSuccess'] === false) {
            return redirect()->route('home')->with('activation_message', $result['error']['message']);
        }

        return redirect()->route('home');
    }

    public function getListOfNotIdentifiedPayments()
    {
        $userId = Auth::id();

        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/payments?PerPage=10&Filters.IsIdentified=false',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp($userId)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && isset($result['value']['payments'])) {
            return $result['value']['payments'];
        }

        return [];

    }

    public function getListNotIdentifiedPayment(string $identificationNumber)
    {
        $userId = Auth::id();

        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/payments/' . $identificationNumber,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp()) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result && $result['isSuccess'] === true && isset($result['value'])) {
            return $result['value'];
        }

        return null;
    }

    public function paymentIdentificationProcess(string $identificationNumber, string $virtualAccountNumber, float $amount)
    {
        $userId = Auth::id();
        if (!Cache::has('access_token_' . $userId)) {
            $this->authorize($userId);
        }

        $bankData = $this->getBankData($userId);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('BANK_MAIN_URL') . '/api/nominalaccounts-service/v2/partner/accounts/' . $bankData['BANK_ACCOUNT_NUMBER'] . '/payments/' . $identificationNumber . '/identification',
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
                'Authorization: Bearer ' . Cache::get('access_token_' . $userId)
            ),
        ));

        if ($ip = $this->prepareUserIp()) {
            curl_setopt($curl, CURLOPT_INTERFACE, $ip);
        }

        curl_exec($curl);

        return redirect()->back();
    }

    final public function addBalance(string $beneficiaryId, string $virtualAccountId): View
    {
        $payments = [];
        $paymentIds = $this->getListOfNotIdentifiedPayments();
        $beneficiary = $this->getBeneficiary($beneficiaryId);
        foreach ($paymentIds as $value) {
            $payments[] = $this->getListNotIdentifiedPayment($value);
        }

        return view('beneficiaries.add-balance', compact('payments', 'virtualAccountId', 'beneficiary'));
    }

    public function processBalance(Request $request, string $virtualAccountId)
    {
        $payment = json_decode($request->payment, true);
        $this->paymentIdentificationProcess($payment['id'], $virtualAccountId, $payment['amount']);

        return redirect()->route('home');
    }

    private function getBankData($userId = null)
    {
        $user = User::query()->find($userId) ?? Auth::user();
        return json_decode(base64_decode($user->bank_data), true);
    }

    private function getLocalIps(): array {
        $output = shell_exec("ip -o -4 addr show | awk '{print $4}'");
        $lines = explode("\n", trim($output));
        return array_map(static fn($cidr) => explode('/', $cidr)[0], $lines);
    }
    private function prepareUserIp($userId = null):null|string
    {
        $user = User::query()->find($userId) ?? Auth::user();

        if ($user) {
            return in_array($user->ip_address, $this->getLocalIps()) ? $user->ip_address : null;
        }

        return null;
    }
}
