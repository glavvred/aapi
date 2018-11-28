<?php

namespace App\Http\Controllers;

use App\Building;
use App\Planet;
use App\Ship;
use App\User;
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
     * @param int $planetId
     * @param int $buildingId
     * @return string
     */
    public function test(Request $request, int $level, int $planetId, int $buildingId)
    {
        $res = Building::find($buildingId);

        $data = $this->parseAll($request, $res, $level, $planetId);
        return json_encode($data);
    }

    /**
     * Parse all fields
     * @param $request Request
     * @param $jsonData object
     * @param int $level
     * @param int $planetId
     * @return array
     */
    public function parseAll(Request $request, object $jsonData, int $level = 1, int $planetId)
    {
        $res = json_decode($jsonData->resources);
        $req = json_decode($jsonData->requirements);
        $upg = json_decode($jsonData->upgrades);

        $props = [];
        if (!empty($jsonData->properties))
            $props = json_decode($jsonData->properties);

        if ((empty($res) && !empty($jsonData->resources)) ||
            (empty($req) && !empty($jsonData->requirements)) ||
            (empty($upg) && !empty($jsonData->upgrades)) ||
            (empty($props) && !empty($jsonData->properties))
        )
            throw new InvalidArgumentException('Json decode error: ' . json_last_error());

        if (empty($request->auth->id))
            $userId = 2; //todo: test, remove from production
        else
            $userId = $request->auth->id;

        $user = User::find($userId);

        //assuming NO variables except level in technology bonus
        $technologyBonus = $this->getAllCurrentTechnologiesBonus($user->id);

        //using calculated tech bonuses in building bonuses
        $techBuildingBonus = $this->getAllCurrentBuildingsBonus($technologyBonus, $planetId);

        //cost
        $cost = $this->parseCost($res, $level, $techBuildingBonus['bonus']);

        //production
        $production = $this->parseProduction($res, $level, $techBuildingBonus['bonus']);

        //requirements
        $requirements = $this->parseRequirements($req, $level, $techBuildingBonus['levels']);

        //upgrades
        $upgrades = $this->parseUpgrades($upg, $level, $techBuildingBonus['bonus']);

        //ship properties
        $properties = $this->parseProperties($props, $techBuildingBonus['bonus']);

        $res = ['cost' => $cost,
            'production' => $production,
            'requirements' => $requirements,
            'upgrades' => $upgrades,
        ];

        if (!empty($props))
            $res['properties'] = $properties;

        return $res;
    }

    /**
     * Gather all technologies with respective bonuses
     * !assuming NOT using any other building|tech bonuses!
     *
     * @param int $userId
     * @return array
     */
    public function getAllCurrentTechnologiesBonus(int $userId)
    {
        $user = User::find($userId);

        $technologyLevels = $user->technologies()
            ->wherePivot('owner_id', $userId)
            ->get(['upgrades', 'level', 'name']);

        $formulas = $constants = $result = $actualBonusByTech = $levels = [];

        foreach ($technologyLevels as $techId => $technologyLevel) {
            $level = $technologyLevel->level;

            //get all upgrades - pick most recent - pack as an array
            $categories = json_decode($technologyLevel->upgrades);
            foreach ($categories as $category) {
                foreach ($category as $key => $jsonDatum) {
                    //pick most recent constant pack
                    $currentConstants = [];
                    if (!empty($jsonDatum->constant)) {
                        foreach ($jsonDatum->constant as $levelConstant) {
                            if ($levelConstant->level > $level)
                                break;
                            else
                                $currentConstants = $levelConstant;
                        }
                    }

                    //pick most recent formula pack
                    $currentFormula = [];
                    if (!empty($jsonDatum->formula)) {
                        foreach ($jsonDatum->formula as $levelFormula) {
                            if ($levelFormula->level > $level)
                                break;
                            else
                                $currentFormula = $levelFormula;
                        }
                    }

                    //each value in constants goes to corresponding variable with respective name
                    foreach ($currentConstants as $ikey => $value) {
                        if ($ikey == 'level')
                            $constants[$key]['constants'][$ikey] = $level;
                        $constants[$techId][$key]['constants'][$ikey] = $value;
                    }

                    //each value in formulas goes to corresponding variable with respective name
                    foreach ($currentFormula as $ikey => $value) {
                        if ($ikey == 'level')
                            $constants[$key]['constants'][$ikey] = $level;
                        $formulas[$techId][$key]['formula'] = $value;
                    }
                }
            }
            if (!empty($level))
                $levels[$technologyLevel->name] = $level;
        }

        foreach ($formulas as $techId => $formula) { //tech
            foreach ($formula as $key => $resource) { //formula in tech
                $x = 0;

                $const = $constants[$techId][$key]['constants'];
                $level = $constants[$techId][$key]['constants']['level'];

                $string_processed = preg_replace_callback(
                    '~\{\$(.*?)\}~si',
                    function ($match) use ($const, $level) {
                        return eval('return $const[\'' . $match[1] . '\'];');
                    },
                    $resource['formula']);

                eval('$x = round(' . $string_processed . ");");

                $actualBonusByTech[$techId][$key] = $x;
            };
        }

        foreach ($actualBonusByTech as $bonusByTech) {
            foreach ($bonusByTech as $key => $item) {
                if (!empty($result[$key]))
                    $result[$key] += $item;
                else
                    $result[$key] = $item;
            }
        }

        return ['bonus' => $result, 'levels' => $levels];
    }

    /**
     * Gather all buildings with respective bonuses
     *
     * @param array $bonuses
     * @param int $planetId
     * @return array
     */
    public function getAllCurrentBuildingsBonus(array $bonuses, int $planetId)
    {
        $planet = Planet::find($planetId);

        $buildingLevels = $planet->buildings()
            ->wherePivot('planet_id', $planetId)
            ->get(['upgrades', 'level', 'name']);

        $formulas = $constants = $result = $levels = $sums = [];

        foreach ($buildingLevels as $buildingId => $buildingLevel) {
            $level = $buildingLevel->level;

            //get all upgrades - pick most recent - pack as an array
            $categories = json_decode($buildingLevel->upgrades);
            foreach ($categories as $category) {
                foreach ($category as $key => $jsonDatum) {
                    //pick most recent constant pack
                    $currentConstants = [];
                    if (!empty($jsonDatum->constant)) {
                        foreach ($jsonDatum->constant as $levelConstant) {
                            if ($levelConstant->level > $level)
                                break;
                            else
                                $currentConstants = $levelConstant;
                        }
                    }

                    //pick most recent formula pack
                    $currentFormula = [];
                    if (!empty($jsonDatum->formula)) {
                        foreach ($jsonDatum->formula as $levelFormula) {
                            if ($levelFormula->level > $level)
                                break;
                            else
                                $currentFormula = $levelFormula;
                        }
                    }

                    //each value in constants goes to corresponding variable with respective name
                    foreach ($currentConstants as $ikey => $value) {
                        if ($ikey == 'level')
                            $constants[$buildingId][$key]['constants'][$ikey] = $level;
                        $constants[$buildingId][$key]['constants'][$ikey] = $value;
                    }

                    //each value in formulas goes to corresponding variable with respective name
                    foreach ($currentFormula as $ikey => $value) {
                        if ($ikey == 'level')
                            $constants[$buildingId][$key]['constants'][$ikey] = $level;
                        $formulas[$buildingId][$key]['formula'] = $value;
                    }
                }
            }

            if (!empty($level))
                $levels[$buildingLevel->name] = $level;
        }

        $actualBonusByBuilding = [];

        foreach ($formulas as $buildingId => $formula) { //building
            foreach ($formula as $key => $resource) { //formula in building
                $x = 0;

                $const = $constants[$buildingId][$key]['constants'];
                $const = array_merge($const, $bonuses['bonus']);

                $level = $const['level'];

                $string_processed = preg_replace_callback(
                    '~\{\$(.*?)\}~si',
                    function ($match) use ($const, $level) {
                        return eval('return $const[\'' . $match[1] . '\'];');
                    },
                    $resource['formula']);

                eval('$x = round(' . $string_processed . ");");

                $actualBonusByBuilding[$buildingId][$key] = $x;
            }
        };

        //compress building bonuses
        foreach ($actualBonusByBuilding as $bonusByBuilding) {
            foreach ($bonusByBuilding as $key => $item) {
                if (!empty($result[$key]))
                    $result[$key] += $item;
                else
                    $result[$key] = $item;
            }
        }

        $bonus = $bonuses['bonus'];
        foreach (array_keys($result + $bonus) as $key) {
            $sums[$key] = (isset($result[$key]) ? $result[$key] : 0) + (isset($bonus[$key]) ? $bonus[$key] : 0);
        }

        return ['bonus' => $sums, 'levels' => array_merge($levels, $bonuses['levels'])];
    }

    /**
     * Parse cost helper
     *
     * @param $jsonDecoded
     * @param array $bonuses calculated bonuses from technologies and buildings with levels
     * @param int $level
     * @return array
     */
    private function parseCost($jsonDecoded, int $level, $bonuses)
    {
        //pick most recent constant pack
        $currentConstants = $costResources = [];

        foreach ($jsonDecoded->cost->constant as $levelConstant) {
            if ($levelConstant->level > $level)
                break;
            else
                $currentConstants = $levelConstant;
        }

        //pick most recent formula pack
        $currentFormula = [];

        foreach ($jsonDecoded->cost->formula as $levelFormula) {
            if ($levelFormula->level > $level)
                break;
            else
                $currentFormula = $levelFormula;
        }

        $constants = $bonuses;
        //each value in constants goes to corresponding variable with respective name
        foreach ($currentConstants as $key => $value) {
            $constants[$key] = $value;
        }
        $constants['level'] = $level;

        foreach ($currentFormula as $key => $resource) {
            if ($key == 'level')
                continue;
            $x = 0;

            $string_processed = preg_replace_callback(
                '~\{\$(.*?)\}~si',
                function ($match) use ($constants) {
                    return eval('return $constants[\'' . $match[1] . '\'];');
                },
                $resource);

            eval('$x = round(' . $string_processed . ");");
            $costResources[$key] = $x;
        };

        $costResources['time'] = round(array_sum($costResources) / 10);

        return $costResources;
    }

    /**
     * Parse production helper
     *
     * @param array $bonuses
     * @param $jsonDecoded
     * @param int $level
     * @return array
     */
    private function parseProduction($jsonDecoded, int $level, $bonuses)
    {
        $productionResources = [];

        //pick most recent constant pack
        $currentConstants = [];
        foreach ($jsonDecoded->production->constant as $levelConstant) {
            if ($levelConstant->level > $level)
                break;
            else
                $currentConstants = $levelConstant;
        }

        //pick most recent formula pack
        $currentFormula = [];
        foreach ($jsonDecoded->cost->formula as $levelFormula) {
            if ($levelFormula->level > $level)
                break;
            else
                $currentFormula = $levelFormula;
        }

        //adding bonuses from other entites as constants
        $constants = $bonuses;

        //each value in constants goes to corresponding variable with respective name
        foreach ($currentConstants as $key => $value) {
            $constants[$key] = $value;
        }
        $constants['level'] = $level;

        foreach ($currentFormula as $key => $resource) {

            $x = 0;

            $string_processed = preg_replace_callback(
                '~\{\$(.*?)\}~si',
                function ($match) use ($constants) {
                    return eval('return $constants[\'' . $match[1] . '\'];');
                },
                $resource);

            eval('$x = round(' . $string_processed . ");");
            $productionResources[$key] = $x;
        };

        $productionResources['time'] = round(array_sum($productionResources) / 10);

        return $productionResources;
    }

    /**
     * Parse Requirements helper
     *
     * @param $techBuildingBonus
     * @param $jsonDecoded
     * @param int $level
     * @return array
     */
    private function parseRequirements($jsonDecoded, int $level, $techBuildingBonus)
    {

        $res = [];
        $cConstants['level'] = $level;

        foreach ($jsonDecoded as $key => $category) { //building/tech/etc
            if (!empty($category->formula) && !empty($category->constant)) {

                //pick most recent formula pack
                $currentFormula = [];
                foreach ($category->formula as $levelFormula) {
                    if ($levelFormula->level > $level)
                        break;
                    else
                        $currentFormula = $levelFormula;
                }

                //pick most recent constant pack
                $currentConstants = [];
                foreach ($category->constant as $levelConstant) {
                    if ($levelConstant->level > $level)
                        break;
                    else {
                        if ($levelConstant == "level")
                            continue;

                        $currentConstants[] = $levelConstant;

                    }
                }

                foreach ($currentConstants[0] as $kkey => $currentConstant) {
                    if ($kkey == "level")
                        continue;
                    $cConstants[$kkey] = $currentConstant;
                }

                $constants = array_merge($cConstants, $techBuildingBonus);

                foreach ($currentFormula as $fkey => $building) {
                    if ($fkey == 'level')
                        continue;

                    $string_processed = preg_replace_callback(
                        '~\{\$(.*?)\}~si',
                        function ($match) use ($constants) {
                            return eval('return $constants[\'' . $match[1] . '\'];');
                        },
                        $building);

                    $x = 0;
                    eval ('$x = ' . $string_processed . ';');

                    $res[$key][$fkey] = $x;
                }
            }
        }

        return $res;
    }

    /**
     * Parse Requirements helper
     *
     * @param array $techBuildingBonus
     * @param $jsonDecoded
     * @param int $level
     * @return array
     */
    private function parseUpgrades($jsonDecoded, int $level, $techBuildingBonus)
    {
        $res = [];
        $cConstants['level'] = $level;

        foreach ($jsonDecoded as $key => $category) { //building/tech/etc
            foreach ($category as $cKey => $item) { //building in buildings, tech in technologies etc

                if (!empty($item->formula) && !empty($item->constant)) {
                    //pick most recent formula pack
                    $currentFormula = [];
                    foreach ($item->formula as $levelFormula) {
                        if ($levelFormula->level > $level)
                            break;
                        else
                            $currentFormula = $levelFormula;
                    }

                    //pick most recent constant pack
                    $currentConstants = [];
                    foreach ($item->constant as $levelConstant) {
                        if ($levelConstant->level > $level)
                            break;
                        else {
                            if ($levelConstant == "level")
                                continue;

                            $currentConstants[] = $levelConstant;

                        }
                    }

                    foreach ($currentConstants[0] as $kkey => $currentConstant) {
                        if ($kkey == "level")
                            continue;
                        $cConstants[$kkey] = $currentConstant;
                    }

                    $constants = array_merge($cConstants, $techBuildingBonus);

                    foreach ($currentFormula as $fkey => $building) {
                        if ($fkey == 'level')
                            continue;

                        $string_processed = preg_replace_callback(
                            '~\{\$(.*?)\}~si',
                            function ($match) use ($constants) {
                                return eval('return $constants[\'' . $match[1] . '\'];');
                            },
                            $building);

                        $x = 0;
                        eval ('$x = ' . $string_processed . ';');

                        $res[$key][$fkey] = $x;
                    }
                }
            }
        }

        return $res;
    }

    /**
     * Parse ship properties helper
     *
     * @param array $techBuildingBonus
     * @param $jsonDecoded
     * @return array
     */
    private function parseProperties($jsonDecoded, $techBuildingBonus)
    {
        $res = [];

        foreach ($jsonDecoded as $key => $category) { //combat/navigation/etc
            foreach ($category as $cKey => $item) { //attack in combat, etc
                if (!empty($item->formula) && !empty($item->constant)) {
                    //assuming there is only one formula and no level slices
                    $cConstants = [];
                    foreach ($item->constant[0] as $kkey => $currentConstant) {
                        $cConstants[$kkey] = $currentConstant;
                    }

                    $constants = array_merge($cConstants, $techBuildingBonus);

                    foreach ($item->formula[0] as $fkey => $ship) {

                        $string_processed = preg_replace_callback(
                            '~\{\$(.*?)\}~si',
                            function ($match) use ($constants) {
                                return eval('return $constants[\'' . $match[1] . '\'];');
                            },
                            $ship);

                        $x = 0;
                        eval ('$x = ' . $string_processed . ';');

                        $res[$key][$fkey] = $x;
                    }
                }
            }
        }

        return $res;
    }

    /**
     * level 1 - 100 characteristics table printout
     * @param Request $request
     * @param int $planetId
     * @param int $buildingId
     */
    public function testMany(Request $request, int $planetId, int $buildingId)
    {
        $res = Building::find($buildingId);

        echo '<table border="1" width="100%">';
        for ($level = 0; $level < 101; $level++) {
            echo '<tr><td width="40px"> level: ' . $level . '</td>';
            echo '<td>';
            foreach ($this->parseAll($request, $res, $level, $planetId) as $key => $item) {
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
                if (!empty($item['time'])) {
                    echo 'time: ' . $item['time'] . '<br>';
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
                    foreach ($item as $uKey => $uItem) { //upgrade type
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
     * ship characteristics table printout
     * @param Request $request
     * @param int $planetId
     * @param int $shipId
     */
    public function testShip(Request $request, int $planetId, int $shipId)
    {
        $res = Ship::find($shipId);

        $parsed = $this->parseAll($request, $res, 1, $planetId);

        echo '<table border="1" width="100%">';
        echo '<tr><td>';
        foreach ($parsed as $key => $item) {
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
            if (!empty($item['time'])) {
                echo 'time: ' . $item['time'] . '<br>';
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
                foreach ($item as $uKey => $uItem) { //upgrade type
                    if ($uKey == 'level')
                        continue;
                    echo '<b>' . $uKey . '</b><br>';
                    foreach ($uItem as $uuKey => $uuItem) {
                        echo $uuKey . " : " . $uuItem . '<br> ';
                    }
                }
                echo '</td>';
            }
            if ($key == 'properties') {
                echo '<td>';
                foreach ($item as $uKey => $uItem) { //upgrade type
                    echo '<b>' . $uKey . '</b><br>';
                    foreach ($uItem as $uuKey => $uuItem) {
                        if ($uuKey == 'level')
                            continue;

                        echo $uuKey . " : " . $uuItem . '<br> ';
                    }
                }
                echo '</td>';
            }

            echo '</tr></table>';
        }
        echo '</td>';
        echo '</tr>';
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
                    [
                        'level' => 0,

                        'metal' => 5,
                        'crystal' => 10,
                        'gas' => 3,
                        'energy' => 7,
                        'time' => 10,
                        'dark_matter' => 0,
                        'multiplier' => 1.55,
                    ]
                ], //constant
                'formula' => [
                    [
                        'level' => 0,
                        'metal' => '{$metal} * {$multiplier}**{$level}',
                        'crystal' => '{$crystal} * {$multiplier}**{$level}',
                        'gas' => '{$gas} * {$multiplier}**{$level}',
                        'time' => '({$metal} + {$crystal} + {$gas}) / 10',
                        'energy' => '{$energy} * {$multiplier}**{$level}',
                        'dark_matter' => 1,
                    ],
                    [
                        'level' => 20,
                        'metal' => '{$metal} * pow({$multiplier},{$level})',
                        'crystal' => '{$crystal} * ({$multiplier})**{$level}',
                        'gas' => '{$gas} * ({$multiplier})**{$level}',
                        'energy' => '{$energy} * ({$multiplier})**{$level}',
                        'dark_matter' => 1,
                    ]
                ]
            ],
            'production' => [
                'constant' => [
                    [
                        'level' => 0,
                        'metal' => 1,
                        'crystal' => 2,
                        'gas' => 1,
                        'energy' => -1,
                        'multiplier' => 1.55,
                    ],
                ],//constant
                'formula' => [
                    [
                        'level' => 0,
                        'metal' => '{$metal} * {$multiplier}**{$level}',
                        'crystal' => '{$metal} * {$multiplier}**{$level}',
                        'gas' => '{$metal} * {$multiplier}**{$level}',
                        'energy' => '{$metal} * {$multiplier}**{$level}',
                    ],
                    [
                        'level' => 20,
                        'metal' => '{$metal} * {$multiplier}**{$level}',
                        'crystal' => '{$metal} * {$multiplier}**{$level}',
                        'gas' => '{$metal} * {$multiplier}**{$level}',
                        'energy' => '{$metal} * {$multiplier}**{$level}',
                    ],
                ],//formula
            ], //production
            'storage' => [
                'constant' => [
                    [
                        'level' => 0,
                        'metal' => 10,
                        'crystal' => 12,
                        'gas' => 10,

                        'multiplier' => 1.15,
                    ],
                ],//constant
                'formula' => [
                    [
                        'level' => 0,
                        'metal' => '{$metal} * {$multiplier}**{$level}',
                        'crystal' => '{$metal} * {$multiplier}**{$level}',
                        'gas' => '{$metal} * {$multiplier}**{$level}',
                        'energy' => '{$metal} * {$multiplier}**{$level}',
                    ],
                    [
                        'level' => 20,
                        'metal' => '{$metal} * {$multiplier}**{$level}',
                        'crystal' => '{$metal} * {$multiplier}**{$level}',
                        'gas' => '{$metal} * {$multiplier}**{$level}',
                        'energy' => '{$metal} * {$multiplier}**{$level}',
                    ],
                ],//formula
            ],//storage
        ];

        return response()->json($res, 200);

    }

    /**
     * default json output for building 'requirements' field
     * @return string
     */
    public function defaultJsonRequirements()
    {
        $res = [
            'technology' => [
                'constant' => [
                    [
                        'level' => 0,
                        'multiplier' => 2,
                    ]
                ],
                'formula' => [
                    [
                        'level' => 0,
                    ],
                    [
                        'level' => 1,
                        'speed' => '{$level} - 1',
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
                        'speed' => '{$level}/{$multiplier}',
                        'self' => '{$level} - 1',
                        'location' => '10',
                        'defence' => '1',
                    ],
                ],
            ],
            'building' => [
                'constant' => [
                    [
                        'level' => 0,
                        'multiplier' => 2,
                    ]
                ],
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
                        'fusion' => '10*{$multiplier}',
                        'terraformer' => '1',
                    ],
                ],
            ]
        ];

        return response()->json($res, 200);
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
                        [
                            'level' => 0,
                            'multiplier' => '1.1',
                        ],
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
                        [
                            'level' => 0,
                            'multiplier' => '10',
                        ],
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
                        [
                            'level' => 0,
                            'multiplier' => '2',
                        ],
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
                        [
                            'level' => 0,
                            'multiplier' => '1.01',
                        ],
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

        return response()->json($res, 200);
    }

    /**
     * default json output for ship 'properties' field
     * @return string
     */
    public function defaultJsonProperties()
    {
        $res = [
            'combat' => [
                'attack' => [
                    'constant' => [
                        [
                            'multiplier' => '1.1',
                        ],
                    ],
                    'formula' => [
                        [
                            'attack' => '{$light_fighter_attack} * {$multiplier}',
                        ],
                    ],
                ],//attack
                'armor' => [
                    'constant' => [
                        [
                            'multiplier' => '10',
                        ],
                    ],
                    'formula' => [
                        [
                            'armor' => '{$light_fighter_armor} * {$multiplier}',
                        ],
                    ],
                ],//armor
                'shield' => [
                    'constant' => [
                        [
                            'multiplier' => '2',
                        ],
                    ],
                    'formula' => [
                        [
                            'shield' => '{$light_fighter_shield} * {$multiplier}',
                        ],
                    ],
                ],//shield
            ],//combat
            'navigation' => [
                'speed' => [
                    'constant' => [
                        [
                            'multiplier' => '1',
                        ],
                    ],
                    'formula' => [
                        [
                            'speed' => '{$light_fighter_speed} * {$multiplier}',
                        ],
                    ],
                ],//speed
            ],//combat
        ];

        return response()->json($res, 200);
    }

}