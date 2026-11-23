<?php
namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Models\Member;
use PressDo\App\Core\{Controller,Response};
use PressDo\App\Helpers\{Languages,Config};
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class ActivateOtp extends Controller
{
    public function makeData()
    {
        if(!$this->session['member']){
            Header('Location: /member/login?redirect='.$_SERVER['REQUEST_URI']);
            exit;
        }

        $user = Member::getUserInfo($this->session['uuid']);

        if($user['totp_secret'] !== null){
            $error = ['code' => 'already_activated_otp'];
            $page = [
                'view_name' => 'error',
                'title' => Languages::get('page')['error'],
                'data' => $error
            ];
            return $page;
        }

        $secret = $this->session['otp_secret'] ?? null;
        $errmsg = null;
        if ($this->request->isMethod('POST')) {
            $pin = $this->request->postString('pin');
            if (is_string($secret) && preg_match('/\A[0-9]{6}\z/', $pin) === 1
                && TOTP::createFromSecret($secret)->verify($pin)
                && Member::setTotp($this->session['uuid'], $secret)) {
                unset($this->session['otp_secret']);
                $_SESSION = $this->session;
                Response::redirect('/member/mypage', 303);
            }
            $errmsg = 'err_invalid_pin';
        }

        $this->session['otp_secret'] ??= trim(Base32::encodeUpper(random_bytes(10)), '=');

        $writer = new PngWriter();
        $qrCode = new QrCode(
            data: 'otpauth://totp/'
                .urlencode(Config::get('wiki.site_name')).':'
                .$this->session['member']['username']
                .'?secret='.$this->session['otp_secret'].'&period=30&digits=6&algorithm=SHA1&issuer='.urlencode(Config::get('wiki.site_name')),
            size: 244,
            margin: 0
        );
        $qr_result = $writer->write($qrCode);

        $page = [
            'view_name' => 'activate_otp',
            'title' => Languages::get('page')['activate_otp'],
            'data' => [
                'error' => $errmsg,
                'qrcode' => $qr_result->getDataUri()
            ],
            'menus' => [],
            'customData' => []
        ];

        return $page;
    }
}