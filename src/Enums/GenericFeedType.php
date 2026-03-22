<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum GenericFeedType: string
{
    case PROMOTIONS                   = 'PROMOTIONS';
    case PRODUCT_RATINGS_AND_REVIEWS  = 'PRODUCT_RATINGS_AND_REVIEWS';
    case SHIPPING_PROFILES            = 'SHIPPING_PROFILES';
    case NAVIGATION_MENU              = 'NAVIGATION_MENU';
    case OFFER                        = 'OFFER';
}
