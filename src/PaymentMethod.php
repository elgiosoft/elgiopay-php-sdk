<?php

namespace ElgioPay\SDK;

enum PaymentMethod: string
{
    case MTN_MOBILE_MONEY = 'mtn_mobile_money';
    case ORANGE_MONEY = 'orange_money';
}