@extends('layouts.app')

@section('head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="{{ mix('css/bootstrap.css', 'assets') }}">
    <style lang="css">
        .alert.alert-style {
            margin-top: 0;
        }
    </style>
@endsection

@section('body')
    <script type="text/javascript" src="{{ mix('js/bootstrap.js', 'assets') }}"></script>
@endsection
