<?php

namespace BinanceBot;

class BinanceBotPrices
{
   private $_api = null;
   private $prices = null;
   private static $btcusd = 0;
   private static $btceur = 0;

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

      self::$btcusd = 0;
      self::$btceur = 0;
      $this->forceupdate();
   }

   public function forceupdate()
   {
      $this->prices = $this->_api->prices();
      self::getBTCUSD();
      self::getBTCEUR();
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
      if( self::$btcusd == 0 )
      {
         $page = file_get_contents( "https://bitpay.com/api/rates" );
         $my_array = json_decode( $page, true );
         self::$btcusd = round( $my_array[1][ "rate" ] );
      }

      return self::$btcusd;
   }

   public static function getBTCEUR()
   {
      if( self::$btceur == 0 )
      {
         $page = file_get_contents( "https://bitpay.com/api/rates" );
         $my_array = json_decode( $page, true );
         self::$btceur = round( $my_array[2][ "rate" ] );
      }

      return self::$btceur;
   }
}
