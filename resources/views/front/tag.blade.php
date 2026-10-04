@extends('layouts.app')
@section('content')
<x-archive :posts="$posts" :title="'#'.$tag->name" :subtitle="'Latest stories tagged '.$tag->name" />
@endsection
