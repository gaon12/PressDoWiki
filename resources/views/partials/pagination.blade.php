<div class="bt-g page-btn-area">
    @foreach ([[$phref, $lang['history']['prev'], '‹'], [$nhref, $lang['history']['next'], '›']] as [$href, $label, $arrow])
        @if ($href === null)
            <span class="pressdo-btn btn-page" role="button" aria-disabled="true">
                @if ($loop->first)<span aria-hidden="true">{{ $arrow }}</span>@endif
                <span>{{ $label }}</span>
                @if ($loop->last)<span aria-hidden="true">{{ $arrow }}</span>@endif
            </span>
        @else
            <a href="{{ $href }}" class="pressdo-btn btn-page" role="button">
                @if ($loop->first)<span aria-hidden="true">{{ $arrow }}</span>@endif
                <span>{{ $label }}</span>
                @if ($loop->last)<span aria-hidden="true">{{ $arrow }}</span>@endif
            </a>
        @endif
    @endforeach
</div>
