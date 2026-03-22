<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum FeedIngestionSourceType: string
{
    case PRIMARY_FEED       = 'PRIMARY_FEED';
    case SUPPLEMENTARY_FEED = 'SUPPLEMENTARY_FEED';
}
