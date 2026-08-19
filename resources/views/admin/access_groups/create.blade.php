@extends('layouts.app')

@section('title', 'Nuovo Gruppo di Accesso')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-plus"></i> Crea Nuovo Gruppo di Accesso</span>
                    <a href="{{ route('admin.access_groups') }}" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla lista
                    </a>
                </div>

                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.access_groups.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome Gruppo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" placeholder="Es: Artefici, Bibliotecari, Cultisti" required>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug (usato nei tag markdown) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-muted">#access-</span>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                       id="slug" name="slug" value="{{ old('slug') }}" placeholder="es: artefici o cavalieriDelDrago" required>
                            </div>
                            <small class="form-text text-muted">Formato camelCase (es: <code>cavalieriDelDrago</code>). Nessun trattino "-" o underscore "_".</small>
                        </div>

                        <div class="mb-3">
                            <label for="color" class="form-label">Colore Badge / Bordo</label>
                            <div class="input-group" style="max-width: 250px;">
                                <input type="color" class="form-control form-control-color bg-dark border-secondary" 
                                       id="colorPicker" value="{{ old('color', '#a83232') }}" title="Scegli un colore">
                                <input type="text" class="form-control @error('color') is-invalid @enderror" 
                                       id="color" name="color" value="{{ old('color', '#a83232') }}" placeholder="#a83232">
                            </div>
                            <small class="form-text text-muted">Formato esadecimale (es: #a83232).</small>
                        </div>

                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Gruppo Padre (Opzionale)</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                                <option value="">-- Nessun padre (Gruppo di primo livello) --</option>
                                @foreach($parents as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }} ({{ $parent->slug }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Chi fa parte di questo gruppo vedrà anche le informazioni filtrate per il gruppo padre.</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione (Opzionale)</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3" placeholder="Breve descrizione dell'organizzazione o fazione...">{{ old('description') }}</textarea>
                        </div>

                        <hr>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-lg"></i> Salva Gruppo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const nameInput = document.getElementById('name');
        const slugInput = document.getElementById('slug');
        const colorPicker = document.getElementById('colorPicker');
        const colorInput = document.getElementById('color');

        if (colorPicker && colorInput) {
            colorPicker.addEventListener('input', function () {
                colorInput.value = colorPicker.value;
            });
            colorInput.addEventListener('input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(colorInput.value)) {
                    colorPicker.value = colorInput.value;
                }
            });
        }

        if (nameInput && slugInput) {
            nameInput.addEventListener('input', function () {
                if (!slugInput.dataset.manual) {
                    slugInput.value = nameInput.value
                        .trim()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // remove accents
                        .replace(/[^a-zA-Z0-9\s_-]/g, '') // remove special chars
                        .replace(/[-_\s]+(.)?/g, (_, c) => c ? c.toUpperCase() : '') // camelCase after space/dash
                        .replace(/^[A-Z]/, c => c.toLowerCase()); // ensure lower camelCase
                }
            });
            slugInput.addEventListener('input', function () {
                slugInput.dataset.manual = 'true';
            });
        }
    });
</script>
@endsection
