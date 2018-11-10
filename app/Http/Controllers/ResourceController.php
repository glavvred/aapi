<?php

namespace App\Http\Controllers;

use App\Building;
use Illuminate\Http\Request;
use Psr\Log\InvalidArgumentException;


/**
 * Class ResourcesController
 * @package App\Http\Controllers
 */
class ResourceController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function test(Request $request, int $level, int $bid)
    {
        $res = Building::find($bid)->resources;

        return json_encode($this->parse(($res), $level));
    }

    /**
     *
     * @param $jsonData
     * @param int $level
     * @return array
     */
    public function parse($jsonData, int $level = 1)
    {
        $res = json_decode($jsonData);
        if (empty($res))
            throw new InvalidArgumentException('Json decode error: ' . json_last_error());

        //cost
        $cost = $this->parseCost($res, $level);

        //production
        $production = $this->parseProduction($res, $level);

        return ['cost' => $cost, 'production' => $production];
    }

    /**
     * Parse cost helper
     *
     * @param $jsonDecoded
     * @param int $level
     * @return array
     */
    private function parseCost($jsonDecoded, int $level)
    {
        $cost = $jsonDecoded->cost;
        $costConstantResources = $jsonDecoded->cost->constant->resources;
        $levelCostFormulas = $cost->formula;

        //resources
        $currentCostFormula = [];
        foreach ($levelCostFormulas as $levelFormula) {
            if ($levelFormula->level > $level)
                break;
            else
                $currentCostFormula = $levelFormula;
        }

        $costResources = [];
        foreach ($currentCostFormula->resources as $key => $resource) {
            $x = 0;

            $metal = $costConstantResources->metal;
            $multiplier = $costConstantResources->multiplier;

            $crystal = $costConstantResources->crystal;
            $gas = $costConstantResources->gas;
            $energy = $costConstantResources->energy;

            $string_processed = preg_replace_callback(
                '~\{\$(.*?)\}~si',
                function ($match) use ($metal, $crystal, $gas, $energy, $multiplier, $level) {
                    return eval('return $' . $match[1] . ';');
                },
                $resource);

            eval('$x = intval(' . $string_processed . ");");
            $costResources[$key] = $x;
        };

        return $costResources;
    }

    /**
     * Parse production helper
     *
     * @param $jsonDecoded
     * @param int $level
     * @return array
     */
    private function parseProduction($jsonDecoded, int $level)
    {

        $production = $jsonDecoded->production;
        $productionConstant = $production->constant;
        $levelProductionFormulas = $production->formula;

        //resources
        $currentProductionFormula = [];
        foreach ($levelProductionFormulas as $levelFormula) {
            if ($levelFormula->level > $level)
                break;
            else
                $currentProductionFormula = $levelFormula;
        }

        $productionResources = [];
        foreach ($currentProductionFormula as $key => $resource) {
            if ($key == 'level')
                break;

            $metal = $crystal = $gas = $energy = 0;

            if (!empty($productionConstant->metal))
                $metal = $productionConstant->metal->base;

            if (!empty($productionConstant->crystal))
                $crystal = $productionConstant->crystal->base;

            if (!empty($productionConstant->gas))
                $gas = $productionConstant->gas->base;

            if (!empty($productionConstant->energy))
                $energy = $productionConstant->energy->base;

            $res = 0;
            $multiplier = 0;

            eval('$multiplier = $productionConstant->{$key}->multiplier;');
            $temp = 0;
            eval('$temp = $productionConstant->{$key}->base;');
            $$key = $temp;

            $string_processed = preg_replace_callback(
                '~\{\$(.*?)\}~si',
                function ($match) use ($key, $multiplier, $metal, $crystal, $gas, $energy) {
                    return eval('return $' . $match[1] . ';');
                },
                $resource);

            eval('$res = intval(' . $string_processed . ");");
            $productionResources[$key] = $res;

        };
        return $productionResources;
    }

    public function testMany(int $bid)
    {
        $res = Building::find($bid)->resources;

        echo '<table border="1">';
        for ($level = 0; $level < 100; $level++) {
            echo '<tr><td> level: ' . $level.'</td>';
            echo '<td>';
            foreach ($this->parse(($res), $level) as $key => $item) {
                echo '<table border="1" style="float: left; margin-right:20px">
                    <tr><td>' . $key . '</td>';
                        if (!empty($item['metal'])) {
                            echo '<td>m: ' . $item['metal'] . '</td>';
                        }
                        if (!empty($item['crystal'])) {
                            echo '<td>c: ' . $item['crystal'] . '</td>';
                        }
                        if (!empty($item['gas'])) {
                            echo '<td>g: ' . $item['gas'] . '</td>';
                        }
                        if (!empty($item['energy'])) {
                            echo '<td>e: ' . $item['energy'] . '</td>';
                        }
                        if (!empty($item['dark_matter'])) {
                            echo '<td>dm: ' . $item['dark_matter'] . '</td>';
                        }
                echo '</tr></table>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public function defaultJson()
    {
        $res = [
            'cost' => [
                'constant' => [
                    'resources' => [
                        'metal' => 5,
                        'crystal' => 10,
                        'gas' => 3,
                        'energy' => 7,
                        'dark_matter' => 0,

                        'multiplier' => 1.55,
                    ],
                    'fields' => [
                        'base' => 1,

                        'multiplier' => 1,
                    ],
                ], //constant
                'formula' => [
                    [
                        'level' => 0,
                        'resources' => [
                            'metal' => '{$metal} * {$multiplier}**{$level}',
                            'crystal' => '{$crystal} * {$multiplier}**{$level}',
                            'gas' => '{$gas} * {$multiplier}**{$level}',
                            'energy' => '{$energy} * {$multiplier}**{$level}',
                            'dark_matter' => 1,
                        ],
                        'fields' => '$fields + $level',
                        'requires' => [
                            'building' => [
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                            ],
                            'technology' => [
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                            ],

                        ],
                    ],
                    [
                        'level' => 20,
                        'resources' => [
                            'metal' => '{$metal} * pow({$multiplier},{$level})',
                            'crystal' => '{$crystal} * ({$multiplier})**{$level}',
                            'gas' => '{$gas} * ({$multiplier})**{$level}',
                            'energy' => '{$energy} * ({$multiplier})**{$level}',
                            'dark_matter' => 1,
                        ],
                        'fields' => '$fields + $level',
                        'requires' => [
                            'building' => [
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                            ],
                            'technology' => [
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                                [
                                    'id' => 20,
                                    'level' => 20,
                                ],
                            ],
                        ],
                    ],
                ], //formula
            ], //cost
            'production' => [
                'constant' => [
                    'metal' => [
                        'base' => 1,
                        'multiplier' => 1,
                    ],
                    'crystal' => [
                        'base' => 1,
                        'multiplier' => 1,
                    ],
                    'gas' => [
                        'base' => 1,
                        'multiplier' => 1,
                    ],
                    'energy' => [
                        'base' => 1,
                        'multiplier' => 1,
                    ],
                    'dark_matter' => [
                        'base' => 1,
                        'multiplier' => 1,
                    ],
                ],
                'formula' => [
                    [
                        'level' => 0,
                        'metal' => '$metal * 1.55**$level',
                        'crystal' => '$metal * 1.55**$level',
                        'gas' => '$metal * 1.55**$level',
                        'energy' => '$metal * 1.55**$level',
                        'dark_matter' => 1,
                    ],
                    [
                        'level' => 20,
                        'metal' => '$metal * 1.15**$level',
                        'crystal' => '$metal * 1.15**$level',
                        'gas' => '$metal * 1.15**$level',
                        'energy' => '$metal * 1.15**$level',
                        'dark_matter' => 1,
                    ],
                ],
            ], //production
            'properties' => [
                'storage' => [
                    'constant' => [
                        'metal' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                        'crystal' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                        'gas' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'metal' => '$metal * 1.55**$level',
                            'crystal' => '$metal * 1.55**$level',
                            'gas' => '$metal * 1.55**$level',
                            'energy' => '$metal * 1.55**$level',
                            'dark_matter' => 1,
                        ],
                        [
                            'level' => 20,
                            'metal' => '$metal * 1.15**$level',
                            'crystal' => '$metal * 1.15**$level',
                            'gas' => '$metal * 1.15**$level',
                            'energy' => '$metal * 1.15**$level',
                            'dark_matter' => 1,
                        ],
                    ],
                ],//storage
                'technology_speed' => [
                    'constant' => [
                        'speed' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'speed' => '$speed * $multiplier**$level',
                        ],
                        [
                            'level' => 20,
                            'speed' => '$speed * ($multiplier/2)**$level',
                        ],
                    ],
                ],//technology_speed
                'building_speed' => [
                    'constant' => [
                        'speed' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'speed' => '$speed * $multiplier**$level',
                        ],
                        [
                            'level' => 20,
                            'speed' => '$speed * ($multiplier/2)**$level',
                        ],
                    ],
                ],//building_speed
                'rocket_storage' => [
                    'constant' => [
                        'interceptor' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                        'interstellar' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'interceptor' => '$interceptor * 1.55**$level',
                            'interstellar' => '$interstellar * 1.55**$level',
                        ],
                        [
                            'level' => 20,
                            'interceptor' => '$interceptor * 1.15**$level',
                            'interstellar' => '$interstellar * 1.15**$level',
                        ],
                    ],
                ],//rocket_storage
                'fields_growth' => [
                    'constant' => [
                        'fields' => [
                            'base' => 1,
                            'multiplier' => 1,
                        ],
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'fields' => '$fileds * 1.55**$level',
                        ],
                        [
                            'level' => 20,
                            'fields' => '$fields * 1.15**$level',
                        ],
                    ],
                ],//fields_growth

            ],
        ];

        return json_encode($res);
    }

}