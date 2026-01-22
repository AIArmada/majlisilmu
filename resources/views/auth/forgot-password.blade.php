@extends('layouts.app')

@section('title', __('Forgot Password') . ' - ' . config('app.name'))

@section('content')
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-50 py-20">
        <div
            class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-xl shadow-slate-200/50 rounded-3xl border border-slate-100 overflow-hidden">
            <div class="mb-8 text-center">
                <h2 class="font-heading text-3xl font-bold text-slate-900">{{ __('Forgot Password') }}</h2>
                <p class="text-slate-500 mt-2 text-sm">
                    {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link.') }}
                </p>
            </div>

            <!-- Session Status -->
            @if (session('status'))
                <div
                    class="mb-4 font-medium text-sm text-green-600 border border-green-200 bg-green-50 rounded-xl p-3 text-center">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block font-medium text-sm text-slate-700">{{ __('Email') }}</label>
                    <input id="email"
                        class="block mt-1 w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                        type="email" name="email" value="{{ old('email') }}" required autofocus />
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end mt-8">
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-6 py-3 bg-emerald-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg shadow-emerald-500/20">
                        {{ __('Email Password Reset Link') }}
                    </button>
                </div>

                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}"
                        class="text-sm font-semibold text-slate-500 hover:text-emerald-600 transition-colors">
                        ← {{ __('Back to Login') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection