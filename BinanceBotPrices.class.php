<?php

namespace BinanceBot;

class BinanceBotPrices
{
   private $_api = null;
   private $prices = null;
   private static $marketPrices = [];

   public function __construct( $arrs )
   {
      $this->_api = $arrs[1];

      if( $arrs[0] == true )
      {
         @unlink( BinanceBotSettings::getInstance()->cachePricesFile );
      }

      $this->update();
   }

   public function update()
   {
      if( file_exists( BinanceBotSettings::getInstance()->cachePricesFile ) && rand( 1, 3 ) != 3 )
      {
         $this->load();
         return;
      }

      $this->forceupdate();
   }

   public function forceupdate()
   {
      self::$marketPrices = [];
      $this->prices = $this->_api->prices();
      self::getBTCUSD();
      self::getBTCEUR();
      self::getBaseCurrencyUSD();
      self::getBaseCurrencyEUR();
      $this->save();
   }

   private function save()
   {
      file_put_contents( BinanceBotSettings::getInstance()->cachePricesFile, serialize( $this->prices ) );
   }

   private function load()
   {
      $this->prices = unserialize( file_get_contents( BinanceBotSettings::getInstance()->cachePricesFile ) );
   }

   public function getPrice( $symbol )
   {
      return $this->prices[ $symbol ];
   }

   public function getAllPrices()
   {
      return $this->prices;
   }

   public static function getBTCUSD()
   {
      return round(self::getMarketPrice("USD", "BTC"),2);
   }

   public static function getBTCEUR()
   {
      return round(self::getMarketPrice("EUR", "BTC"),2);
   }

   public static function getBaseCurrencyUSD()
   {
      return round(self::getMarketPrice("USD", BinanceBotSettings::getInstance()->base_currency),2);
   }

   public static function getBaseCurrencyEUR()
   {
      return round(self::getMarketPrice("EUR", BinanceBotSettings::getInstance()->base_currency),2);
   }

   private static function getEuroMultiplier() {
      $xml = file_get_contents("https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");
      $obj = simplexml_load_string($xml);
      return abs($obj->Cube->Cube->Cube[0]['rate'] - 2);
   }

   public static function getMarketPrice($base, $currency = "BTC")
   {
      if(isset(self::$marketPrices[$currency][$base])) {
         return self::$marketPrices[$currency][$base];
      }

      $url = 'https://api.coinranking.com/v2/coins';
      
      $headers = [
         'x-access-token: ' . BinanceBotSettings::getInstance()->cr_apikey
      ];
      $request = "{$url}"; // create the request URL

      $curl = curl_init(); // Get cURL resource
      // Set cURL options
      curl_setopt_array($curl, array(
         CURLOPT_URL => $request,            // set the request URL
         CURLOPT_HTTPHEADER => $headers,     // set the headers 
         CURLOPT_RETURNTRANSFER => 1         // ask for raw response instead of bool
      ));

      $response = curl_exec($curl); // Send the request, save the response
      //print_r($response);
      $json = json_decode($response); // print json decoded response
      curl_close($curl); // Close request

      foreach($json->data->coins as $X) {
         if($X->symbol == $currency) {
            if(!isset(self::$marketPrices[$currency])){
               self::$marketPrices[$currency] = [];
            }
            $val = $base == "USD" ? 1 : self::getEuroMultiplier();
            self::$marketPrices[$currency][$base] = $X->price * $val;
            return $X->price;
         }
      }
   }
}