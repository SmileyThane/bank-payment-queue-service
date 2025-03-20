<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Upload extends Model
{
    use SoftDeletes;
    protected $fillable = ['file_name', 'file_path', 'outstanding_amount', 'hash', 'reference_id', 'beneficiary_id', 'virtual_account_id', 'user_id'];

    protected $appends = ['clients_count', 'broken_clients_count'];
    public function getClientsCountAttribute() {
        return $this->hasMany(Client::class, 'upload_id')->count();
    }
    public function getBrokenClientsCountAttribute() {
        return $this->hasMany(Client::class, 'upload_id')->whereIn('status', [Client::STATUSES[2], Client::STATUSES[6]])->count();
    }
}
