<?php

namespace Binance;

class API
{
    /**
     * @param mixed $key
     * @param mixed $secret
     */
    public function __construct($key = null, $secret = null)
    {
        if ($key === null && $secret === null) {
            return;
        }
    }

    /**
     * @param mixed $symbol
     * @return array<string, mixed>
     */
    public function prevDay($symbol)
    {
        return array();
    }

    /**
     * @param mixed $symbol
     * @return array<int, mixed>
     */
    public function orders($symbol)
    {
        return array();
    }

    /**
     * @param mixed $symbol
     * @return array<int, mixed>
     */
    public function openOrders($symbol)
    {
        return array();
    }

    /**
     * @param mixed $symbol
     * @param mixed $quantity
     * @param mixed $price
     * @return array<string, mixed>
     */
    public function buy($symbol, $quantity, $price)
    {
        return array();
    }

    /**
     * @param mixed $symbol
     * @param mixed $quantity
     * @param mixed $price
     * @return array<string, mixed>
     */
    public function sell($symbol, $quantity, $price)
    {
        return array();
    }

    /**
     * @param mixed $symbol
     * @param mixed $orderId
     * @return mixed
     */
    public function cancel($symbol, $orderId)
    {
        return array();
    }

    /**
     * @param mixed $prices
     * @return array<string, mixed>
     */
    public function balances($prices)
    {
        return array();
    }

    /**
     * @param mixed $symbol
     * @param mixed $interval
     * @return array<int, mixed>
     */
    public function candlesticks($symbol, $interval)
    {
        return array();
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeInfo()
    {
        return array();
    }

    /**
     * @param mixed $bytes
     * @return void
     */
    public function addToTransfered($bytes)
    {
    }

    /**
     * @return int
     */
    public function getRequestCount()
    {
        return 0;
    }

    /**
     * @return int
     */
    public function getTransfered()
    {
        return 0;
    }
}
