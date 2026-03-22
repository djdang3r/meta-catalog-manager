<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum OfferApplicationType: string
{
    case SALE                    = 'SALE';
    case AUTOMATIC_AT_CHECKOUT   = 'AUTOMATIC_AT_CHECKOUT';
    case BUYER_APPLIED           = 'BUYER_APPLIED';
}
