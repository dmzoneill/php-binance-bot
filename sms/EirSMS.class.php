<?php

namespace BinanceBot;

class EirSMS implements ISMSGateway
{
   private $cookie_file = "cookie.txt";

   public function __construct()
   {

   }

   public function send( $message )
   {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://my.eir.ie/login");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.62 Safari/537.36');
      curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
      curl_exec($ch);
      curl_close($ch);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://my.eir.ie/rest/brand/3/portalUser/authenticate");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"emailAddress\":\"" . BinanceBotSettings::getInstance()->smsGatewayUsername . "\",\"password\":\"" . BinanceBotSettings::getInstance()->smsGatewayPassword . "\"}");
      curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json' ) );
      curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
      curl_exec($ch);
      curl_close($ch);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://my.eir.ie/mobile/webtext/mobileNumbers/" . BinanceBotSettings::getInstance()->smsGatewayRecipientNumber . "/messages?ts=" . time() );
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"content\":\"" . $message . "\",\"recipients\":[\"" . BinanceBotSettings::getInstance()->smsGatewayRecipientNumber . "\"]}");
      curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json' ) );
      curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
      $output = curl_exec($ch);
      curl_close($ch);
   }
}
