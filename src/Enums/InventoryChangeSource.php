<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum InventoryChangeSource: string
{
    case FEED_UPLOAD = 'feed_upload';
    case BATCH_API   = 'batch_api';
    case MANUAL      = 'manual';
    case SYSTEM      = 'system';
}
