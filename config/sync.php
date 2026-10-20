<?php

return [

    /*
    | Maximum number of records per entity returned in each page of the pull endpoint.
    */
    'pull_page_size' => 200,

    /*
    | Seconds subtracted from the snapshot to build the next cursor, so rows committed
    | slightly after the snapshot was taken are still picked up (duplicates are harmless).
    */
    'pull_cursor_margin_seconds' => 5,

];
