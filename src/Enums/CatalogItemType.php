<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum CatalogItemType: string
{
    case PRODUCT_ITEM   = 'PRODUCT_ITEM';
    case VEHICLE        = 'VEHICLE';
    case HOTEL          = 'HOTEL';
    case HOTEL_ROOM     = 'HOTEL_ROOM';
    case FLIGHT         = 'FLIGHT';
    case DESTINATION    = 'DESTINATION';
    case HOME_LISTING   = 'HOME_LISTING';
    case VEHICLE_OFFER  = 'VEHICLE_OFFER';
}
