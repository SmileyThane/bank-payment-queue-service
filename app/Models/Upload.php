<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = ['file_name', 'file_path', 'outstanding_amount', 'hash', 'reference_id', 'beneficiary_id', 'virtual_account_id'];
}
