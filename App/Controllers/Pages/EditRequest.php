<?php
namespace PressDo\App\Controllers\Pages;

use PressDo\App\Models\{Document,EditRequest as ER,Member,ACL};
use PressDo\App\Core\{Controller, Response};
use PressDo\App\Controllers\ACL as WikiACL;
use PressDo\App\Helpers\{Languages,Namespaces,Config};
use PressDo\App\Services\Document\DocumentConflictException;

class EditRequest extends Controller
{
    public string $content;

    public function makeData(): array
    {
        $action = $this->uri_data->action ?? '';
        $changesStatus = in_array($action, ['accept', 'close', 'reopen'], true);
        if ($changesStatus && !$this->request->isMethod('POST')) {
            Response::methodNotAllowed('POST');
        }

        $error = [];
        $erdata = ER::get($this->uri_data->title);
        if ($erdata === null) {
            $error = ['code' => 'editrequest_not_found'];
        } else {
            $ACL = new WikiACL($erdata['namespace'], $erdata['title'], $erdata['document'], $this->session, $error);
            $ACL->check('read');
        }

        if ($error !== []) {
            return [
                'view_name' => 'error',
                'title' => Languages::get('page', 'error'),
                'data' => $error,
            ];
        }

        $title = (self::forceShowNamespace($erdata['namespace'], $erdata['title']) ? $erdata['namespace'].':' : '').$erdata['title'];
        $baserevdat = Document::load($erdata['document'], $erdata['baserev']);

        $contributor = self::getUserIdfData($erdata['contributor_m'], $erdata['contributor_i']);
        $executor = self::getUserIdfData($erdata['executor_m'], $erdata['executor_i']);

        $content = self::readSyntax($erdata['content'], [
            'title' => self::makeTitle($erdata['namespace'], $erdata['title']),
            'thread' => false
        ]);

        $actingUser = $this->session['uuid'] ?? null;
        $isContributor = is_string($actingUser) && $actingUser === $contributor['uuid'];
        $conflictMessage = Languages::get('msg', 'err_edit_request_conflict');

        if ($action === 'edit' && $erdata['status'] === 'open') {
            $ACL->check('edit_request');
            if (!$isContributor || $error !== []) {
                return [
                    'view_name' => 'error',
                    'title' => Languages::get('page', 'error'),
                    'data' => $error ?: ['code' => 'err_not_self_modify'],
                ];
            }

            // Edit Submission
            if ($this->request->isMethod('POST') && self::editFormProcess($this, 'eretoken')) {
                try {
                    ER::modify(
                        $this->uri_data->title,
                        $this->content,
                        $this->request->postString('comment'),
                        mb_strlen($this->content) - mb_strlen((string) ($baserevdat['content'] ?? '')),
                    );
                } catch (DocumentConflictException) {
                    Response::text($conflictMessage, 409);
                }
                
                Header('Location: /edit_request/'.$this->uri_data->title);
                unset($this->session['eretoken']);
                $_SESSION = $this->session;
                exit;
            }

            $page = [
                'view_name' => 'edit_edit_request',
                'title' => $title,
                'data' => [
                    'body' => [
                        'baserev' => $erdata['baserev'],
                        'raw' => $_POST['content'] ?? $erdata['content'],
                        'comment' => $erdata['comment']
                    ],
                    'document' => [
                        'namespace' => $erdata['namespace'],
                        'title' => $erdata['title'],
                        'forceShowNamespace' => self::forceShowNamespace($erdata['namespace'], $erdata['title'])
                    ],
                    'user' => $erdata['namespace'] == Namespaces::user(),
                    'token' => self::rand(64)
                ]
            ];
    
            $this->session['raw'] = $erdata['content'];
            $this->session['eretoken'] = $page['data']['token'];
            return $page;
        }

        $ACL->check('edit');
        $canEditDocument = $error === [];
        $isAdmin = in_array('admin', $ACL->perms, true);
        $isOpen = $erdata['status'] === 'open';
        $canAccept = $isOpen && $canEditDocument;
        $canClose = $isOpen && ($isContributor || $canEditDocument);
        $canLock = $canEditDocument && $isAdmin;
        $canReopen = ($erdata['status'] === 'close' && ($isContributor || $canEditDocument))
            || ($erdata['status'] === 'locked' && $canLock);

        if ($changesStatus) {
            $authorized = match ($action) {
                'accept' => $canEditDocument,
                'close' => $isContributor || $canEditDocument,
                'reopen' => $erdata['status'] === 'locked' ? $canLock : ($isContributor || $canEditDocument),
            };
            if (!$authorized) {
                Response::text('Forbidden', 403);
            }

            $validState = match ($action) {
                'accept' => $canAccept,
                'close' => $canClose,
                'reopen' => $canReopen,
            };
            if (!$validState) {
                Response::text($conflictMessage, 409);
            }

            try {
                if ($action === 'reopen') {
                    ER::reopen($this->uri_data->title, $erdata['status']);
                } else {
                    $member = !empty($this->session['member']) ? $actingUser : null;
                    $ip = $member === null ? ($actingUser ?? Member::getIpUuid($this->session['ip'])) : null;
                    if ($action === 'close') {
                        ER::close(
                            $this->uri_data->title,
                            $this->request->postString('reason'),
                            $member,
                            $ip,
                            $canLock && $this->request->hasPost('lock'),
                        );
                    } else {
                        ER::accept($this->uri_data->title, $erdata, $member, $ip);
                    }
                }
            } catch (DocumentConflictException) {
                Response::text($conflictMessage, 409);
            }

            Response::redirect('/edit_request/' . rawurlencode($this->uri_data->title), 303);
        }

        $page = [
            'view_name' => 'edit_request',
            'title' => $title,
            'data' => [
                'document' => [
                    'namespace' => $erdata['namespace'],
                    'title' => $erdata['title'],
                    'forceShowNamespace' => self::forceShowNamespace($erdata['namespace'], $erdata['title'])
                ],
                'body' => [
                    'slug' => $this->uri_data->title,
                    'status' => $erdata['status'],
                    'comment' => $erdata['comment'],
                    'baserev' => $erdata['baserev'],
                    'contributor_m' => $contributor['member'],
                    'contributor_i' => $contributor['ip'],
                    'contributor_uuid' => $contributor['uuid'],
                    'contributor_admin' => $contributor['admin'],
                    'contributor_style' => $contributor['style'],
                    'executor_m' => $executor['member'],
                    'executor_i' => $executor['ip'],
                    'executor_uuid' => $executor['uuid'],
                    'executor_admin' => $executor['admin'],
                    'executor_style' => $executor['style'],
                    'reason' => $erdata['reason'],
                    'lengthdiff' => $erdata['count'],
                    'createdtime' => $erdata['datetime'],
                    'lastedit' => $erdata['lastedit'],
                    'acceptrev' => $erdata['acceptrev']
                ],
                'can_accept' => $canAccept,
                'can_close' => $canClose,
                'can_lock' => $canLock,
                'can_reopen' => $canReopen,
                'can_edit' => $isOpen && $isContributor,
                'diff' => self::loadDiff(
                    $baserevdat['content'],
                    $erdata['content'],
                    Languages::get('page', 'edit_request').' '.$this->uri_data->title
                ),
                'editor_comment' => $content->editorComment,
                'preview' => $content->html,
                'user' => $erdata['namespace'] == Namespaces::user()
            //   'customData' => $ad_set
            ]
        ];

        return $page;
    }

    private function getUserIdfData(?string $uuid, ?string $ip): array
    {
        if (empty($uuid) && empty($ip)) {
            return ['member' => null, 'ip' => null, 'uuid' => null, 'admin' => false, 'style' => ''];
        }

        $mperms = [];
        if (!empty($ip)) {
            $Cuuid = Document::bin2uuid($ip);
            $ip = Member::ipLookup($Cuuid);
            $member = null;
        } elseif(!empty($uuid)) {
            $Cuuid = Document::bin2uuid($uuid);
            $member = Member::lookup($Cuuid);
            $ip = null;

            if (empty($mperms[$Cuuid])) {
                $mperms[$Cuuid] = [];
                ACL::getAccountPerms($Cuuid, $member, $mperms[$Cuuid]);
            }
        }
        $groups = array_map(fn($r) => $r[0]['groupid'], ACL::getUserAclgroups($ip, $member !== null ? $Cuuid : null));
        $style = implode(' ', array_map(fn($r) => Config::get('aclgroup.'.$r.'.style', ' '), $groups));
        return [
            'member' => $member,
            'ip' => $ip,
            'uuid' => $Cuuid,
            'admin' => in_array('admin', $mperms[$Cuuid] ?? [], true),
            'style' => $style
        ];
    }
}
