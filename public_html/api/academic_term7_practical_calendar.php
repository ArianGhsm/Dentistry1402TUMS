<?php
declare(strict_types=1);

/** Education office: revised second practical rotation, 1405/08/23–1405/10/26. */
function dent_term7_practical_calendar(): array
{
    return [
        'rotationBThrough' => '1405/10/26',
        'closures' => [
            '1405/09/28' => 'board', '1405/09/29' => 'board', '1405/09/30' => 'board',
            '1405/10/01' => 'board', '1405/10/02' => 'board', '1405/10/16' => 'official',
        ],
        'makeup' => ['1405/10/26' => ['weekday' => 3, 'replaces' => '1405/10/16']],
    ];
}
