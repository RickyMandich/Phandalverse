@extends('layouts.error')
@section('code', @yield('code', '500'))
@section('message')
    @if(Auth::isAdmin())
        @yield('specificMessage')
    @endif
    <br>
    La pagina è in manutenzione
@endsection