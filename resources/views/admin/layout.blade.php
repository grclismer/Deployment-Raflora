@php
// Backward-compatible wrapper: keep `@extends('admin.layout')` working
@endphp

<x-admin-layout title="@yield('title', 'Admin Dashboard')">
    @yield('content')
</x-admin-layout>
