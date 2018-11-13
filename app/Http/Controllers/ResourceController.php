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

    /**
     * print characteristics for given level of givin building
     * @param Request $request
     * @param int $level
     * @param int $bid
     * @return string
     */
    public function test(Request $request, int $level, int $bid)
    {
        $res = Building::find($bid);

        return json_encode($this->parseAll(($res), $level));
    }

    /**
     * Parse all fields (test)
     * @param $jsonData
     * @param int $level
     * @return array
     */
    public function parseAll($jsonData, int $level = 1)
    {
        $res = json_decode($jsonData->resources);
        $req = json_decode($jsonData->requirements);
        $upg = json_decode($jsonData->upgrades);

        if (empty($res) || empty($req) || empty($upg))
            throw new InvalidArgumentException('Json decode error: ' . json_last_error());

        //cost
        $cost = $this->parseCost($res, $level);
        //production
        $production = $this->parseProduction($res, $level);

        //requirements
        $requirements = $this->parseRequirements($req, $level);

        //upgrades
        $upgrades = $this->parseUpgrades($upg, $level);

        return ['cost' => $cost,
            'production' => $production,
            'requirements' => $requirements,
            'upgrades' => $upgrades,
        ];
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

            $x = (0);

            $string_processed = preg_replace_callback(
                '~\{\$(.*?)\}~si',
                function ($match) use ($metal, $crystal, $gas, $energy, $multiplier, $level) {
                    return eval('return $' . $match[1] . ';');
                },
                $resource);

            eval('$x = round(' . $string_processed . ",2);");
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

            eval('$res = round(' . $string_processed . ", 2);");
            $productionResources[$key] = $res;

        };
        return $productionResources;
    }

    /**
     * Parse Requirements helper
     *
     * @param $jsonDecoded
     * @param int $level
     * @return array
     */
    private function parseRequirements($jsonDecoded, int $level)
    {
        $res = [];

        $building = $jsonDecoded->building;
        $technology = $jsonDecoded->technology;

        $levelBuildingFormulas = $building->formula;
        $levelTechFormulas = $technology->formula;

        //building requirements
        $currentBRFormula = [];
        foreach ($levelBuildingFormulas as $levelFormula) {
            if ($levelFormula->level > $level)
                break;
            else
                $currentBRFormula = $levelFormula;
        }

        foreach ($currentBRFormula as $key => $building) {
            if ($key == 'level')
                continue;

            $string_processed = preg_replace_callback(
                '~\{\$(.*?)\}~si',
                function ($match) use ($level) {
                    return eval('return $' . $match[1] . ';');
                },
                $building);

            $x = 0;
            eval ('$x = ' . $string_processed . ';');

            $res['building'][$key] = $x;
        }

        //building requirements
        $currentTRFormula = [];
        foreach ($levelTechFormulas as $levelFormula) {
            if ($levelFormula->level > $level)
                break;
            else
                $currentTRFormula = $levelFormula;
        }

        foreach ($currentTRFormula as $key => $technology) {
            if ($key == 'level')
                continue;

            $string_processed = preg_replace_callback(
                '~\{\$(.*?)\}~si',
                function ($match) use ($level) {
                    return eval('return $' . $match[1] . ';');
                },
                $technology);

            $x = 0;
            eval ('$x = ' . $string_processed . ';');

            $res['technology'][$key] = $x;
        }


        return $res;
    }

    /**
     * Parse Requirements helper
     *
     * @param $jsonDecoded
     * @param int $level
     * @return array
     */
    private function parseUpgrades($jsonDecoded, int $level)
    {
        $res = [];

        foreach ($jsonDecoded as $typeName => $upgradeType) {
            foreach ($upgradeType as $item) {
                if (empty($item->formula))
                    continue;

                if (!empty($item->constant->multiplier))
                    $multiplier = $item->constant->multiplier;

                $levelUpgradeFormulas = $item->formula;

                //building requirements
                $currentUpgradeFormula = [];
                foreach ($levelUpgradeFormulas as $levelFormula) {
                    if ($levelFormula->level > $level)
                        break;
                    else
                        $currentUpgradeFormula = $levelFormula;
                }

                foreach ($currentUpgradeFormula as $key => $upgrade) {
                    if ($key == 'level')
                        continue;

                    echo '<Br>';

                    $string_processed = preg_replace_callback(
                        '~\{\$(.*?)\}~si',
                        function ($match) use ($level, $multiplier) {
                            return eval('return $' . $match[1] . ';');
                        },
                        $upgrade);

                    $x = 0;
                    eval ('$x = ' . $string_processed . ';');
                    $res['upgrade'][$typeName][$key] = $x;
                }
            }
        }

        return $res;
    }

    /**
     * level 0 - 99 characteristics table printout
     * @param int $bid
     */
    public function testMany(int $bid)
    {
        $res = Building::find($bid);

        echo '<table border="1" width="100%">';
        for ($level = 0; $level < 100; $level++) {
            echo '<tr><td width="40px"> level: ' . $level . '</td>';
            echo '<td>';
            foreach ($this->parseAll(($res), $level) as $key => $item) {
                echo '<table border="1" style="float: left; width: 22%;">
                    <tr><th>' . $key . '</th><td>';
                if (!empty($item['metal'])) {
                    echo 'm: ' . $item['metal'] . '<br>';
                }
                if (!empty($item['crystal'])) {
                    echo 'c: ' . $item['crystal'] . '<br>';
                }
                if (!empty($item['gas'])) {
                    echo 'g: ' . $item['gas'] . '<br>';
                }
                if (!empty($item['energy'])) {
                    echo 'e: ' . $item['energy'] . '<br>';
                }
                if (!empty($item['dark_matter'])) {
                    echo 'dm: ' . $item['dark_matter'] . '<br>';
                }

                if ($key == 'requirements') {
                    echo '<td>';
                    if (!empty($item['building'])) {
                        echo 'building: ';
                        foreach ($item['building'] as $reqKey => $req) {
                            if ($reqKey == 'level')
                                continue;
                            echo $reqKey . " : " . $req . '<br> ';
                        }
                    }
                    if (!empty($item['technology'])) {
                        echo 'technology: ';
                        foreach ($item['technology'] as $reqKey => $req) {
                            if ($reqKey == 'level')
                                continue;
                            echo $reqKey . " : " . $req . '<br> ';
                        }
                    }
                    echo '</td>';
                }
                if ($key == 'upgrades') {
                    echo '<td>';
                    foreach ($item['upgrade'] as $uKey => $uItem) { //upgrade type
                        if ($uKey == 'level')
                            continue;
                        echo '<b>' . $uKey . '</b><br>';
                        foreach ($uItem as $uuKey => $uuItem) {
                            echo $uuKey . " : " . $uuItem . '<br> ';
                        }
                    }
                    echo '</td>';
                }

                echo '</tr></table>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    /**
     * default json output for building 'resources' field
     * @return string
     */
    public function defaultJsonResources()
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
                ],//constant
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
                ],//formula
            ], //production
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
                ],//constant
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
                ],//formula
            ],//storage
        ];

        return json_encode($res);
    }

    /**
     * default json output for building 'requirements' field
     * @return string
     */
    public function defaultJsonRequirements()
    {
        $res = [
            'technology' => [
                'formula' => [
                    [
                        'level' => 0,
                    ],
                    [
                        'level' => 1,
                        'speed' => '{$level} - 1', //tech - level
                        'self' => '{$level} - 1',
                    ],
                    [
                        'level' => 10,
                        'speed' => '{$level} - 1',
                        'self' => '{$level} - 1',
                        'location' => '1',
                    ],
                    [
                        'level' => 15,
                        'speed' => '{$level} - 1',
                        'self' => '{$level} - 1',
                        'location' => '10',
                        'defence' => '1',
                    ],
                ],
            ],
            'building' => [
                'formula' => [
                    [
                        'level' => 0,
                    ],
                    [
                        'level' => 3,
                        'mine' => '{$level} - 2', //tech - level
                    ],
                    [
                        'level' => 10,
                        'mine' => '{$level} - 2',
                        'fusion' => '1',
                    ],
                    [
                        'level' => 15,
                        'mine' => '{$level} - 2',
                        'fusion' => '10',
                        'terraformer' => '1',
                    ],
                ],
            ]
        ];

        return json_encode($res);
    }

    /**
     * default json output for building 'upgrades' field
     * @return string
     */
    public function defaultJsonUpgrades()
    {
        $res = [
            'planet' => [
                'building_speed' => [
                    'constant' => [
                        'multiplier' => '1.1',
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'speed' => '{$level} * {$multiplier}',
                        ],
                        [
                            'level' => 20,
                            'speed' => '({$level} + 1) * {$multiplier}',
                        ],
                    ],
                ],//technology_speed
                'rocket_capacity' => [
                    'constant' => [
                        'multiplier' => '10',
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'rocket_capacity' => '{$level} * {$multiplier}',
                        ],
                        [
                            'level' => 20,
                            'rocket_capacity' => '({$level} + 1) * {$multiplier}',
                        ],
                    ],
                ],//rocket_storage
                'fields_growth' => [
                    'constant' => [
                        'multiplier' => '2',
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'fields_growth' => '{$level} * {$multiplier}',
                        ],
                        [
                            'level' => 20,
                            'fields_growth' => '({$level} + 1) * {$multiplier}',
                        ],
                    ],
                ],//fields_growth
            ],//planet
            'technology' => [
                'research_speed' => [
                    'constant' => [
                        'multiplier' => '1.01',
                    ],
                    'formula' => [
                        [
                            'level' => 0,
                            'speed' => '{$level} ** {$multiplier}',
                        ],
                        [
                            'level' => 20,
                            'speed' => '({$level} + 1) ** {$multiplier}',
                        ],
                    ],
                ],
                'navigation' => [],
                'combat' => [],
            ],
        ];

        return json_encode($res);
    }

}