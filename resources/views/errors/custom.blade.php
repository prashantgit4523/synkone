@extends('errors.minimal')

@section('title', $title ?? 'Not Found')
@section('code', $code ?? '404')
@section('message', $message ?? 'Not Found')
