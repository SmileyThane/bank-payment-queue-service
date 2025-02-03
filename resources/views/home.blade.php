@extends('layouts.app')

@section('content')
    @if(\Session::has('activation_message'))
        <div class="alert alert-danger" role="alert">
            {{ \Session::get('activation_message') }}
        </div>
    @endif
    <div class="container mx-auto my-12 space-y-12">
        <!-- Tabs Navigation -->
        <div class="bg-white shadow-md rounded-lg">
            <div class="flex border-b border-gray-200 items-center justify-between">
                <div class="flex">
                    <button class="tab-link w-1/3 py-4 px-4 text-center text-blue-600 font-semibold border-b-2 border-blue-600 focus:outline-none active" data-tab="uploadTab">Работа с реестрами</button>
                    <button class="tab-link w-1/3 py-4 px-4 text-center text-gray-600 font-semibold border-b-2 border-transparent hover:text-blue-600 hover:border-blue-600 focus:outline-none" data-tab="beneficiaryTab">Список бенефициаров</button>
                    <button class="tab-link w-1/3 py-4 px-4 text-center text-gray-600 font-semibold border-b-2 border-transparent hover:text-blue-600 hover:border-blue-600 focus:outline-none" data-tab="virtualAccountsTab">Список виртуальных банковских счетов</button>
                </div>
                <p class="text-sm text-gray-600 text-center p-2 me-2 bg-gray-200">Баланс Н/C: <strong class="text-black">{{ number_format($balance, 2, ',', ' ') }}</strong></p>
            </div>

            <!-- Upload Registry Tab -->
            <div id="uploadTab" class="tab-content p-6">
                <div class="container mx-auto my-12 space-y-12">
                    <!-- Upload Form -->
                    <div class="bg-white shadow-md rounded-lg">
                        <div class="bg-blue-600 text-white rounded-t-lg py-4 text-center">
                            <h1 class="text-lg font-semibold">Загрузите ваш реестр</h1>
                        </div>
                        <div class="p-6">
                            <form action="{{ route('upload') }}" method="POST" enctype="multipart/form-data"
                                  class="space-y-6">
                                @csrf
                                <div>
                                    <label for="fileUpload" class="block text-sm font-medium text-gray-700 mb-2">Выберите
                                        файл</label>
                                    <input type="file" name="file" id="fileUpload"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                           required>
                                    <p class="mt-1 text-sm text-gray-500">Доступные форматы: .xlsx, .csv</p>
                                </div>
                                <div>
                                    <label for="reference_id" class="block text-sm font-medium text-gray-700 mb-2">Идентификатор
                                        источника</label>
                                    <input name="reference_id" id="reference_id"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                                <div>
                                    <label for="reference_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Виртуальный аккаунт
                                    </label>
                                    <select class="form-select form-select-md mb-3" name="virtual_account"
                                            aria-label=".form-select-lg example" required>
                                        @foreach($accounts as $account)
                                            <option
                                                @if($account['availableFunds'] <= 0)
                                                    disabled
                                                @endif
                                                value="{{ json_encode($account) }}"
                                            >
                                                @if(isset($account['beneficiary']['name']))
                                                    <p class="text-sm text-gray-600">Наименование:
                                                        {{$account['beneficiary']['name'] }}
                                                    </p>
                                                @else
                                                    @if(isset($account['beneficiary']))
                                                        <p class="text-sm text-gray-600">Наименование:
                                                            {{ $account['beneficiary']['lastName'] }} {{ $account['beneficiary']['firstName'] }} {{ $account['beneficiary']['middleName'] }}
                                                        </p>
                                                    @endif
                                                @endif
                                                Баланс: {{$account['availableFunds']}} ID: {{ $account['accountNumber'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="text-right">
                                    <button type="submit"
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-300">
                                        <i class="bi bi-upload"></i> Загрузить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Uploaded Files -->
                    <div class="bg-white shadow-md rounded-lg">
                        <div class="bg-gray-50 rounded-t-lg p-6">
                            <h3 class="text-lg font-semibold text-blue-600">Загруженные реестры</h3>
                            <p class="text-sm text-gray-600">Загруженные реестры отображены ниже.</p>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full table-auto border-collapse">
                                    <thead class="bg-blue-50 border-b border-blue-200">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Имя файла</th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">ID источника
                                        </th>
                                        {{--                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Хэш-сумма</th>--}}
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Сумма к
                                            выплате
                                        </th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Создано</th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Обновлено</th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Статус</th>
                                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Действия</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($uploads as $upload)
                                        <tr class="border-b last:border-none">
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $upload->file_name }}</td>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $upload->reference_id }}</td>
                                            <td class="px-4 py-2">
                                    <span
                                        class="inline-block text-blue-700 px-3 py-1 text-xs font-medium text-end" style="min-width: 150px;">
                                        {{ number_format($upload->outstanding_amount, 2, ',', ' ') }}
                                    </span>
                                            </td>
                                            <td class="px-4 py-2">
                                     <span class="inline-block px-3 py-1 text-xs font-medium">
                                           {{ $upload->created_at }}
                                      </span>
                                            </td>
                                            <td class="px-4 py-2">
                                     <span class="inline-block px-3 py-1 text-xs font-medium">
                                           {{ $upload->updated_at }}
                                      </span>
                                            </td>
                                            <td class="px-4 py-2">
                                                @if($upload->is_executed === 1)
                                                    <span
                                                        class="inline-block bg-green-300 px-3 py-1 rounded-full text-xs font-medium">
                                            Обработан
                                        </span>
                                                @else
                                                    <span
                                                        class="inline-block bg-yellow-300 px-3 py-1 rounded-full text-xs font-medium">
                                        Не обработан
                                        </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-left">
                                                <a href="{{ route('clients', $upload->hash) }}"
                                                   class="bg-blue-500 text-white px-3 py-1 rounded-md text-xs font-medium hover:bg-blue-600 focus:outline-none focus:ring focus:ring-blue-300 inline-block">
                                                    <i class="bi bi-eye"></i> Показать клиентов
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Processed Registries Tab -->
            <div id="beneficiaryTab" class="tab-content p-6 hidden">
                <h3 class="text-lg font-semibold text-blue-600">Список бенефициаров</h3>
                <div class="overflow-x-auto mt-4">
                    <table class="min-w-full table-auto border-collapse">
                        <thead class="bg-blue-50 border-b border-blue-200">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Наименование</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Активирован</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">ИНН</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($beneficiaries as $beneficiary)
                            <tr class="border-b last:border-none">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                    @if(isset($beneficiary['data']['name']))
                                        <p class="text-sm text-gray-600">
                                            {{$beneficiary['data']['name'] }}
                                        </p>
                                    @else
                                        <p class="text-sm text-gray-600">
                                            {{ $beneficiary['data']['lastName'] }} {{ $beneficiary['data']['firstName'] }} {{ $beneficiary['data']['middleName'] }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                    <p class="text-sm text-gray-600">
                                        {{ $beneficiary['isActive'] ? 'Да' : 'Нет' }}
                                    </p>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700">
                                    <p class="text-sm text-gray-600">
                                        {{ $beneficiary['inn'] }}
                                    </p>

                                </td>
                                <td class="px-4 py-2 text-left">
                                    <a href="{{ route('activateBeneficiary', $beneficiary['id']) }}"
                                       @if($beneficiary['isActive'])
                                           disabled=""
                                       @endif
                                       class="bg-blue-500 text-white px-3 py-1 rounded-md text-xs font-medium hover:bg-blue-600 focus:outline-none focus:ring focus:ring-blue-300 inline-block">
                                        <i class="bi bi-check"></i>
                                        Активировать
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="virtualAccountsTab" class="tab-content p-6 hidden">
                <h3 class="text-lg font-semibold text-blue-600">Список виртуальных банковских счетов</h3>
                <div class="overflow-x-auto mt-4">
                    <table class="min-w-full table-auto border-collapse">
                        <thead class="bg-blue-50 border-b border-blue-200">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">ID Виртуального аккаунта
                            </th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Наименование бенефициара
                            </th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Актуальный баланс</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($accounts as $account)
                            <tr class="border-b last:border-none">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                    <p class="text-sm text-gray-600">
                                        {{ $account['accountNumber']}}
                                    </p>
                                </td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                    @if(isset($account['beneficiary']['name']))
                                        <p class="text-sm text-gray-600">
                                            {{$account['beneficiary']['name'] }}
                                        </p>
                                    @else
                                        @if(isset($account['beneficiary']))
                                            <p class="text-sm text-gray-600">
                                                {{ $account['beneficiary']['lastName'] }} {{ $account['beneficiary']['firstName'] }} {{ $account['beneficiary']['middleName'] }}
                                            </p>
                                        @endif
                                    @endif
                                </td>

                                <td class="px-4 py-2 text-sm text-gray-700">
                                    <p class="text-sm text-gray-600 text-end">
                                        {{ number_format($account['availableFunds'], 2, ',', ' ') }}
                                    </p>

                                </td>
                                <td class="px-4 py-2 text-left">
                                    <a href="{{ route('virtualAccount.addBalance', ['beneficiaryId' => $account['beneficiary']['id'], 'virtualAccountId' => $account['accountNumber']]) }}"
                                       class="bg-blue-500 text-white px-3 py-1 rounded-md text-xs font-medium hover:bg-blue-600 focus:outline-none focus:ring focus:ring-blue-300 inline-block">
                                        <i class="bi bi-cash"></i>
                                        Пополнить баланс
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Tab Switching -->
    <script>
        document.querySelectorAll('.tab-link').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tab-link').forEach(btn => {
                    btn.classList.remove('text-blue-600', 'border-blue-600');
                    btn.classList.add('text-gray-600', 'border-transparent');
                });
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));

                button.classList.add('text-blue-600', 'border-blue-600');
                button.classList.remove('text-gray-600', 'border-transparent');
                document.getElementById(button.getAttribute('data-tab')).classList.remove('hidden');
            });
        });
    </script>

    <style>
        html {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
        }

        /* Ensure Utility Consistency */
        .btn {
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        .rounded-md {
            border-radius: 0.375rem;
        }

        .rounded-lg {
            border-radius: 0.5rem;
        }

        /* Responsive Design Consistency */
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


