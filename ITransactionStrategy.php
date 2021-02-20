<?php

namespace BinanceBot;

interface ITransactionStrategy
{
   public function getBuySpread( $symbol, $TotalPriceUSD, $quantity );
   public function getSellSpread( $symbol, $TotalPriceUSD, $quantity );
   public function limitOrders( $side );
}
