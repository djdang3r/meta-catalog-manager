<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum FeedOverrideType: string
{
    case LANGUAGE             = 'language';
    case COUNTRY              = 'country';
    case LANGUAGE_AND_COUNTRY = 'language_and_country';
}
