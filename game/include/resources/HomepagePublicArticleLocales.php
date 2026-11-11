<?php

declare(strict_types=1);

/*
 * Per-language overrides (preview text and body) of the catalogued public news
 * articles, keyed article key => public `lang`. A language without an entry
 * uses the English article, exactly as the reference does.
 *
 * Source: the public lobby news API (/api/news?lang={lang}&after=&amount=200)
 * for lang in {ar, bg, bs, cs, da, de, el, en, es, et, fi, fr, he, hr, hu, id, it, ja, lt, lv, ms, nl, no, pl, pt, ro, ru, sk, sl, sr, sv, th, tr, vi, zh}, retrieved 2026-11-06.
 * Uncompressed override JSON SHA-256: 2c6872e4516aa2e66530ee993f49a5d83469869ab73bab30a4e11db05fe6c8f9
 */
return [
    'winter-time-the-effects-on-your-game' => [
        'de' => [
            'preview' => 'Die Winterzeit kommt! Erfahre, wie sich die Zeitumstellung auf die Serverzeit, Truppenbewegungen, täglichen Aufgaben und andere Ereignisse im Spiel auswirkt.',
            'body' => [
                [
                    'type' => 'heading',
                    'level' => 3,
                    'text' => 'Wie wirkt sich die Zeitumstellung auf mein Spiel aus?',
                ],
                [
                    'type' => 'paragraph',
                    'parts' => [
                        [
                            'type' => 'text',
                            'text' => 'Wenn die Uhren zurückgestellt werden, passt sich auch die Serverzeit an. Dadurch können sich die angezeigten Uhrzeiten von Spielereignissen und Timern leicht verschieben. Hier erfährst du, was dich erwartet und worauf du achten solltest.',
                        ],
                    ],
                ],
                [
                    'type' => 'heading',
                    'level' => 4,
                    'text' => 'Wann findet die Zeitumstellung in Europa statt?',
                ],
                [
                    'type' => 'list',
                    'items' => [
                        [
                            [
                                'type' => 'text',
                                'text' => 'Am letzten Sonntag im Oktober werden die Uhren um eine Stunde zurückgestellt (+1 Stunde, wodurch der Tag 25 Stunden hat). Die nächste Zeitumstellung findet am ',
                            ],
                            [
                                'type' => 'strong',
                                'text' => 'Sonntag, dem 25. Oktober',
                            ],
                            [
                                'type' => 'text',
                                'text' => ', statt.',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'parts' => [
                        [
                            'type' => 'strongEm',
                            'text' => 'Die Serverzeit wird um 1:00 Uhr UTC+0 umgestellt.',
                        ],
                    ],
                ],
                [
                    'type' => 'heading',
                    'level' => 4,
                    'text' => 'Was kann sich dadurch im Spiel ändern?',
                ],
                [
                    'type' => 'list',
                    'items' => [
                        [
                            [
                                'type' => 'text',
                                'text' => 'Die Rücksetzzeit der täglichen Aufgaben verschiebt sich um eine Stunde.',
                            ],
                        ],
                        [
                            [
                                'type' => 'text',
                                'text' => 'Bei Berichten und allen anderen zeitabhängigen Ereignissen wiederholt sich eine Stunde (01:59:59 → 01:00:00).',
                            ],
                        ],
                        [
                            [
                                'type' => 'text',
                                'text' => 'Die Dauer des Anfängerschutzes und aller anderen zeitlich begrenzten Vorgänge bleibt unverändert. Lediglich die angezeigte Endzeit wird angepasst.',
                            ],
                        ],
                        [
                            [
                                'type' => 'text',
                                'text' => 'Die Datei Map.sql wird um Mitternacht (Serverzeit) aktualisiert und richtet sich im Sommer nach UTC+1 und im Winter nach UTC+0.',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'heading',
                    'level' => 4,
                    'text' => 'Beispielszenario',
                ],
                [
                    'type' => 'paragraph',
                    'parts' => [
                        [
                            'type' => 'text',
                            'text' => 'Spieler A schickt am Samstag um 23:00 Uhr Serverzeit einen Angriff los, der 4,5 Stunden unterwegs ist. Der Angriff trifft am Sonntag um 02:30 Uhr Serverzeit ein, nachdem die Uhr um eine Stunde zurückgestellt wurde.',
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'parts' => [
                        [
                            'type' => 'text',
                            'text' => 'Das Spiel berücksichtigt die bevorstehende Zeitumstellung bereits beim Abschicken der Truppen. Die korrekte Ankunftszeit wird daher von Anfang an angezeigt.',
                        ],
                    ],
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
                            'text' => 'Prüfe, wann die Uhren in deiner Region umgestellt werden',
                            'href' => 'https://www.timeanddate.com/time/change/',
                            'strong' => true,
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'parts' => [
                        [
                            'type' => 'text',
                            'text' => 'Alle oben genannten Beispiele und Informationen beziehen sich auf die Serverzeit. Du kannst die im Spiel verwendete Zeitzone in deinen Einstellungen einsehen und ändern.',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
