<?php

require 'Bot.class.php';

class BinanceBot extends Bot
{
   public function __construct()
   {
      parent::__construct();
   }
}

$bot = new BinanceBot();
$bot->run();
