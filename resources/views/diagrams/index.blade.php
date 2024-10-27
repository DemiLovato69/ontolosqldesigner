@extends('layouts.app')

@section('content')
    <diagram-list :diagrams='{{ $diagrams }}'></diagram-list>
@endsection
