<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'surname', 'patronymic', 'card_number', 'status', 'status_description', 'amount', 'upload_id'];

    public const STATUSES = [
        'pending',
        'payment_initiated',
        'payment_initiation_problem',
        'payment_executed',
        'payment_in_process',
        'payment_closed',
        'payment_cancelled',
        'not_found'
    ];

    public const PROBLEM_DEFAULT_MESSAGES = [
        'payment_initiation_problem' => 'Ошибка выполнения запроса, пожалуйста повторите попытку или свяжитесь с банком',
        'payment_cancelled' => 'Ошибка обработки платежа, пожалуйста проверьте настройку вашего аккаунта или свяжитесь с банком'

    ];

    public const STATUS_MESSAGES = [
        'pending' => [
          'color' => 'bg-gray-100',
          'text' =>  'В ожидании'
        ],
        'payment_initiated' => [
            'color' => 'bg-gray-100',
            'text' =>  'Платеж создан'
        ],
        'payment_initiation_problem' => [
            'color' => 'bg-danger',
            'text' =>  'Проблема создания платежа'
        ],
        'payment_executed' => [
            'color' => 'bg-gray-300',
            'text' =>  'Отправлено'
        ],
        'payment_in_process' => [
            'color' => 'bg-blue-100',
            'text' =>  'Платеж в процессе'
        ],
        'payment_closed' => [
            'color' => 'bg-green-300',
            'text' =>  'Платеж проведен'
        ],
        'payment_cancelled' => [
            'color' => 'bg-danger',
            'text' =>  'Платеж отменен'
        ],
        'not_found' => [
            'color' => 'bg-danger',
            'text' =>  'Платеж не найден'
        ],
    ];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
