<?php

namespace BinanceBot;

class EirSMS implements ISMSGateway
{
    /** @var string */
    private $cookie_file = "cookie.txt";

    /**
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * @param mixed $message
     * @return void
     */
    #[\Override]
    public function send($message)
    {
        $agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/62.0.3202.62 Safari/537.36';
        $this->request(
            "https://my.eir.ie/login",
            array(
                CURLOPT_USERAGENT => $agent,
            )
        );

        $this->request(
            "https://my.eir.ie/rest/brand/3/portalUser/authenticate",
            array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => "{\"emailAddress\":\"" . BinanceBotSettings::getInstance()->smsGatewayUsername
                    . "\",\"password\":\"" . BinanceBotSettings::getInstance()->smsGatewayPassword . "\"}",
                CURLOPT_HTTPHEADER => array('Content-Type:application/json'),
            )
        );

        $this->request(
            "https://my.eir.ie/mobile/webtext/mobileNumbers/"
                . BinanceBotSettings::getInstance()->smsGatewayRecipientNumber . "/messages?ts=" . time(),
            array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => "{\"content\":\"" . $message . "\",\"recipients\":[\""
                    . BinanceBotSettings::getInstance()->smsGatewayRecipientNumber . "\"]}",
                CURLOPT_HTTPHEADER => array('Content-Type:application/json'),
            )
        );
    }

    /**
     * @param string $url
     * @param array<int, mixed> $options
     * @return string|bool
     */
    private function request($url, $options)
    {
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        foreach ($options as $option => $value) {
            curl_setopt($ch, $option, $value);
        }
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }
}
