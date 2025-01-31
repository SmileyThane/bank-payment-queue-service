@extends('layouts.app')

@section('content')
    <div class="container mx-auto my-12 space-y-12">
        <!-- Upload Form -->
        <div class="bg-white shadow-md rounded-lg">
            <div class="bg-blue-600 text-white rounded-t-lg py-4 text-center">
                <h1 class="text-lg font-semibold">Загрузите ваш реестр</h1>
            </div>
            <div class="p-6">
                <form action="{{ route('upload') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
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
                                    @if(isset($account['beneficiary']['data']['name']))
                                        <p class="text-sm text-gray-600">Наименование бенефициара:
                                            {{$account['beneficiary']['data']['name'] }}
                                        </p>
                                    @else
                                        <p class="text-sm text-gray-600">Наименование бенефициара:
                                            {{ $account['beneficiary']['data']['lastName'] }} {{ $account['beneficiary']['data']['firstName'] }} {{ $account['beneficiary']['data']['middleName'] }}
                                        </p>
                                    @endif
                                    Баланс: {{$account['availableFunds']}} ID Виртуального аккаунта: {{ $account['accountNumber'] }}
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
                <p class="text-sm text-gray-600">Актуальный баланс: <strong class="text-black">{{ $balance }}</strong>
                </p>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto border-collapse">
                        <thead class="bg-blue-50 border-b border-blue-200">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Имя файла</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">ID источника</th>
                            {{--                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Хэш-сумма</th>--}}
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Сумма к выплате</th>
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
                                {{--                                <td class="px-4 py-2">--}}
                                {{--                                    <span class="inline-block bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-medium">--}}
                                {{--                                        {{ $upload->hash }}--}}
                                {{--                                    </span>--}}
                                {{--                                </td>--}}
                                <td class="px-4 py-2">
                                    <span
                                        class="inline-block bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">
                                        {{ $upload->outstanding_amount }}
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

    <!-- Unified Styles for Simplicity -->
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
