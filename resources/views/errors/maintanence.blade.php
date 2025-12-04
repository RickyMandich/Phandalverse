@extends('layouts.error')
@section('code', '500')
@section('message')
    @if(Auth::admin())    
        @yield('specificMessage')
    @endif
    <br>
    La pagina è in manutenzione
@endsection