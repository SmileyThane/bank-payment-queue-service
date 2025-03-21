@php use App\Models\Client; @endphp
@extends('layouts.app')

@section('content')
    <div class="container mx-auto my-12 space-y-12">
        <!-- Clients Section -->
        <div class="bg-white shadow-md rounded-lg">
            <!-- Header Section -->
            <div class="bg-gray-50 p-6 rounded-t-lg">
                <h3 class="text-lg font-semibold text-blue-600">Клиенты</h3>
                <div class="flex justify-between items-center mt-2">
                    <div>
                        @if(isset($beneficiary))
                            <div class="mb-3">
                                @if(isset($beneficiary['isActive']) && $beneficiary['isActive'] === false)
                                    <a href="{{ route('activateBeneficiary', ['beneficiaryId' => $beneficiary['id']]) }}"
                                       class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-300">
                                        <i class="bi bi-upload"></i>
                                        Активировать
                                    </a>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600">Бенефициар активирован:
                                <strong class="text-black">{{ $beneficiary['isActive'] ? 'Да' : 'Нет' }}</strong>
                            </p>
                            <p class="text-sm text-gray-600">ИНН бенефициара:
                                <strong class="text-black">{{ $beneficiary['inn'] }}</strong>
                            </p>
                            @if(isset($beneficiary['data']['name']))
                                <p class="text-sm text-gray-600">Наименование бенефициара:
                                    <strong class="text-black">{{ $beneficiary['data']['name'] }}</strong>
                                </p>
                            @else
                                <p class="text-sm text-gray-600">Наименование бенефициара:
                                    <strong
                                        class="text-black">{{ $beneficiary['data']['lastName'] }} {{ $beneficiary['data']['firstName'] }} {{ $beneficiary['data']['middleName'] }}</strong>
                                </p>
                            @endif
                            @if(isset($beneficiary['data']['email']))
                                <p class="text-sm text-gray-600">Еmail бенефициара:
                                    <strong class="text-black">{{ $beneficiary['data']['email'] }}</strong>
                                </p>
                            @endif
                        @endif
                        <p class="text-sm text-gray-600">ID бенефициара:
                            <strong class="text-black">{{ $upload['beneficiary_id'] }}</strong>
                        </p>
                        <p class="text-sm text-gray-600">ID виртуального аккаунта: <strong
                                class="text-black">{{ $upload->virtual_account_id }}</strong></p>
                        <p class="text-sm text-gray-600">Актуальный баланс: <strong
                                class="text-black">{{ number_format($balance, 2, ',', ' ') }}</strong></p>
                        <p class="text-sm text-gray-600">Не обработано получателей: <strong
                                class="text-black">{{ $upload['clients_count'] - $processedClientsCount }}</strong></p>
                        <p class="text-sm text-gray-600">Обработано получателей: <strong
                                class="text-black">{{ $processedClientsCount }}</strong></p>
                    </div>
                    <div>
                        @if($isExecuted && $isProcessed)
                            <p class="text-sm text-gray-600">Выполнение завершено.</p>
                        @endif
                        @if($outstandingAmount > $balance)
                            <p class="text-sm text-red-700">Недостаточно средств.</p>
                        @else
                            @if(!$isExecuted)
                                <form action="{{ route('initDeals', ['hash' => $hash]) }}" method="get"
                                      class="inline-block">
                                    <div class="mb-2">
                                        <label for="payment_comment"
                                               class="block text-sm font-medium text-gray-700 mb-2">Комментарий</label>
                                        <input name="payment_comment" id="payment_comment"
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                        >
                                    </div>
                                    <button type="submit"
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-300">
                                        <i class="bi bi-upload"></i>
                                        Подтвердить сделки
                                    </button>
                                </form>
                            @endif
                            @if($isExecuted && $isDealsCreated && !$isProcessed)
                                <form action="{{ route('initPaymentProcess', ['hash' => $hash]) }}" method="get"
                                      class="inline-block">
                                    <button type="submit"
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-300">
                                        <i class="bi bi-upload"></i>
                                        Оплатить
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <!-- Clients Table -->
            <div class="p-6">
                <div class="mb-2">
                    <form action="{{ route('clients', ['hash' => $hash]) }}" method="get"
                          class="flex">
                        <input name="search" id="search"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                               value="{{ $search }}"
                        >
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-300">
                            <i class="bi bi-upload"></i>
                            Поиск
                        </button>
                    </form>

                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto border-collapse">
                        <thead class="bg-blue-50 border-b border-blue-200">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">#</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Имя</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Фамилия</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Отчество</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Номер карты</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Сумма</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Статус</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Описание статуса</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($clients as $key => $client)
                            <tr class="border-b last:border-none">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $key+1 }}</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $client->name }}</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $client->surname }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ $client->patronymic }}</td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-block bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium shadow-sm">{{ $client->card_number }}</span>
                                </td>
                                <td style="min-width: 150px;"
                                    class="px-4 py-2 text-sm text-gray-700 text-end">{{ number_format($client->amount, 2, ',', ' ') }}</td>
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-block px-3 py-1 {{Client::STATUS_MESSAGES[$client->status]['color']}} rounded-full text-xs font-medium shadow-sm">
                                        {{ Client::STATUS_MESSAGES[$client->status]['text'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    @if(!json_validate($client->status_description))
                                        <span
                                            class="inline-block px-3 py-1 rounded-full text-xs font-medium shadow-sm bg-gray-100 text-gray-900">
                                            {{ $client->status_description }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Styles -->
    <style>
        html {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
        }

        .rounded-lg {
            border-radius: 0.5rem;
        }

        .rounded-t-lg {
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }

        .shadow-md {
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }

        .badge {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .btn {
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            .container {
                max-width: 720px;
            }
        }

        @media (min-width: 1024px) {
            .container {
                max-width: 960px;
            }
        }

        @media (min-width: 1400px) {
            .container {
                max-width: 1280px;
            }
        }
    </style>

@endsection
