<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
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
        'pending' => 'В ожидании',
        'payment_initiated' => 'Платеж создан',
        'payment_initiation_problem' => 'Проблема создания платежа',
        'payment_executed' => 'Платеж выполнен',
        'payment_in_process' => 'Платеж в процессе',
        'payment_closed' => 'Платеж проведен',
        'payment_cancelled' => 'Платеж отменен',
        'not_found' => 'Платеж не найден'
    ];

}
