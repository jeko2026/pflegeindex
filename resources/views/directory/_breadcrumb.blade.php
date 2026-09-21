<nav aria-label="Breadcrumb">
    <ol class="breadcrumbs" style="margin:0">
        @foreach($breadcrumbs as $crumb)
            @if($loop->first)
                <li><a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a></li>
            @elseif($loop->last)
                <li aria-current="page"><span aria-hidden="true">›</span><span>{{ $crumb['name'] }}</span></li>
            @else
                <li><span aria-hidden="true">›</span><a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
