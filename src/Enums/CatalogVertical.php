<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum CatalogVertical: string
{
    case COMMERCE       = 'commerce';
    case VEHICLES       = 'vehicles';
    case HOTELS         = 'hotels';
    case FLIGHTS        = 'flights';
    case DESTINATIONS   = 'destinations';
    case HOME_LISTINGS  = 'home_listings';
    case VEHICLE_OFFERS = 'vehicle_offers';
}
