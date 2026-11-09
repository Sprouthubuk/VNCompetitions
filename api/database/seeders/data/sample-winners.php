<?php

/*
 * The sample winners from web/src/data/comps.ts. Each one gets a drawn comp of its
 * own (these prizes aren't among the 8 live comps). `announced` is Europe/London.
 * comps.ts shows "live draw" winners with no date; announced winners always have a
 * date in the API, so they're given the previous Wednesday's live draw.
 */

return [
    ['name' => 'Craig Baker', 'prize' => 'Star Wars Target Mandalorian 26g', 'ticket' => 143, 'announced' => '2026-11-08 21:30'],
    ['name' => 'Toby Shears', 'prize' => '£150 Harrows mystery box', 'ticket' => 9, 'announced' => '2026-11-08 21:29'],
    ['name' => 'McKenzie Vetier', 'prize' => 'Ryan Searle GEO darts 32g', 'ticket' => 171, 'announced' => '2026-11-08 21:28'],
    ['name' => 'Jamie Guest', 'prize' => 'Target Rove darts 23g', 'ticket' => 126, 'announced' => '2026-11-08 21:27'],
    ['name' => 'Ben Carter', 'prize' => 'Luke Littler Nuke Fallout 24g', 'ticket' => null, 'announced' => '2026-11-04 21:30'],
    ['name' => 'Leigh Picton', 'prize' => 'Phil Taylor Power Chrono 26g', 'ticket' => null, 'announced' => '2026-11-04 21:29'],
    ['name' => 'Dean Bush', 'prize' => 'Charlie Manby 25g 95%', 'ticket' => null, 'announced' => '2026-11-04 21:28'],
    ['name' => 'Robert Pickford', 'prize' => 'Littler Born To Win dartboard', 'ticket' => null, 'announced' => '2026-11-04 21:27'],
];
