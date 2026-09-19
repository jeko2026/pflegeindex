<?php

return [
    // Explicit publication list: importing data does not publish additional pages.
    'pilots' => [
        'potsdam' => ['ambulante-pflegedienste'],
        'neuruppin' => ['ambulante-pflegedienste'],
        'falkensee' => ['tagespflege'],
        'frankfurt-oder' => ['pflegeheime'],
        'cottbus' => ['tagespflege'],
    ],
    'categories' => [
        'ambulante-pflegedienste' => [
            'label' => 'Ambulante Pflegedienste',
            'backlink' => 'Weitere ambulante Pflegedienste',
            'types' => ['Ambulante Pflege'],
            'name_markers' => [],
        ],
        'tagespflege' => [
            'label' => 'Tagespflege',
            'backlink' => 'Weitere Tagespflege-Angebote',
            'types' => ['Tagespflege', 'Tages- und Nachtpflege'],
            'name_markers' => ['tagespflege', 'tages- und nachtpflege'],
        ],
        'pflegeheime' => [
            'label' => 'Pflegeheime',
            'backlink' => 'Weitere Pflegeheime',
            'types' => ['Stationäre Pflege', 'Vollstationäre Pflege', 'Pflegeheim'],
            'name_markers' => ['pflegeheim', 'altenheim', 'seniorenheim', 'seniorenzentrum', 'seniorenresidenz', 'senioren-residenz', 'pflegewohnstift'],
        ],
    ],
];
