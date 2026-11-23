<?php

namespace PressDo\App\Controllers\Pages\admin;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Helpers\{Languages, Config};
use PressDo\App\Http\Security\PermissionGrantPolicy;
use PressDo\App\Models\Member;

class Grant extends Controller
{
    private const PERMISSIONS = [
        'delete_thread', 'admin', 'update_thread_status', 'nsacl', 'hide_thread_comment',
        'grant', 'login_history', 'update_thread_document', 'update_thread_topic',
        'hide_revision', 'mark_troll_revision', 'aclgroup', 'api_access', 'no_force_captcha',
        'batch_revert', 'hide_document_history_log', 'hideip', 'config', 'developer',
    ];

    public function makeData(): array
    {
        $actorId = $this->session['uuid'] ?? null;
        $actor = !empty($this->session['member']) && is_string($actorId) ? Member::specialPerms($actorId) : [];
        $isPost = $this->request->isMethod('POST');
        if (!in_array('grant', $actor, true) && !in_array('developer', $actor, true)) {
            if ($isPost) {
                Response::text('Forbidden', 403);
            }

            return [
                'view_name' => 'error',
                'title' => Languages::get('page', 'error'),
                'data' => ['code' => 'no_permission'],
            ];
        }

        $page = [
            'view_name' => 'grant',
            'title' => Languages::get('page', 'grant'),
            'data' => [],
        ];
        $username = $this->request->queryString('username');
        if ($username === '') {
            return $page;
        }
        $member = Member::exist($username);
        if ($member === false) {
            $page['data']['errbox'] = 'err_wrong_username';
            return $page;
        }

        $stored = (string) ($member['perm'] ?? '');
        $current = array_values(array_filter(explode(',', $stored), 'strlen'));
        $catalog = Config::get('wiki.permissions');
        $catalog = is_array($catalog) ? array_values(array_filter($catalog, 'is_string')) : self::PERMISSIONS;
        $manageable = PermissionGrantPolicy::manageable($actor, $current, $catalog, $actorId === $member['uuid']);
        $page['data'] = [
            'username' => $member['username'],
            'perms' => $manageable,
            'have' => array_intersect($current, $manageable),
        ];
        if ($manageable === []) {
            if ($isPost) {
                Response::text('Forbidden', 403);
            }

            $page['data']['errbox'] = 'no_permission';
            return $page;
        }
        if (!$isPost) {
            return $page;
        }

        $selected = $this->request->postData()['permissions'] ?? [];
        if ($this->request->postString('grant_permissions') !== '1' || !is_array($selected) || !array_is_list($selected)) {
            Response::text('Invalid permission submission.', 422);
        }
        foreach ($selected as $permission) {
            if (!is_string($permission) || !in_array($permission, $manageable, true)) {
                Response::text('Forbidden', 403);
            }
        }

        $desired = array_values(array_unique(array_merge(array_diff($current, $manageable), $selected)));
        $added = array_diff($desired, $current);
        $removed = array_diff($current, $desired);
        if ($added === [] && $removed === []) {
            $page['data']['errbox'] = 'no_change';
            return $page;
        }

        $record = implode(' ', [
            ...array_map(static fn (string $permission): string => '+' . $permission, $added),
            ...array_map(static fn (string $permission): string => '-' . $permission, $removed),
        ]);
        if (!Member::grantPermissions($actorId, $member['uuid'], $stored, $desired, $record)) {
            Response::text('Permissions changed. Reload and try again.', 409);
        }

        Response::redirect('/admin/grant?username=' . rawurlencode($member['username']), 303);
    }
}
