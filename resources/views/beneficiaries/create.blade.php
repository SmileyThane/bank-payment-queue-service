@extends('layouts.app')

@section('content')
<form action="{{ route('beneficiary.store') }}" method="POST" class="bg-white shadow-md rounded-lg p-6 space-y-4">
    @csrf

    <h3 class="text-lg font-semibold text-blue-600">Форма регистрации</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="inn" class="block text-sm font-medium text-gray-700">ИНН</label>
            <input type="text" id="inn" name="inn" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="birth_date" class="block text-sm font-medium text-gray-700">Дата рождения</label>
            <input type="date" id="birth_date" name="birth_date" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="birth_place" class="block text-sm font-medium text-gray-700">Место рождения</label>
            <input type="text" id="birth_place" name="birth_place" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" id="email" name="email" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700">Имя</label>
            <input type="text" id="first_name" name="first_name" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700">Фамилия</label>
            <input type="text" id="last_name" name="last_name" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="middle_name" class="block text-sm font-medium text-gray-700">Отчество</label>
            <input type="text" id="middle_name" name="middle_name" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
        </div>

        <div>
            <label for="passport_date" class="block text-sm font-medium text-gray-700">Дата выдачи паспорта</label>
            <input type="date" id="passport_date" name="passport_date" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="passport_issuer_code" class="block text-sm font-medium text-gray-700">Код подразделения</label>
            <input type="text" id="passport_issuer_code" name="passport_issuer_code" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="passport_issuer_name" class="block text-sm font-medium text-gray-700">Кем выдан паспорт</label>
            <input type="text" id="passport_issuer_name" name="passport_issuer_name" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="passport_number" class="block text-sm font-medium text-gray-700">Номер паспорта</label>
            <input type="text" id="passport_number" name="passport_number" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="passport_series" class="block text-sm font-medium text-gray-700">Серия паспорта</label>
            <input type="text" id="passport_series" name="passport_series" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="phone_number" class="block text-sm font-medium text-gray-700">Номер телефона</label>
            <input type="text" id="phone_number" name="phone_number" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div class="col-span-2">
            <label for="address" class="block text-sm font-medium text-gray-700">Адрес</label>
            <input type="text" id="address" name="address" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>
    </div>

    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-300 mt-5">
        Отправить
    </button>
</form>

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
