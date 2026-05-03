<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

/**
 * Official Meta Marketing API catalog vertical values.
 * @see https://developers.facebook.com/docs/marketing-api/reference/product-catalog/
 */
enum CatalogVertical: string
{
    case ADOPTABLE_PETS             = 'adoptable_pets';
    case APPS_AND_SOFTWARE          = 'apps_and_software';
    case ARTICLES_AND_PUBLICATIONS   = 'articles_and_publications';
    case COMMERCE                   = 'commerce';
    case DESTINATIONS               = 'destinations';
    case FLIGHTS                    = 'flights';
    case GENERIC                    = 'generic';
    case HOME_LISTINGS              = 'home_listings';
    case HOTELS                     = 'hotels';
    case LOCAL_SERVICE_BUSINESSES   = 'local_service_businesses';
    case MEDIA_TITLES               = 'media_titles';
    case OFFER_ITEMS                = 'offer_items';
    case OFFLINE_COMMERCE           = 'offline_commerce';
    case PROFESSIONAL_SERVICES      = 'professional_services';
    case TRANSACTABLE_ITEMS         = 'transactable_items';
    case VEHICLES                   = 'vehicles';
}
