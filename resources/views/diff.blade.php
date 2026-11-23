@if (!empty($error['errbox']))<div class="a e">
    <strong>{{ $lang['msg']['error'] }}</strong>
    <span>{{ $error['message'] }}</span>
</div>@endif
@if (!empty($alert['alertbox']))<div class="a">
    <strong>{{ $lang['msg']['alert'] }}</strong>
    <span>{{ $alert['message'] }}</span>
</div>@endif
<div>
    <div id="categoryspace_top"></div>
    <div class="w">
        {!! $wiki['page']['data']['diff'] !!}
    </div>
</div>
