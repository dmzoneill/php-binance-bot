<?php

namespace BinanceBot;

use Exception;

class BinanceBotHoldings extends BinanceBotService
{
    private mixed $balances = null;

    /**
     * @var (float|mixed|string)[][]
     *
     */
    private array $wallet = array();
    private array $holdings = array();
    private mixed $cum_btcvalue = 0;
    private mixed $cum_usdvalue = 0;
    private mixed $cum_btctotal = 0;
    private mixed $cum_usdtotal = 0;
    private mixed $cum_eurtotal = 0;
    private bool $balancesChanged = false;
    private mixed $balanceUpdateCounter = 0;

    #[\Override]
    protected function cacheFile()
    {
        return BinanceBotSettings::getInstance()->cacheBalancesFile;
    }

    #[\Override]
    public function update()
    {
        $this->balanceUpdateCounter++;
        if (
            file_exists(BinanceBotSettings::getInstance()->cacheBalancesFile) && $this->balanceUpdateCounter % 13
            != 0
        ) {
            $this->load();
        } else {
            $updated = null;
            try {
                $updated = $this->api->balances($this->prices->getAllPrices());
                asort($updated);
                if (
                    $this->balances != null
                    && $updated[ BinanceBotSettings::getInstance()->base_currency ][ 'available' ]
                        != $this->balances[ BinanceBotSettings::getInstance()->base_currency ][ 'available' ]
                ) {
                    $txt = $this->getBTCTotal();
                    file_put_contents(
                        BinanceBotSettings::getInstance()->baseCurrencyTrackerFile,
                        strval($txt) . PHP_EOL,
                        FILE_APPEND | LOCK_EX
                    );
                    $this->balancesChanged = true;
                }
            } catch (Exception $e) {
                print($e);
            }
            $this->balances = $updated;
        }

        $this->updateWallet();
        $this->save();
    }

    public function getHoldingsChanged(): bool
    {
        if ($this->balancesChanged == true) {
            $this->smsGateway->send("Holdings Changed");
            $this->balancesChanged = false;
            return true;
        }

        return false;
    }

    private function save(): void
    {
        file_put_contents(BinanceBotSettings::getInstance()->cacheBalancesFile, serialize($this->balances));
    }

    private function load(): void
    {
        $raw = file_get_contents(BinanceBotSettings::getInstance()->cacheBalancesFile);
        if (is_string($raw)) {
            $this->balances = unserialize($raw);
        }
    }

    public function getBalances(): array
    {
        $ignored_coins = BinanceBotSettings::getInstance()->ignoredCoinPairs();

        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " ignored coins:\n");
            print(implode(",", $ignored_coins) . "\n");
        }

        $temp = [];

        foreach ($this->balances as $symbol => $data) {
            if (in_array($symbol, $ignored_coins)) {
                if (BinanceBotSettings::getInstance()->debug) {
                    print(__FUNCTION__ . ":" . __LINE__ . " ignored coin $symbol \n");
                }
                continue;
            }
            $temp[$symbol] = $data;
        }

        return $temp;
    }

    public function getHoldings(mixed $symbol): mixed
    {
        if (isset($this->holdings[ $symbol ])) {
           //print $symbol . " - " . $this->holdings[ $symbol ] . "\n";
            return $this->holdings[ $symbol ];
        }
        return false;
    }

    public function getWallet(): mixed
    {
        return $this->wallet;
    }

    public function getBaseCurrencyAvailable(): mixed
    {
        if (!isset($this->balances[ BinanceBotSettings::getInstance()->base_currency ])) {
            @unlink(BinanceBotSettings::getInstance()->cacheBalancesFile);
            $this->update();
        }
        return $this->balances[ BinanceBotSettings::getInstance()->base_currency ][ 'available' ];
    }

    public function getBTCTotal(): mixed
    {
        $cumbtctotal = 0;

        foreach ($this->balances as $data) {
            $btctotal = $data['btcTotal'];
            $cumbtctotal += $btctotal;
        }

        return $cumbtctotal;
    }

    private function updateWallet(): void
    {
        $exchange_rate = $this->prices->getBTCUSD();
        $exchange_rate_eur = $this->prices->getBTCEUR();

        $this->cum_btcvalue = 0;
        $this->cum_usdvalue = 0;
        $this->cum_btctotal = 0;
        $this->cum_usdtotal = 0;
        $this->cum_eurtotal = 0;
        $this->wallet = array();
        $this->holdings = array();

        foreach ($this->balances as $symbol => $data) {
            if ($data[ 'available' ]) {
                $btcvalue = $data['btcValue'];
                $usdvalue = $exchange_rate * $data['btcValue'];
                $btctotal = $data['btcTotal'];
                $usdtotal = $exchange_rate * $data['btcTotal'];
                $eurtotal = $exchange_rate_eur * $data['btcTotal'];

                $this->holdings[ $symbol ] = $usdtotal;
                if ($data['available'] > 0.00000000 || $data['onOrder'] > 0.00000000) {
                    $this->wallet[] = array( $symbol, $data['available'], $data['onOrder'], $btcvalue, round(
                        $usdvalue,
                        2
                    ), $btctotal, round($usdtotal, 2), round($eurtotal, 2) );
                } else {
                    $other = isset($this->wallet[ 'other' ]) ? $this->wallet[ 'other' ] : array( '-OTHER-', 0, 0, 0, 0,
                        0, 0, 0 );
                    $other[1] += $data['available'];
                    $other[2] += $data['onOrder'];
                    $other[3] = (float) $other[3] + (float) $btcvalue;
                    $other[4] = (float) $other[4] + round($usdvalue, 2);
                    $other[5] = (float) $other[5] + (float) $btctotal;
                    $other[6] = (float) $other[6] + round($usdtotal, 2);
                    $other[7] = (float) $other[7] + round($eurtotal, 2);
                    $this->wallet[ 'other' ] = $other;
                }

                $this->cum_btcvalue += $btcvalue;
                $this->cum_usdvalue += $usdvalue;
                $this->cum_btctotal += $btctotal;
                $this->cum_usdtotal += $usdtotal;
                $this->cum_eurtotal += $eurtotal;
            }
        }

        usort($this->wallet, function ($a, $b) {
            return $a[6] < $b[6] ? 1 : 0;
        });
    }

    public function getCumBTCValue(): mixed
    {
        return $this->cum_btcvalue;
    }

    public function getCumUSDValue(): float
    {
        return round($this->cum_usdvalue, 2);
    }

    public function getCumBTCTotal(): mixed
    {
        return $this->cum_btctotal;
    }

    public function getCumUSDTotal(): float
    {
        return round($this->cum_usdtotal, 2);
    }

    public function getCumEURTotal(): float
    {
        return round($this->cum_eurtotal, 2);
    }
}
