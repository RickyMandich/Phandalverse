@extends('layouts.error')
@section('code', $__env->yieldContent('code') ?: '503')
@section('message')
    @if(Auth::check() && Auth::user()->isAdmin())
        @yield('specificMessage')
    @endif
    <br>
    La pagina è in manutenzione
@endsection