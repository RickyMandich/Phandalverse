@extends('layouts.app')

@section('title', 'Modifica Gruppo di Accesso')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-lock"></i> Modifica Gruppo: {{ $group->name }}</span>
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

                    <form method="POST" action="{{ route('admin.access_groups.update', $group) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="name" class="form-label">Nome Gruppo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $group->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug (usato nei tag markdown) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-muted">#access:</span>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                       id="slug" name="slug" value="{{ old('slug', $group->slug) }}" required>
                            </div>
                            <small class="form-text text-muted">Solo lettere minuscole, numeri e trattini (kebab-case). Nessuna virgola o pipe.</small>
                        </div>

                        <div class="mb-3">
                            <label for="color" class="form-label">Colore Badge / Bordo</label>
                            <div class="input-group" style="max-width: 250px;">
                                <input type="color" class="form-control form-control-color bg-dark border-secondary" 
                                       id="colorPicker" value="{{ old('color', $group->color ?? '#a83232') }}" title="Scegli un colore">
                                <input type="text" class="form-control @error('color') is-invalid @enderror" 
                                       id="color" name="color" value="{{ old('color', $group->color ?? '#a83232') }}" placeholder="#a83232">
                            </div>
                            <small class="form-text text-muted">Formato esadecimale (es: #a83232).</small>
                        </div>

                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Gruppo Padre (Opzionale)</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                                <option value="">-- Nessun padre (Gruppo di primo livello) --</option>
                                @foreach($parents as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id', $group->parent_id) == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }} ({{ $parent->slug }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Chi fa parte di questo gruppo vedrà anche le informazioni filtrate per il gruppo padre.</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione (Opzionale)</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description', $group->description) }}</textarea>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-lg"></i> Salva Modifiche
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Zona Eliminazione -->
            <div class="card border-danger mt-4">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> Zona Pericolosa
                </div>
                <div class="card-body">
                    <p class="mb-2">Eliminando questo gruppo, gli utenti assegnati perderanno l'accesso associato. I gruppi figli diventeranno gruppi di primo livello.</p>
                    <form action="{{ route('admin.access_groups.delete', $group) }}" method="POST"
                          onsubmit="return confirm('Sei sicuro di voler eliminare questo gruppo? Questa operazione è irreversibile.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Elimina Gruppo
                        </button>
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
    });
</script>
@endsection
