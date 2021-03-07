<?php

namespace BinanceBot;

class BinanceBotSettings
{
   private static $instance;
   private $data = array();
   private $configFile = "config.json";
   private $timeLoaded = 0;
   private $lastCacheClear = 0;

   private function __construct()
   {
      $this->load();
   }

   private function load()
   {
      $this->lastCacheClear = time();
      $this->data = json_decode( file_get_contents( $this->configFile ), true );
      $this->timeloaded = filemtime( $this->configFile );
   }

   private function reload()
   {
      clearstatcache();
      
      if($this->timeloaded < filemtime( $this->configFile )) {
         print("Settings change detected... \n");
         $this->load();
      }
   }

   public function update()
   {
      $this->load();
   }

   public function __get( $variable )
   {
      $this->reload();

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
