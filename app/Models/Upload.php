<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Upload extends Model
{
    use SoftDeletes;
    protected $fillable = ['file_name', 'file_path', 'outstanding_amount', 'hash', 'reference_id', 'beneficiary_id', 'virtual_account_id'];
}
