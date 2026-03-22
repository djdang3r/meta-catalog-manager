<?php

namespace ScriptDevelop\MetaCatalogManager\Enums;

enum FeedFormat: string
{
    case CSV           = 'csv';
    case TSV           = 'tsv';
    case RSS_XML       = 'rss_xml';
    case ATOM_XML      = 'atom_xml';
    case GOOGLE_SHEETS = 'google_sheets';
}
