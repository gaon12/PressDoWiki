<?php
namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Models\Member;
use PressDo\App\Core\{Controller,Response};
use PressDo\App\Helpers\{Languages,Config};
use OTPHP\TOTP;

class DeactivateOtp extends Controller
{
    public function makeData()
    {
        if(!$this->session['member']){
            Header('Location: /member/login?redirect='.$_SERVER['REQUEST_URI']);
            exit;
        }

        $user = Member::getUserInfo($this->session['uuid']);

        if(empty($user['totp_secret'])){
            $error = ['code' => 'already_activated_otp'];
            $page = [
                'view_name' => 'error',
                'title' => Languages::get('page')['error'],
                'data' => $error
            ];
            return $page;
        }

        $errmsg = null;
        if ($this->request->isMethod('POST')) {
            $pin = $this->request->postString('pin');
            $secret = $user['totp_secret'];
            if (preg_match('/\A[0-9]{6}\z/', $pin) === 1
                && TOTP::createFromSecret($secret)->verify($pin)
                && Member::removeTotp($this->session['uuid'], $secret)) {
                unset($this->session['otp_secret']);
                $_SESSION = $this->session;
                Response::redirect('/member/mypage', 303);
            }
            $errmsg = 'err_invalid_pin';
        }

        $page = [
            'view_name' => 'deactivate_otp',
            'title' => Languages::get('page')['deactivate_otp'],
            'data' => [
                'error' => $errmsg
            ],
            'menus' => [],
            'customData' => []
        ];

        return $page;
    }
}