# PHP Binance Bot

## Based on
https://github.com/jaggedsoft/php-binance-api

![alt text](https://github.com/dmzoneill/php-binance-bot/raw/master/example.png)

## Donations

Bitcoin 1BLX95wuEQdMkdeRLR5mjgJ1AvJv16CY1r

ETH 0x8831876a202a5722d869a6397e521b94bef483a6

LTC LZSEvLg43YXT1yoPacpKWgzwo3zzUFbF4y

Strategies wrriten on demand - donations helps

## Getting Started

Bot has made me 40% more bitcoin, uses basic fibionacci strategy

modify config.json and put in the relevant api and prikey, 

modify the rest of the settings if you wish

## Basic operation

php binance-bot.class.php

The bot will buy on downward trending stocks defined by "**downward_trigger_percent**" using the 24hr percent change.


### Buy orders

If the "**downward_trigger_percent**" is set at -5,  then when the stock hits -6% in the last day, the bot will buy set a 'buy limit' at "**buy_at_percent**" (86%).  

The bot will buy "**buy_total_amount**". 

The quantity is that "**buy_total_amount**" divided by the unit price.

The Bot decides to buy penny stocks lower than the "**max_unit_price**" (0.0001) roughly $1.

The bot will buy up to "**max_open_buy_orders**" (15) orders.

the bot will set the 'buy limit' at "**buy_at_percent**" (0.82)

The bot will cancel buy orders if they fall out of the range of "**cancel_buy_at_lower_percent**" (72%) and "**cancel_buy_at_upper_percent**" (132%)


### Sell orders

Once a buy has been detected 
The Bot will immediately 'set limit' for the maximum amount available at "**sell_at_percent**" (1.22) .

The bot will buy up to "**max_open_buy_orders**" (15)

### Loss protection

None at this time.

### Can i change the logic or implement other strategies.

sure, Look at basicTractionStategy (strategy pattern)
