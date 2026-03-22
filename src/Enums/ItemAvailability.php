<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum ItemAvailability: string
{
    case IN_STOCK             = 'in stock';
    case OUT_OF_STOCK         = 'out of stock';
    case PREORDER             = 'preorder';
    case AVAILABLE_FOR_ORDER  = 'available for order';
    case DISCONTINUED         = 'discontinued';
}
