<?php
namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Models\Member;
use PressDo\App\Core\{Controller, ProjectPaths, Response};
use PressDo\App\Helpers\{Languages,Config};
use lbuchs\WebAuthn\WebAuthn;

class Mypage extends Controller
{
    public function makeData(): array
    {
        if (!$this->session['member']) {
            Response::redirect('/member/login?redirect=%2Fmember%2Fmypage');
        }

        if ($this->request->isMethod('POST') && $this->request->hasPost('session_action')) {
            $this->manageSessions();
        }

        $errmsg = null;
        if (!empty($_POST['delnm'])) {
            Member::deleteUserWebauthn($this->session['uuid'], $_POST['delnm']);
        } elseif (isset($_POST['challenge']) && json_decode($_POST['challenge'], true) !== null && !empty($_POST['passkeyName'])) {
            // validate passkey enrollment
            $passkeys = Member::getUserWebauthn($this->session['uuid']);
            foreach ($passkeys as $p) {
                if ($p['name'] == $_POST['passkeyName']) {
                    $errmsg = 'authenticator_duplicate_name';
                    break;
                }
            }

            if (!$errmsg) {
                $webauthn = new WebAuthn(Config::get('wiki.site_name'), Config::get('wiki.domain'));
    
                $data = json_decode($_POST['challenge'], true);
                $challenge = $this->session['challenge'];
    
                $credential = $webauthn->processCreate(base64_decode($data['response']['clientDataJSON']), base64_decode($data['response']['attestationObject']), $challenge);
                $credential->id = $data['id'];
                $credential->credentialId = base64_encode($credential->credentialId);
                $credential->AAGUID = base64_encode($credential->AAGUID);
                
                // 등록된 키를 데이터베이스에 저장
                Member::saveUserWebauthn($this->session['uuid'], $_POST['passkeyName'], json_encode($credential));
            }                
        }

        $user = Member::getUserInfo($this->session['uuid']);
        $passkeys = Member::getUserWebauthn($this->session['uuid']);

        $dowebauthn = false;
        if ($user['totp_secret']) {
            // initilaize passkey enrollment
            $dowebauthn = true;
            $webauthn = new WebAuthn(Config::get('wiki.site_name'), Config::get('wiki.domain'));
            $passkey_id = random_bytes(20);
            $idset = [];
            
            foreach ($passkeys as $p) {
                $pd = json_decode($p['client_data'], false);
                array_push($idset, base64_decode($pd->credentialId));
            }
            
            $resp = $webauthn->getCreateArgs(
                $passkey_id, 
                $this->session['member']['username'], 
                $this->session['member']['username'],
                60 * 4,
                excludeCredentialIds: $idset
            );
            
            $challenge = $webauthn->getChallenge();
            $this->session['challenge'] = $challenge;
        }

        $skins = array_slice(scandir(ProjectPaths::public('skins')) ?: [], 2);

        $page = [
            'view_name' => 'mypage',
            'title' => Languages::get('page')['mypage'],
            'data' => [
                'error' => $errmsg,
                'skins' => $skins,
                'totp' => ($user['totp_secret']),
                'email' => $user['email'],
                'perms' => explode(',', $user['perm'] ?? ''),
                'webauthn' => $passkeys,
                'webauthn_arg' => $dowebauthn ? json_encode($resp) : null,
                'sessions' => Member::loginSessions($this->session['uuid']),
            ],
            'menus' => [],
            'customData' => []
        ];
        
        return $page;
    }

    private function manageSessions(): never
    {
        $action = $this->request->postString('session_action');
        $target = $action === 'revoke' ? $this->request->postScalarString('session_id') : null;
        if (!in_array($action, ['revoke', 'revoke_others'], true) || ($action === 'revoke' && $target === null)) {
            Response::text('Invalid session action.', 422);
        }

        if (!Member::revokeOtherLoginSessions($this->session['uuid'], $this->session['device_session'], $this->session['credential'], $target)) {
            Response::notFound();
        }

        Response::redirect('/member/mypage', 303);
    }
}
