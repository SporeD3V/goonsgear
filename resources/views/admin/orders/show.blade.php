@extends('admin.layout')

@section('content')
    <livewire:admin.order-detail :order-id="$order->id" />
@endsection
