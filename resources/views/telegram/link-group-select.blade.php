@extends('layouts.app')

@section('title', 'Collega gruppo a una campagna')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-telegram"></i> Collega questo gruppo a una campagna
                </div>
                <div class="card-body">
                    @if($currentlyLinked)
                        <div class="alert alert-info">
                            Questo gruppo è attualmente collegato a <strong>{{ $currentlyLinked->display_name }}</strong>.
                            Selezionando un'altra campagna, il collegamento a <strong>{{ $currentlyLinked->display_name }}</strong> verrà rimosso.
                        </div>
                    @endif

                    <p class="text-muted">
                        Un gruppo può essere collegato a una sola campagna alla volta. Scegli a quale campagna vuoi
                        collegare questo gruppo:
                    </p>

                    <form method="POST" action="{{ route('telegram.link.group.store', $linkToken->token) }}">
                        @csrf
                        <div class="mb-3">
                            <select name="campaign_id" class="form-select" required>
                                <option value="" disabled {{ !$currentlyLinked ? 'selected' : '' }}>Seleziona una campagna...</option>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" {{ $currentlyLinked && $currentlyLinked->id === $campaign->id ? 'selected' : '' }}>
                                        {{ $campaign->display_name }}
                                        @if($campaign->telegram_chat_id && (!$currentlyLinked || $currentlyLinked->id !== $campaign->id))
                                            (già collegata a un altro gruppo)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-link-45deg"></i> Collega gruppo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
