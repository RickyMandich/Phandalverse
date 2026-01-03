@extends('layouts.app')
@section('title', 'Hash Test Page')
@section('content')
    <div class="container">
        <h1>Hash Test Page</h1>
        <p>This page is used to test password hashing.</p>
    </div>
    @if ($mode == 'hashed')
        <div class="row">
            <div class="col-6">
                <span>testo in chiaro:</span>
                <pre class="form-control">{{ $plain }}</pre>
            </div>
            <div class="col-6">
                @if($match)
                    <div>
                        <span>Match: ✅</span>
                        <div>
                            <span>Hashed:</span>
                            <pre class="form-control">{{ $hashed }}</pre>
                        </div>
                    </div>
                @else
                    <div>
                        <span>Match: ❌</span>
                        <div>
                            <span>Hashed:</span>
                            <pre class="form-control">{{ $hashed }}</pre>
                        </div>
                        <div>
                            <span>new hash:</span>
                            <pre class="form-control">{{ $newHash }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    @elseif ($mode == 'toHash')
        <div class="row">
            <div class="col-6">
                <span>testo in chiaro:</span>
                <pre class="form-control">{{ $toHash }}</pre>
            </div>
            <div class="col-6">
                <span>Hashed:</span>
                <pre class="form-control">{{ $newHash }}</pre>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-6">
                <form action="{{ route('test.hash') }}">
                    <div class="mb-3">
                        <label for="plain" class="form-label">testo in chiaro</label>
                        <input type="text" class="form-control" id="plain" name="plain" required>
                    </div>
                    <div class="mb-3">
                        <label for="hashed" class="form-label">Hashed</label>
                        <input type="text" class="form-control" id="hashed" name="hashed">
                    </div>
                    <button type="submit" class="btn btn-primary">Test Hash</button>
                </form>
            </div>
            <div class="col-6">
                <form action="{{ route('test.hash') }}">
                    <div class="mb-3">
                        <label for="toHash" class="form-label">testo in chiaro</label>
                        <input type="text" class="form-control" id="toHash" name="toHash" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Hash</button>
                </form>
            </div>
        </div>
    @endif
@endsection