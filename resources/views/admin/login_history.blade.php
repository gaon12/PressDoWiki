@php
    use PressDo\App\Infrastructure\Rendering\ViewTime;
@endphp

@php
    $username = $query->queryString('username');
    $data = $wiki['page']['data'];
    $base = '/admin/login_history?username=' . rawurlencode($username);
    $phref = isset($data['until']) ? $base . '&until=' . rawurlencode((string) $data['until']) : null;
    $nhref = isset($data['from']) ? $base . '&from=' . rawurlencode((string) $data['from']) : null;
@endphp
<div class="wiki-content">
    @if (!empty($wiki['page']['data']['errbox']))<div class="a e">
        <strong>{{ $lang['msg']['error'] }}</strong>
        <span>{{ $lang['msg'][$data['errbox']] ?? $data['errbox'] }}</span>
    </div>@endif
    <form method="get">
        <div data-pressdo-loginform class="c">
            <label data-pressdo-loginform for="username">{{ $lang['auth']['username'] }} : </label>
            <input data-pressdo-acl-span type="text" id="username" name="username">
        </div>
        <div class="btn-area">
            <button class="btn-blue btn" type="submit">{{ $lang['confirm'] }}</button>
        </div>
    </form>
    @if ($username !== '' && empty($data['errbox']))<form method="POST">
        <h3>{{ $username }} {{ $lang['login_history'] }}</h3><br>
        <p>{{ $lang['auth']['last_login_ua'].' : '.$wiki['page']['data']['UA'] }}</p><br>
        <p>{{ $lang['auth']['email'].' : '.$wiki['page']['data']['email'] }}</p>
        @include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
        <table class="pressdo-table">
            <thead>
                <th><strong>Date</strong></th>
                <th><strong>IP</strong></th>
            </thead>
            @foreach ($wiki['page']['data']['history'] as $h)<tr>
                <td>{!! ViewTime::absolute((int) ($h['datetime'] ?? 0)) !!}</td>
                <td>{{ $h['ip'] }}</td>
            </tr>@endforeach
        </table>
        @include('partials.pagination', ['phref' => $phref, 'nhref' => $nhref])
    </form>@endif
</div>
