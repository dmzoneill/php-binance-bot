<?php

namespace BinanceBot;

class BinanceBotSettings
{
   private static $instance;
   private $data = array();
   private $configFile = "config.json";

   private function __construct()
   {
      $this->load();
   }

   private function load()
   {
      $this->data = json_decode( file_get_contents( $this->configFile ), true );
   }

   public function update()
   {
      $this->load();
   }

   public function __get( $variable )
   {
      if( isset( $this->data[ $variable ] ) )
      {
         return $this->data[ $variable ];
      }
      else
      {
         print "Unknown variable: $variable\n";
      }
   }

   public static function getInstance()
   {
      if ( !isset( self::$instance) )
      {
         self::$instance = new BinanceBotSettings();
      }
      return self::$instance;
   }
}
