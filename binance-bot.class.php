<?php

require 'bi-api.php';
require 'BinanceBotService.class.php';
require 'BinanceBotOrders.class.php';
require 'BinanceBotHoldings.class.php';
require 'BinanceBotPrinter.class.php';
require 'BinanceBotPrices.class.php';
require 'ITransactionStrategy.php';
require 'TransactionStrategy.class.php';
require 'BasicTransactionStrategy.class.php';
require 'PeriodBasedTransactionStrategy.class.php';
require 'BinanceBotSettings.class.php';
require 'BinanceBotCandles.class.php';
require 'ISMSGateway.php';
require 'sms/EirSMS.class.php';
require 'Bot.class.php';

$bot = new BinanceBot\Bot();
$bot->run();
