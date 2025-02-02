@extends('layouts.app')

@section('content')
<form action="{{ route('beneficiary.store') }}" method="POST" class="bg-white shadow-md rounded-lg p-6 space-y-4">
    @csrf

    <input type="hidden" name="type" value="business">
    <h3 class="text-lg font-semibold text-blue-600">Форма регистрации бенефициара</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="inn" class="block text-sm font-medium text-gray-700">ИНН</label>
            <input type="text" id="inn" name="inn" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Наименование</label>
            <input type="text" id="name" name="name" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
        </div>

        <div class="col-span-2">
            <label for="kpp" class="block text-sm font-medium text-gray-700">КПП</label>
            <input type="text" id="kpp" name="kpp" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" required>
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
