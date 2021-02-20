<?php

namespace BinanceBot;

abstract class TransactionStrategy implements ITransactionStrategy
{
   protected $api = null;
   protected $BinanceBotPrices = null;
   protected $BinanceBotOrders = null;
   protected $BinanceBotHoldings = null;
   protected $BinanceBotCandles = null;

   public function __construct( $arrs )
   {
      $this->api = $arrs[0];
      $this->BinanceBotHoldings = $arrs[1];
      $this->BinanceBotOrders = $arrs[2];
      $this->BinanceBotPrices = $arrs[3];
      $this->BinanceBotCandles = $arrs[4];
   }

   public function getBuySpread( $symbol, $TotalPriceUSD, $quantity )
   {
      return round( $TotalPriceUSD - ( BinanceBotPrices::getBTCUSD() * ( $this->BinanceBotPrices->getPrice( $symbol ) * $quantity ) ), 2 );
   }

   public function getSellSpread( $symbol, $TotalPriceUSD, $quantity )
   {
      if( $this->BinanceBotHoldings->getHoldings( $symbol ) != false )
      {
         return round( $TotalPriceUSD - ( BinanceBotPrices::getBTCUSD() * ( $this->BinanceBotPrices->getPrice( $symbol ) * $quantity ) ), 2 );
      }

      return 0.0;
   }
}
