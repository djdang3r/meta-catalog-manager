<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum OfferTargetGranularity: string
{
    case ITEM_LEVEL  = 'ITEM_LEVEL';
    case ORDER_LEVEL = 'ORDER_LEVEL';
}
