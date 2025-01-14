<?php
 
use Illuminate\Support\Facades\Storage;

class PaypalIPN
{
    private $useLocalCertificate = false;

    public function useLocalCertificate()
    {
        $this->useLocalCertificate = true;
    }

    public function verify()
    {
        if (!count($_POST))
            throw new Exception('Missing POST Data');

        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = [];

        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);

            if (count($keyval) == 2) {
                if ($keyval[0] === 'payment_date') {
                    if (substr_count($keyval[1], '+') === 1)
                        $keyval[1] = str_replace('+', '%2B', $keyval[1]);
                }

                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }

        $req = 'cmd=_notify-validate';

        foreach ($myPost as $key => $value) {
            $value = urlencode($value);
            $req .= "&{$key}={$value}";
        }

        $url = (config('site.paypal_sandbox')) ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($this->useLocalCertificate)
            curl_setopt($ch, CURLOPT_CAINFO, storage_path('cacert.pem'));

        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PHP-IPN-Verification-Script',
            'Connection: Close'
        ]);

        $res = curl_exec($ch);

        if (!$res) {
            $errno = curl_errno($ch);
            $errstr = curl_error($ch);

            curl_close($ch);
            throw new Exception("cURL error: [{$errno}] {$errstr}");
        }

        curl_close($ch);

        return $res == 'VERIFIED';
    }
}
