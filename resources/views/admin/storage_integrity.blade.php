@php($report = $wiki['page']['data'])

<section class="c">
    <h2>파일 저장소 무결성</h2>
    <p>DB에 등록된 파일과 실제 객체 저장소를 읽기 전용으로 대조합니다. 한 번에 {{ $report['limit'] }}개만 검사합니다.</p>

    @if ($report['error'] !== null)
        <div class="a e">
            <strong>검사 실패</strong>
            <span>{{ $report['error'] }}</span>
        </div>
    @else
        <p>전체 {{ $report['total'] }}개 중 {{ count($report['items']) }}개를 검사했습니다.</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>상태</th>
                        <th>파일 문서</th>
                        <th>객체 키</th>
                        <th>SHA-256</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['items'] as $item)
                        <tr data-integrity-state="{{ $item['state'] }}">
                            <td>{{ $item['state_label'] }}</td>
                            <td><a href="/w/{{ rawurlencode($item['title']) }}">{{ $item['title'] }}</a></td>
                            <td><code>{{ $item['object_key'] }}</code></td>
                            <td><code>{{ $item['sha256'] }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">검사할 파일이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <nav class="btn-area" aria-label="무결성 검사 페이지">
            @if ($report['previous_offset'] !== null)
                <a class="pressdo-btn" href="/admin/storage_integrity?offset={{ $report['previous_offset'] }}">이전</a>
            @endif
            @if ($report['next_offset'] !== null)
                <a class="pressdo-btn" href="/admin/storage_integrity?offset={{ $report['next_offset'] }}">다음</a>
            @endif
        </nav>

        <hr>
        <h3>고아 객체 검토 후보</h3>
        <p>현재 객체 페이지에서 {{ $report['orphan_scanned'] }}개를 검사했습니다. DB 미참조 상태가 24시간 이상 지속된 관리 대상 객체만 표시합니다.</p>
        <p>유예 중 {{ $report['ignored_recent'] }}개, 관리 대상 외 객체 {{ $report['ignored_unmanaged'] }}개는 제외했습니다.</p>
        <table>
            <thead>
                <tr><th>객체 키</th><th>최종 수정</th><th>경과</th><th>크기</th></tr>
            </thead>
            <tbody>
                @forelse ($report['orphan_items'] as $object)
                    <tr data-orphan-candidate>
                        <td><code>{{ $object['object_key'] }}</code></td>
                        <td>{{ $object['last_modified'] }}</td>
                        <td>{{ $object['age_hours'] }}시간</td>
                        <td>{{ $object['size'] }} bytes</td>
                    </tr>
                @empty
                    <tr><td colspan="4">이 페이지에는 고아 객체 후보가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
        <nav class="btn-area" aria-label="고아 객체 검사 페이지">
            <a class="pressdo-btn" href="/admin/storage_integrity">처음부터</a>
            @if ($report['next_object_cursor'] !== null)
                <a class="pressdo-btn" href="/admin/storage_integrity?object_cursor={{ rawurlencode($report['next_object_cursor']) }}">다음 객체</a>
            @endif
        </nav>
    @endif
</section>
