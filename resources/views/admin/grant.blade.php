<div class="wiki-content">
    @if (!empty($wiki['page']['data']['errbox']))<div class="a e">
        <strong>{{ $lang['msg']['error'] }}</strong>
        <span>{{ $lang['msg'][$wiki['page']['data']['errbox']] }}</span>
    </div>@endif
    <form method="GET" action="{{ $request_uri }}">
        <div class="form c">
            <label for="usernameInput">Username</label>
            <input class="pressdo-form" data-pressdo-acl-span type="text" id="usernameInput" name="username">
        </div>
        <div class="btn-area">
            <button class="btn-blue pressdo-btn" type="submit">{{ $lang['confirm'] }}</button>
        </div>
    </form>
    @if (!empty($wiki['page']['data']['username']))<form method="POST" action="{{ $request_uri }}">
        @include('partials.csrf')
        <h3>{{ $wiki['page']['data']['username'] }}</h3>
        @foreach ($wiki['page']['data']['perms'] as $p)<div class="c form">
            <label for="{{ $p }}">{{ $p }}</label>
            <input type="checkbox" class="pressdo-form" id="{{ $p }}" name="permissions[]" value="{{ $p }}" @checked(in_array($p, $wiki['page']['data']['have']))>
        </div>@endforeach
        <div class="btn-area">
            <button class="btn-blue pressdo-btn" type="submit">{{ $lang['confirm'] }}</button>
        </div>
    </form>@endif
</div>
