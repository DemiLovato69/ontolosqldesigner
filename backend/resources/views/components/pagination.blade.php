@php
    $navClass = $navClass ?? 'pagination';
    $window = \Illuminate\Pagination\UrlWindow::make($paginator);
    $elements = array_filter([
        $window['first'],
        is_array($window['slider']) ? '...' : null,
        $window['slider'],
        is_array($window['last']) ? '...' : null,
        $window['last'],
    ]);
@endphp
@if($paginator->hasPages())
<nav class="{{ $navClass }}" aria-label="Pagination">
    @if($paginator->onFirstPage())
        <span class="disabled">←</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev">←</a>
    @endif

    @foreach($elements as $element)
        @if(is_string($element))
            <span class="dots">{{ $element }}</span>
        @elseif(is_array($element))
            @foreach($element as $page => $url)
                @if($page == $paginator->currentPage())
                    <span aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next">→</a>
    @else
        <span class="disabled">→</span>
    @endif
</nav>
@endif
