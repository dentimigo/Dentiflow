@extends('layouts.app')
@php($fromRecords = request('origen') === 'expedientes')
@section('title', $fromRecords ? 'Nuevo expediente' : 'Nuevo paciente')
@section('content')
    @include('pacientes._form', [
        'title' => $fromRecords ? 'Nuevo expediente' : 'Nuevo paciente',
        'subtitle' => 'Crea un expediente completo con información de contacto y datos administrativos.',
        'action' => route('pacientes.store'),
        'method' => 'POST',
        'paciente' => [],
        'submitLabel' => 'Crear expediente',
        'backUrl' => $fromRecords ? route('expedientes') : route('pacientes'),
        'backLabel' => $fromRecords ? 'Volver a expedientes' : 'Volver a pacientes',
        'origin' => $fromRecords ? 'expedientes' : null,
    ])
@endsection



