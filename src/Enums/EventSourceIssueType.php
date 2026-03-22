<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum EventSourceIssueType: string
{
    case APP_HAS_NO_AEM_SETUP                    = 'APP_HAS_NO_AEM_SETUP';
    case CATALOG_NOT_CONNECTED_TO_EVENT_SOURCE   = 'CATALOG_NOT_CONNECTED_TO_EVENT_SOURCE';
    case DELETED_ITEM                            = 'DELETED_ITEM';
    case INVALID_CONTENT_ID                      = 'INVALID_CONTENT_ID';
    case MISSING_EVENT                           = 'MISSING_EVENT';
    case NO_CONTENT_ID                           = 'NO_CONTENT_ID';
}
