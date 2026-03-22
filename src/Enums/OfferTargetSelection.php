<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum OfferTargetSelection: string
{
    case ALL_CATALOG_PRODUCTS = 'ALL_CATALOG_PRODUCTS';
    case SPECIFIC_PRODUCTS    = 'SPECIFIC_PRODUCTS';
}
