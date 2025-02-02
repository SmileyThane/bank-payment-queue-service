<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Upload;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class UploadController extends Controller
{
    final public function uploadFile(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx']);

        $file = $request->file('file');
        $referenceId = $request->string('reference_id');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('uploads');
        $outstandingAmount = 0;
        $virtualAccount = json_decode($request->virtual_account, true);

        $hash = Str::uuid();
        $data = Excel::toCollection(null, $file);
        $upload = Upload::query()->create([
            'file_name' => $fileName,
            'file_path' => $filePath,
            'hash' => $hash,
            'beneficiary_id' => $virtualAccount['beneficiaryId'],
            'virtual_account_id' => $virtualAccount['accountNumber'],
            'reference_id' => $referenceId,
        ]);

        foreach ($data[0] as $key => $row) {
            if ($key === 0) {
                continue;
            }

            $outstandingAmount += $row[4];
            Client::query()->create([
                'name' => $row[0],
                'surname' => $row[1],
                'patronymic' => $row[2],
                'card_number' => (string)$row[3],
                'amount' => $row[4],
                'upload_id' => $upload->id,
            ]);
        }

        $upload->outstanding_amount = $outstandingAmount;
        $upload->save();

        return back()->with('success', 'File uploaded successfully. Hash: ' . $hash);
    }

    final public function index(): View
    {
        $paymentController = new PaymentController();
        $uploads = Upload::query()->orderByDesc('id')->get();
        $balance = $paymentController->getActualBalance();
        $accounts = $paymentController->getVirtualAccountsList();
        $beneficiaries = [];

        foreach ($accounts as $key => $account) {
            $beneficiary = $paymentController->getBeneficiary($account['beneficiaryId']);

            $beneficiaries[] = $beneficiary;
            $accounts[$key]['beneficiary'] = [
                'id' => $beneficiary['id'] ?? null,
                'name' => $beneficiary['data']['name'] ?? null,
                'firstName' => $beneficiary['data']['firstName'] ?? null,
                'lastName' => $beneficiary['data']['lastName'] ?? null,
                'middleName' => $beneficiary['data']['middleName'] ?? null,
            ];
        }


        return view('home', compact('balance', 'uploads', 'accounts', 'beneficiaries'));
    }
}

