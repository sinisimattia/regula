@extends('layouts.emails.base_layout')

@section('content')
    <h1>@lang('emails.delete_account.title')</h1>

    <p>@lang('emails.delete_account.greeting', ['name' => $displayName])</p>

    <p>@lang('emails.delete_account.confirmation')</p>

    <p>@lang('emails.delete_account.farewell')</p>
@endsection
