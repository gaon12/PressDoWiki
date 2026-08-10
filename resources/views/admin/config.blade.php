@php($settingsPage = $wiki['page']['data'])

@if ($settingsPage['saved'])
    <div class="a">
        <strong>저장 완료</strong>
        <span>사이트 설정을 안전하게 저장했습니다.</span>
    </div>
@endif

@if (isset($settingsPage['errors']['_form']))
    <div class="a e">
        <strong>저장 실패</strong>
        <span>{{ $settingsPage['errors']['_form'] }}</span>
    </div>
@endif

<form method="post">
    <input type="hidden" name="token" value="{{ $settingsPage['token'] }}">

    @foreach ($settingsPage['sections'] as $section => $settings)
        <section class="c">
            <h2>{{ $section }}</h2>

            @foreach ($settings as $setting)
                <div class="comment">
                    <label for="setting-{{ $loop->parent->index }}-{{ $loop->index }}">
                        <strong>{{ $setting['label'] }}</strong>
                        <small>{{ $setting['key'] }}</small>
                    </label>
                    <p>{{ $setting['description'] }}</p>

                    @if ($setting['type'] === 'textarea')
                        <textarea
                            id="setting-{{ $loop->parent->index }}-{{ $loop->index }}"
                            class="edit-form"
                            name="settings[{{ $setting['key'] }}]"
                            rows="5"
                        >{{ $setting['value'] }}</textarea>
                    @elseif ($setting['type'] === 'choice')
                        <select
                            id="setting-{{ $loop->parent->index }}-{{ $loop->index }}"
                            name="settings[{{ $setting['key'] }}]"
                        >
                            @foreach ($setting['choices'] as $choice)
                                <option value="{{ $choice }}" @selected($choice === $setting['value'])>{{ $choice }}</option>
                            @endforeach
                        </select>
                    @elseif ($setting['type'] === 'boolean')
                        <select
                            id="setting-{{ $loop->parent->index }}-{{ $loop->index }}"
                            name="settings[{{ $setting['key'] }}]"
                        >
                            <option value="1" @selected($setting['value'] === '1')>사용</option>
                            <option value="0" @selected($setting['value'] !== '1')>사용 안 함</option>
                        </select>
                    @else
                        <input
                            id="setting-{{ $loop->parent->index }}-{{ $loop->index }}"
                            type="{{ $setting['type'] === 'url' ? 'url' : 'text' }}"
                            name="settings[{{ $setting['key'] }}]"
                            value="{{ $setting['value'] }}"
                        >
                    @endif

                    @if ($setting['error'] !== null)
                        <p class="e">{{ $setting['error'] }}</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endforeach

    <div class="btn-area">
        <button class="btn-blue pressdo-btn" type="submit">설정 저장</button>
    </div>
</form>
