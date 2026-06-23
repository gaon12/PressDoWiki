<?php
namespace PressDo\App\Helpers;

final class RouteControllerResolver
{
    private const PAGE_CONTROLLERS = [
        'acl' => 'ACL',
        'aclgroup' => 'Aclgroup',
        'backlink' => 'Backlink',
        'block_history' => 'BlockHistory',
        'contribution' => 'Contribution',
        'delete' => 'Delete',
        'diff' => 'Diff',
        'discuss' => 'Discuss',
        'edit' => 'Edit',
        'edit_request' => 'EditRequest',
        'history' => 'History',
        'license' => 'License',
        'longest_pages' => 'LongestPages',
        'mark_troll' => 'MarkTroll',
        'move' => 'Move',
        'needed_pages' => 'NeededPages',
        'new_edit_request' => 'NewEditRequest',
        'old_pages' => 'OldPages',
        'orphaned_pages' => 'OrphanedPages',
        'random' => 'Random',
        'random_page' => 'RandomPage',
        'raw' => 'Raw',
        'recent_changes' => 'RecentChanges',
        'recent_discuss' => 'RecentDiscuss',
        'search' => 'Search',
        'shortest_pages' => 'ShortestPages',
        'thread' => 'Thread',
        'uncategorized_pages' => 'UncategorizedPages',
        'upload' => 'Upload',
        'wiki' => 'Wiki',
    ];

    private const GROUP_CONTROLLERS = [
        'admin' => [
            'config' => 'admin\\Config',
            'grant' => 'admin\\Grant',
            'login_history' => 'admin\\LoginHistory',
        ],
        'api' => [
            'preview' => 'api\\Preview',
            'recent' => 'api\\Recent',
            'search' => 'api\\Search',
        ],
        'member' => [
            'activate_otp' => 'member\\ActivateOtp',
            'deactivate_otp' => 'member\\DeactivateOtp',
            'login' => 'member\\Login',
            'logout' => 'member\\Logout',
            'mypage' => 'member\\Mypage',
            'recover_password' => 'member\\RecoverPassword',
            'signup' => 'member\\Signup',
            'star' => 'member\\Star',
            'starred_documents' => 'member\\StarredDocuments',
            'unstar' => 'member\\Unstar',
            'withdraw' => 'member\\Withdraw',
        ],
    ];

    public static function resolve(object $uriData): ?string
    {
        $rawPage = $uriData->page ?? '';
        $rawMenu = $uriData->menu ?? '';
        $page = is_string($rawPage) ? $rawPage : '';
        $menu = is_string($rawMenu) ? $rawMenu : '';

        if (isset(self::GROUP_CONTROLLERS[$page])) {
            return self::fqcn(self::GROUP_CONTROLLERS[$page][$menu] ?? null);
        }

        return self::fqcn(self::PAGE_CONTROLLERS[$page] ?? null);
    }

    private static function fqcn(?string $relativeClassName): ?string
    {
        if ($relativeClassName === null) {
            return null;
        }

        return 'PressDo\\App\\Controllers\\Pages\\'.$relativeClassName;
    }
}
