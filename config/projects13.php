<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integration views
    |--------------------------------------------------------------------------
    |
    | This is the whole seam between this package and an optional integration that adds something
    | to its screens. Each entry is null or empty by default, so nothing is rendered, nothing is
    | required, and nothing here names what an integration would put in.
    |
    | A list entry takes the view that renders one `<flux:table.column>` per extra column, the view
    | that renders one `<flux:table.cell>` per extra column in the same order, and how many columns
    | there are so the empty state can span them.
    |
    | The form entry takes the view that appends fields to the drawer, the names of the fields that
    | view owns — seeded from the previous submission so a failed save comes back filled in — and
    | any extra Alpine expression the submit button has to honour.
    |
    */

    'views' => [

        // Fields appended to the project form drawer.
        'project_form' => [
            'fields' => null,
            'state' => [],
            'submit_guard' => null,
        ],

        // Columns appended to the project list.
        'project_list' => [
            'columns' => null,
            'cells' => null,
            'count' => 0,
        ],

        // Columns appended to the epic list.
        'epic_list' => [
            'columns' => null,
            'cells' => null,
            'count' => 0,
        ],

    ],

];
