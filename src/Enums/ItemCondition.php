<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum ItemCondition: string
{
    case NEW         = 'new';
    case REFURBISHED = 'refurbished';
    case USED        = 'used';
}
