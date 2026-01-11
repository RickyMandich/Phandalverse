@extends('layouts.app')

@section('title', 'Segnala un problema')

@section('style')
<style>
    .report-form {
        max-width: 700px;
        margin: 0 auto;
    }
    
    .category-card {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid transparent;
    }
    
    .category-card:hover {
        border-color: #4299e1;
        background: rgba(66, 153, 225, 0.1);
    }
    
    .category-card.selected {
        border-color: #4299e1;
        background: rgba(66, 153, 225, 0.2);
    }
    
    .category-card input[type="radio"] {
        display: none;
    }
    
    .category-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
</style>
@endsection

@section('content')
<div class="report-form">
    <h2 class="mb-4">📢 Segnala un problema</h2>
    
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('report.store') }}" method="POST">
        @csrf
        
        {{-- Categoria --}}
        <div class="mb-4">
            <label class="form-label fw-bold">Tipo di problema</label>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="category-card card h-100 text-center p-3 {{ old('category') === 'logic' ? 'selected' : '' }}">
                        <input type="radio" name="category" value="logic" {{ old('category') === 'logic' ? 'checked' : '' }} required>
                        <div class="category-icon">🧠</div>
                        <div class="fw-bold">Problema logico</div>
                        <small class="text-muted">Contenuti che non hanno senso</small>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="category-card card h-100 text-center p-3 {{ old('category') === 'display' ? 'selected' : '' }}">
                        <input type="radio" name="category" value="display" {{ old('category') === 'display' ? 'checked' : '' }}>
                        <div class="category-icon">👁️</div>
                        <div class="fw-bold">Visualizzazione</div>
                        <small class="text-muted">Pagine mal formattate o poco pratiche</small>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="category-card card h-100 text-center p-3 {{ old('category') === 'content' ? 'selected' : '' }}">
                        <input type="radio" name="category" value="content" {{ old('category') === 'content' ? 'checked' : '' }}>
                        <div class="category-icon">📝</div>
                        <div class="fw-bold">Contenuto</div>
                        <small class="text-muted">Contenuti incompleti o sbagliati</small>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="category-card card h-100 text-center p-3 {{ old('category', 'other') === 'other' ? 'selected' : '' }}">
                        <input type="radio" name="category" value="other" {{ old('category', 'other') === 'other' ? 'checked' : '' }}>
                        <div class="category-icon">❓</div>
                        <div class="fw-bold">Altro</div>
                        <small class="text-muted">Qualsiasi altra segnalazione</small>
                    </label>
                </div>
            </div>
        </div>

        {{-- Link pagine --}}
        <div class="mb-4">
            <label for="page_urls" class="form-label fw-bold">Pagine coinvolte</label>
            <textarea 
                name="page_urls" 
                id="page_urls" 
                class="form-control" 
                rows="3" 
                placeholder="Inserisci i link delle pagine coinvolte (uno per riga)"
            >{{ old('page_urls', $referrerUrl) }}</textarea>
            <div class="form-text">Puoi inserire più link, uno per ogni riga.</div>
        </div>

        {{-- Descrizione --}}
        <div class="mb-4">
            <label for="description" class="form-label fw-bold">Descrizione del problema *</label>
            <textarea 
                name="description" 
                id="description" 
                class="form-control" 
                rows="5" 
                placeholder="Descrivi il problema nel dettaglio..."
                required
                minlength="10"
            >{{ old('description') }}</textarea>
            <div class="form-text">Minimo 10 caratteri. Sii il più dettagliato possibile.</div>
        </div>

        {{-- Info utente --}}
        <div class="mb-4 p-3 bg-body-secondary rounded">
            @auth
                <small class="text-muted">
                    <i class="bi bi-person-check"></i> Segnalazione da: <strong>{{ Auth::user()->name }}</strong>
                </small>
            @else
                <small class="text-muted">
                    <i class="bi bi-person-x"></i> Stai inviando una segnalazione anonima. 
                    <a href="{{ route('login') }}">Accedi</a> per associare la segnalazione al tuo account.
                </small>
            @endauth
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">
            📤 Invia segnalazione
        </button>
    </form>
</div>
@endsection

@section('script')
<script>
document.querySelectorAll('.category-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
    });
});
</script>
@endsection

