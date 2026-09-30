@extends('layouts.emails.base_layout')

@section('content')
    <h1>@lang('emails.reset_password.title')</h1>

    <p>@lang('emails.reset_password.description')</p>

    <x-emails.cta-button :url="$resetUrl" :label="__('emails.reset_password.cta')" />

    <p style="margin-top: 32px; font-size: 14px; color: {{ config('theme.colors.gray') }};">@lang('emails.reset_password.footer_text')</p>
@endsection
