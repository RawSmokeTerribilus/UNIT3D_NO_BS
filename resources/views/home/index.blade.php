@extends('layout.with-main')

@section('page', 'page__home')

@section('main')

    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 25px; flex-wrap: wrap;">
        
        <img src="{{ asset('img/NOBS_landscape.png') }}?v=2" alt="Banner 1" style="max-width: 100%; border-radius: 8px; box-shadow: 0 0 10px #ff00ff;">
        
        </div>
    @foreach ($blocks as $block)
        @switch($block)
            @case('news')
                @include('blocks.news')

                @break
            @case('chat')
                @include('blocks.chat')
                @vite('resources/js/unit3d/chat.js')

                @break
            @case('featured')
                @include('blocks.featured')

                @break
            @case('random_media')
                @livewire('random-media')

                @break
            @case('poll')
                @include('blocks.poll')

                @break
            @case('top_torrents')
                @livewire('top-torrents')

                @break
            @case('top_users')
                @livewire('top-users')

                @break
            @case('latest_topics')
                @include('blocks.latest-topics')

                @break
            @case('latest_posts')
                @include('blocks.latest-posts')

                @break
            @case('latest_comments')
                @include('blocks.latest-comments')

                @break
            @case('online')
                @include('blocks.online')

                @break
        @endswitch
    @endforeach
@endsection