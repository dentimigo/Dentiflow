@extends('layouts.app')
@section('title', 'Editar paciente')
@section('content')
    @php($fromExpedientes = request('from') === 'expedientes')
    @include('pacientes._form', [
        'title' => 'Editar paciente',
        'subtitle' => 'Actualiza la información personal, de contacto y administrativa del paciente.',
        'action' => route('pacientes.update', ['paciente' => $paciente['key']]),
        'method' => 'PUT',
        'submitLabel' => 'Guardar cambios',
        'backUrl' => $fromExpedientes ? route('expedientes') : route('pacientes'),
        'backLabel' => $fromExpedientes ? 'Volver a expedientes' : 'Volver a pacientes',
    ])
@endsection



