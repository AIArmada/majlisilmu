@extends('layouts.app')

@section('title', __('Reset Password') . ' - ' . config('app.name'))

@section('content')
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-50 py-20">
        <div
            class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-xl shadow-slate-200/50 rounded-3xl border border-slate-100 overflow-hidden">
            <div class="mb-8 text-center">
                <h2 class="font-heading text-3xl font-bold text-slate-900">{{ __('Reset Password') }}</h2>
                <p class="text-slate-500 mt-2">{{ __('Create a new secure password') }}</p>
            </div>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <!-- Email Address -->
                <div>
                    <label for="email" class="block font-medium text-sm text-slate-700">{{ __('Email') }}</label>
                    <input id="email"
                        class="block mt-1 w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                        type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus />
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mt-4">
                    <label for="password" class="block font-medium text-sm text-slate-700">{{ __('Password') }}</label>
                    <input id="password"
                        class="block mt-1 w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                        type="password" name="password" required autocomplete="new-password" />
                    @error('password')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mt-4">
                    <label for="password_confirmation"
                        class="block font-medium text-sm text-slate-700">{{ __('Confirm Password') }}</label>
                    <input id="password_confirmation"
                        class="block mt-1 w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                        type="password" name="password_confirmation" required autocomplete="new-password" />
                    @error('password_confirmation')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end mt-8">
                    <button type="submit"
                        class="w-full inline-flex justify-center items-center px-6 py-3 bg-emerald-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg shadow-emerald-500/20">
                        {{ __('Reset Password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection