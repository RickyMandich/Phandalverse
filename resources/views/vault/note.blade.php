@extends('layouts.app')

@section('content')
{{ Auth::user() }}
---
<div class="vault-note">
    {!! $html !!}
</div>
@endsection