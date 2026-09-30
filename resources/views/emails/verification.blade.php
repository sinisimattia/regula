@extends('layouts.emails.base_layout')

@section('content')
    <h1>@lang('emails.verification.title')</h1>

    <p>@lang('emails.verification.description')</p>

    <x-emails.cta-button :url="$verificationUrl" :label="__('emails.verification.cta')" />

    <p style="margin-top: 32px; font-size: 14px; color: {{ config('theme.colors.gray') }};">@lang('emails.verification.footer_text')</p>
@endsection
