<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UploadController;



Route::middleware('auth')->group(function () {
    Route::get('/', [UploadController::class, 'index'])->name('home');
    Route::post('/upload', [UploadController::class, 'uploadFile'])->name('upload');
    Route::get('/upload/{id}/delete', [UploadController::class, 'destroy'])->name('deleteClients');
    Route::get('/upload/{hash}', [ClientController::class, 'showByHash'])->name('clients');
    Route::get('/upload/{hash}/pay', [PaymentController::class, 'initDeals'])->name('initDeals');
    Route::get('/upload/{hash}/pay/process', [PaymentController::class, 'initPaymentProcess'])->name('initPaymentProcess');

//    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::get('/beneficiary/create', function () {
        return view('beneficiaries.create');
    })->name('beneficiary.create');

    Route::get('/beneficiary/create-business', function () {
        return view('beneficiaries.create-business');
    })->name('beneficiary.create-business');

    Route::post('/beneficiary', [PaymentController::class, 'createBeneficiary'])->name('beneficiary.store');

    Route::get('/beneficiary/{beneficiaryId}/activate', [PaymentController::class, 'activateBeneficiary'])->name('activateBeneficiary');
    Route::get('/beneficiary/{beneficiaryId}/virtual-account/{virtualAccountId}/add-balance', [PaymentController::class, 'addBalance'])->name('virtualAccount.addBalance');
    Route::post('/virtual-account/{virtualAccountId}/add-balance', [PaymentController::class, 'processBalance'])->name('virtualAccount.processBalance');
});

require __DIR__.'/auth.php';
