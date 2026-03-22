<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum AccountStatus: string
{
    case ACTIVE       = 'active';
    case DISCONNECTED = 'disconnected';
    case REMOVED      = 'removed';
}
