<?php

use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\Extension\WikiLink\WikiLinkExtension;

return [

    'extensions' => [
        CommonMarkCoreExtension::class,
        TableExtension::class,
        TaskListExtension::class,
        StrikethroughExtension::class,
        TableOfContentsExtension::class,
        WikiLinkExtension::class,
    ],

    'options' => [

        'wikilinks' => [
            'base_url'        => '/vault/',
            'html_class'      => 'wikilink',
            'image_class'     => 'wikilink-image',
            'file_extension'  => '.md',
        ],

        'table_of_contents' => [
            'html_class' => 'toc',
            'position'   => 'before-first-heading',
        ],

        // Soft breaks = <br>
        'renderer' => [
            'soft_break' => "<br />",
        ],
    ],
];
