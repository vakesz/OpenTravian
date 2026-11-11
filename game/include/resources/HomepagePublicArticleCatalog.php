<?php

declare(strict_types=1);

/**
 * Source-provenanced, static snapshots of the articles reached from the local
 * public Homepage.  The renderer accepts only the typed blocks below; it does
 * not render source HTML or make a runtime request to Travian.
 *
 * @return array<string, array{
 *   id:string,
 *   path:string,
 *   title:string,
 *   preview:string,
 *   publishedAt:int,
 *   excerptAsset:string,
 *   bannerAsset:string,
 *   body:list<array<string, mixed>>
 * }>
 */
return [
    'winter-time-the-effects-on-your-game' => [
        'id' => '2026/10/22/winter-time-the-effects-on-your-game/',
        'path' => '/homepage/news/2026/10/22/winter-time-the-effects-on-your-game/',
        'title' => 'Winter Time – The Effects on Your Game',
        'preview' => 'Winter time is coming! Find out how the time change affects server time, troop movements, daily quests, and other in-game events.',
        'publishedAt' => 1792682366,
        'excerptAsset' => 'winter-excerpt.jpeg',
        'bannerAsset' => 'winter-banner-1024x379.jpg',
        'body' => [
            [
                'type' => 'heading',
                'level' => 3,
                'text' => 'How does the time change affect my game?',
            ],
            [
                'type' => 'paragraph',
                'parts' => [[
                    'type' => 'text',
                    'text' => 'When clocks move back, the server time adjusts too — which can slightly shift in-game timers and events. Here’s what to expect and how to prepare.',
                ]],
            ],
            [
                'type' => 'heading',
                'level' => 4,
                'text' => 'When does the time change in Europe?',
            ],
            [
                'type' => 'list',
                'items' => [[
                    [
                        'type' => 'text',
                        'text' => 'On the last Sunday of October, clocks move back one hour (+1 hour, creating a 25-hour day). The next time change will occur on ',
                    ],
                    [
                        'type' => 'strong',
                        'text' => 'Sunday, October 25',
                    ],
                    [
                        'type' => 'text',
                        'text' => '.',
                    ],
                ]],
            ],
            [
                'type' => 'paragraph',
                'parts' => [[
                    'type' => 'strongEm',
                    'text' => 'Server time changes at 1:00 AM UTC+0.',
                ]],
            ],
            [
                'type' => 'heading',
                'level' => 4,
                'text' => 'What can this affect in-game?',
            ],
            [
                'type' => 'list',
                'items' => [
                    [[
                        'type' => 'text',
                        'text' => 'The reset time of daily quests will shift by one hour.',
                    ]],
                    [[
                        'type' => 'text',
                        'text' => 'Reports and everything else will repeat one hour (01:59:59 → 01:00:00).',
                    ]],
                    [[
                        'type' => 'text',
                        'text' => 'Beginner’s protection duration, as well as the duration of everything else, will remain the same as always, but the displayed end time will be adjusted.',
                    ]],
                    [[
                        'type' => 'text',
                        'text' => 'Map.sql updates at midnight server time and follows UTC+1 during summer, UTC+0 during winter.',
                    ]],
                ],
            ],
            [
                'type' => 'heading',
                'level' => 4,
                'text' => 'Example scenario',
            ],
            [
                'type' => 'paragraph',
                'parts' => [[
                    'type' => 'text',
                    'text' => 'Player A sends an attack at 23:00 server time on Saturday, which takes 4.5 hours. It will land on Sunday at 02:30 server time, after the clock has moved back one hour.',
                ]],
            ],
            [
                'type' => 'paragraph',
                'parts' => [[
                    'type' => 'text',
                    'text' => 'The game already accounts for the upcoming time change when troops are sent, so the correct arrival time will be displayed from the start.',
                ]],
            ],
            [
                'type' => 'paragraph',
                'parts' => [
                    [
                        'type' => 'text',
                        'text' => '🔗 ',
                    ],
                    [
                        'type' => 'link',
                        'text' => 'Check when clocks change in your region',
                        'href' => 'https://www.timeanddate.com/time/change/',
                        'strong' => true,
                    ],
                ],
            ],
            [
                'type' => 'paragraph',
                'parts' => [[
                    'type' => 'text',
                    'text' => 'All the examples and information above apply to server time. You can see and change the time zone used in-game in your settings.',
                ]],
            ],
        ],
    ],
    'tournament' => [
        'id' => '2025/01/06/tournament/',
        'path' => '/homepage/news/2025/01/06/tournament/',
        'title' => 'Tournament',
        'preview' => 'The Travian Tournament is where Legends are made.',
        'publishedAt' => 1736177762,
        'excerptAsset' => 'ttsquare-160x160.jpg',
        'bannerAsset' => 'tournament-banner-1024x503.jpg',
        'body' => [
            [
                'type' => 'heading',
                'level' => 1,
                'text' => 'Where legends are made',
            ],
            [
                'type' => 'section',
                'spacing' => 'medium',
                'blocks' => [
                    [
                        'type' => 'heading',
                        'level' => 2,
                        'text' => 'Philosophy',
                    ],
                    [
                        'type' => 'paragraph',
                        'parts' => [[
                            'type' => 'text',
                            'text' => 'Step into the arena where legends are made. Welcome to the ultimate battleground of strategy gaming — the Travian Tournament. Face off against elite players from across the globe in brutal, high-stakes clashes for glory, power, and epic rewards. This is not your average game; it’s a war of minds, alliances, and ruthless ambition. Join a fierce community of veterans who fight year after year to crush rivals and carve their names into history. Do you have what it takes to dominate? Prove it.',
                        ]],
                    ],
                ],
            ],
            [
                'type' => 'section',
                'spacing' => 'large',
                'blocks' => [
                    [
                        'type' => 'heading',
                        'level' => 2,
                        'text' => 'Tournament Phases',
                    ],
                    [
                        'type' => 'columns',
                        'items' => [
                            [
                                'title' => 'Qualifications – June',
                                'parts' => [[
                                    'type' => 'text',
                                    'text' => 'Prove your might in the Qualifications and earn your place among the elite in the Finals. The setup is brutal and brilliant: all five tribes, double speed, global competition. From calculated raids to all-out war, every move counts. And for those who just miss the cut? There’s a final shot — a handful of rare, coveted wildcards. This isn’t just strategy. It’s survival of the sharpest.',
                                ]],
                            ],
                            [
                                'title' => 'Finals – November',
                                'parts' => [[
                                    'type' => 'text',
                                    'text' => 'This is the final clash — the stage where legends are born, where betrayal cuts deep and alliances shape empires. It’s time to put your tactics, your teamwork, and your grit to the test. The world is watching. Face off against the fiercest players on the planet for epic rewards and eternal glory.',
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'timeline',
                'items' => [
                    ['title' => 'Training', 'subtitle' => '(Mar. – May)'],
                    ['title' => 'Qualification', 'subtitle' => '(Jun. – Sep.)'],
                    ['title' => 'Wildcard', 'subtitle' => '(Oct.)'],
                    ['title' => 'Finals', 'subtitle' => '(Nov. – Feb.)'],
                ],
            ],
            [
                'type' => 'section',
                'spacing' => 'large',
                'blocks' => [
                    [
                        'type' => 'heading',
                        'level' => 2,
                        'text' => 'Prizes',
                    ],
                    [
                        'type' => 'paragraph',
                        'parts' => [[
                            'type' => 'text',
                            'text' => 'Legendary victories demand legendary rewards. A massive prize pool of 20,000 € and mountains of Gold await the champions of the Finals. But the glory doesn’t stop there — Gold is up for grabs in the Qualifications too, along with those precious, hard-earned Finals access keys. Only the best will claim them.',
                        ]],
                    ],
                    [
                        'type' => 'paragraph',
                        'parts' => [[
                            'type' => 'text',
                            'text' => 'Ready to see what’s at stake?',
                        ]],
                    ],
                    [
                        'type' => 'paragraph',
                        'parts' => [[
                            'type' => 'text',
                            'text' => 'Here are the winning categories:',
                        ]],
                    ],
                    [
                        'type' => 'featureList',
                        'items' => [
                            [
                                'title' => 'Wonder of the World owners',
                                'description' => '– Own one of the largest Wonders of the World at the end of the game round.',
                            ],
                            [
                                'title' => 'Alliance members',
                                'description' => '– Be in an alliance that owns one of the largest Wonders of the World at the end of the game round.',
                            ],
                            [
                                'title' => 'Top rankings',
                                'description' => '– Be among the top 20 players in either the offensive, defensive or population rankings.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'spacing' => 'large',
                        'blocks' => [
                            [
                                'type' => 'heading',
                                'level' => 2,
                                'text' => 'More Information',
                            ],
                            [
                                'type' => 'list',
                                'items' => [
                                    [
                                        [
                                            'type' => 'text',
                                            'text' => 'For full technical details of the game world setup, visit the Game Worlds Setup overview.',
                                        ],
                                    ],
                                    [
                                        [
                                            'type' => 'text',
                                            'text' => 'To explore both Qualification and Finals stages in detail, see the Tournament Overview page.',
                                        ],
                                    ],
                                    [
                                        [
                                            'type' => 'text',
                                            'text' => 'For a full breakdown of rewards, visit the Tournament Prizes overview.',
                                        ],
                                    ],
                                    [[
                                        'type' => 'text',
                                        'text' => 'Terms and Conditions (in English)',
                                    ]],
                                    [[
                                        'type' => 'text',
                                        'text' => 'Every Tournament Hall of Fames',
                                    ]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
