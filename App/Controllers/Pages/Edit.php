<?php
namespace PressDo\App\Controllers\Pages;

use PressDo\App\Models\{Document,Member};
use PressDo\App\Core\Controller;
use PressDo\App\Controllers\ACL as WikiACL;
use PressDo\App\Helpers\{Languages,Namespaces};
use PressDo\App\Services\Document\DocumentConflictException;

class Edit extends Controller
{
    public string $content;

    public function makeData(): array
    {
        [$namespace, $title] = self::parseTitle($this->uri_data->title);
        $error = [];
        $uuid = Document::getUuid($namespace, $title);
        $formReceived = isset($_POST['token']) && isset($_POST['content']);

        $ACL = new WikiACL($namespace, $title, $uuid, $this->session, $error);
        $ACL->check('read');

        if ($error['code'] == 'permission_read'){
            return [
                'view_name' => 'error',
                'title' => Languages::get('page', 'error'),
                'data' => $error
            ];
        }
        $ACL->check('edit');

        // 편집권한이 없으면 편집 요청 권한 확인
        if ($error['code'] == 'permission_edit') {
            $error1 = $error;
            $ACL->check('edit_request');
            if ($error['code'] !== 'permission_edit_request') {
                Header('Location: /new_edit_request/'.$this->uri_data->title);
                exit;
            } else
                $this->error = $error1; // overwrite with edit-error
        }

        if ($formReceived && ($namespace == Namespaces::file() || $namespace == Namespaces::user()))
            $this->error = self::makeErrorBox('invalid_namespace');

        if ($formReceived && !self::validateCaptcha($_POST[$this->api_config['captcha_token_name'] ?? ''] ?? null))
            $this->error = self::makeErrorBox('captcha_failed');

        // Edit Submission
        if ($formReceived && self::editFormProcess($this, 'edittoken') && empty($this->error)) {
            // Approve Edit
            $member = $this->session['member'] ? $this->session['uuid'] : null;
            $ip = !$member ? ($this->session['uuid'] ?? Member::getIpUuid($this->session['ip'])) : null;
            if (!$member && !$this->session['uuid']) {
                $this->session['uuid'] = $ip;
            }

            $baseState = $this->session['edit_base_state'] ?? null;
            $baseUuid = $this->session['edit_base_uuid'] ?? null;
            $baseRevision = $this->session['baserev'] ?? null;

            try {
                if (!is_string($baseState) || !is_int($baseRevision)) {
                    throw new DocumentConflictException('The editor base state is missing.');
                }

                if ($baseState === 'missing') {
                    $uuid = Document::createWithContent(
                        $namespace,
                        $title,
                        $this->content,
                        $_POST['comment'],
                        $member,
                        $ip,
                    );
                } elseif (!is_string($baseUuid)) {
                    throw new DocumentConflictException('The editor base document ID is missing.');
                } elseif ($baseState === 'placeholder') {
                    Document::initializeWithContent(
                        $baseUuid,
                        $namespace,
                        $title,
                        $this->content,
                        $_POST['comment'],
                        $member,
                        $ip,
                    );
                    $uuid = $baseUuid;
                } elseif ($baseState === 'deleted') {
                    Document::recreateWithContent(
                        $baseUuid,
                        $namespace,
                        $title,
                        $this->content,
                        $_POST['comment'],
                        $member,
                        $ip,
                        $baseRevision,
                    );
                    $uuid = $baseUuid;
                } elseif ($baseState === 'normal') {
                    Document::save(
                        $baseUuid,
                        $namespace,
                        $title,
                        $this->content,
                        $_POST['comment'],
                        $member,
                        $ip,
                        $baseRevision,
                        iconv_strlen($this->session['raw']),
                    );
                    $uuid = $baseUuid;
                } else {
                    throw new DocumentConflictException('The editor base state is invalid.');
                }

                Header('Location: /w/'.$this->uri_data->title);
                unset(
                    $this->session['edittoken'],
                    $this->session['edit_base_state'],
                    $this->session['edit_base_uuid'],
                    $this->session['baserev'],
                    $this->session['raw'],
                );
                $_SESSION = $this->session;
                exit;
            } catch (DocumentConflictException) {
                $this->error = self::makeErrorBox('err_edit_conflict');
            }
        }

        $doc = $uuid ? Document::load($uuid) : null;
        if (!$uuid) {
            $baseState = 'missing';
            $baseRevision = 0;
            $rawContent = '';
        } elseif ($doc === null) {
            $baseState = 'placeholder';
            $baseRevision = 0;
            $rawContent = '';
        } elseif ($doc['status'] === 'delete') {
            $baseState = 'deleted';
            $baseRevision = Document::getVersion($uuid);
            $rawContent = '';
        } else {
            $baseState = 'normal';
            $baseRevision = Document::getVersion($uuid);
            $rawContent = is_string($doc['content']) ? $doc['content'] : '';
        }

        $this->session['edit_base_state'] = $baseState;
        $this->session['edit_base_uuid'] = $uuid ?: null;
        $this->session['baserev'] = $baseRevision;
        $this->session['raw'] = $rawContent;
        $section = $_GET['section'] ?? null;

        $page = [
            'view_name' => 'edit',
            'title' => $this->uri_data->title,
            'data' => [
                'body' => [
                    'baserev' => $this->session['baserev'],
                    'section' => $section,
                    'raw' => $_POST['content'] ?? $this->session['raw']
                ],
                'document' => [
                    'namespace' => $namespace,
                    'title' => $title,
                    'forceShowNamespace' => self::forceShowNamespace($namespace, $title)
                ],
                'user' => $namespace == Namespaces::user(),
                'token' => self::rand(64)
            //   'customData' => $ad_set
            ]
        ];

        $this->session['edittoken'] = $page['data']['token'];
        return $page;
    }
}
