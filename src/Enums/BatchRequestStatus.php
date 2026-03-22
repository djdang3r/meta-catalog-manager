<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum BatchRequestStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case COMPLETE   = 'complete';
    case FAILED     = 'failed';
}
