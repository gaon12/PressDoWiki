@foreach ($idset as $k => $i)<div class="context-tooltip" id="contextmenu-{{ $k }}" v="none">
    <div class="popper__backdrop"></div>
    <div class="v-popper__wrapper">
        <div class="v-popper__inner">
            <div>
                <div class="tooltip-top">
                    <div class="user-type">{{ $i[0] }}</div>
                    <div class="user-name">{{ $i[1] }}</div>
                </div>
                @if ($i[0] !== 'IP')
                <hr>
                <a href="/w/{{ rawurlencode(\PressDo\App\Helpers\Namespaces::user().':'.$i[1]) }}" role="button" class="pressdo-btn">사용자 문서</a>
                @endif
                <hr>
                <a href="/contribution/{{ $k }}/document" role="button" class="pressdo-btn">문서 기여 내역</a>
                <a href="/contribution/{{ $k }}/discuss" role="button" class="pressdo-btn">토론 기여 내역</a>
                @if (!empty($wiki['session']['admin']))
                <hr>
                <a role="button" copy-id="{{ $k }}" copy-msg="{{ $lang['msg']['uuid_copied'] }}" href="#" class="pressdo-btn">UUID 복사</a>
                <a href="/BlockHistory?query={{ $k }}&target=text" role="button" class="pressdo-btn">차단 내역 조회</a>
                <hr>
                <a role="button" href="#" class="pressdo-btn btn-darkred" danger>차단</a>
                @endif
            </div>
        </div>
    </div>
</div>@endforeach
