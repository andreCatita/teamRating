<?php

/**
 * 
 * ELO Team Rating ( 5 versus 5 )
 * Copyright (c) 2012   André Catita    http://andrecatita.com
 * http://github.com/andreCatita/
 * 
 * Mostly Inspired by Heroes of Newerth
 * 
 *  GNU General Public License, version 2 (GPL-2.0)
 *  http://opensource.org/licenses/GPL-2.0
 * 
 */
class teamRating {

    private $teamk_rank_weight = 6.5;
    private $k_factor_scale = 8;
    private $media_scale_rank = 1600;
    private $base_k_factor = 20;
    private $logistic_prediction_scale = 80;
    private $max_k_factor = 40;
    private $min_k_factor = 10;
    private $gamma_curve_k = 18;
    private $gamma_curve_theta = 5;
    private $pow_fix = '0.15384615384615384615384615384615'; // Hard-coded, compatibility issues
    private $teamA = null;
    private $teamAvalue = 0;
    private $teamAwinRate = 0;
    private $teamB = null;
    private $teamBvalue = 0;
    private $teamBwinRate = 0;

    public function setTeams($teamA, $teamB) {
	$this->teamA = $teamA;
	$this->teamB = $teamB;
	$this->calculateTeamsValueAndWinRating();
    }

    public function calculate() {
	$output = array();
	// Calculate Team A
	foreach ($this->teamA as $player) {
	    $elo = $player['elo'];
	    // Base K Factor
	    $k_factor = ( ($elo < $this->media_scale_rank) ? min(max(floor((($this->media_scale_rank - $elo) / $this->k_factor_scale) + $this->base_k_factor), $this->min_k_factor), $this->max_k_factor) : max(ceil(max((($this->media_scale_rank - $elo) / $this->k_factor_scale) + $this->base_k_factor, 0)), $this->min_k_factor));
	    // Rating Over Average
	    $rating_over_average = max(0, $elo - (round(($this->calculateTotalTeamELO($this->teamA)) / 5)));
	    // Cumulative
	    // w/ manual tweaks to compensate the PHP ability to calculate the gamma functions
	    $cumulative = ($rating_over_average <= 36 ? 0 : ( ($rating_over_average > 36 && $rating_over_average < 177) ? $this->incompleteGamma($this->gamma_curve_k, $rating_over_average / $this->gamma_curve_theta) / $this->gamma($this->gamma_curve_k) : 1 ) );
	    // Factor
	    $factor = sqrt(1 - $cumulative);
	    // Adjusted K Factor
	    $adjusted_k_factor = $k_factor * $factor;
	    // Calculate Gain Points
	    $player['gain'] = round($this->teamBwinRate * $adjusted_k_factor);
	    // Calculate Loss Points
	    $player['loss'] = round($this->teamAwinRate * $adjusted_k_factor);
	    $output['teamA'][] = $player;
	}
	// Calculate Team B
	foreach ($this->teamB as $player) {
	    $elo = $player['elo'];
	    // Base K Factor
	    $k_factor = ( ($elo < $this->media_scale_rank) ? min(max(floor((($this->media_scale_rank - $elo) / $this->k_factor_scale) + $this->base_k_factor), $this->min_k_factor), $this->max_k_factor) : max(ceil(max((($this->media_scale_rank - $elo) / $this->k_factor_scale) + $this->base_k_factor, 0)), $this->min_k_factor));
	    // Rating Over Average
	    $rating_over_average = max(0, $elo - (round(($this->calculateTotalTeamELO($this->teamB)) / 5)));
	    // Cumulative (w/ manual tweaks to compensate the PHP ability to calculate the imcomplete gamma properly)
	    $cumulative = ($rating_over_average <= 36 ? 0 : ( ($rating_over_average > 36 && $rating_over_average < 177) ? $this->incompleteGamma($this->gamma_curve_k, $rating_over_average / $this->gamma_curve_theta) / $this->gamma($this->gamma_curve_k) : 1 ) );
	    // Factor
	    $factor = sqrt(1 - $cumulative);
	    // Adjusted K Factor
	    $adjusted_k_factor = $k_factor * $factor;
	    // Calculate Gain Points
	    $player['gain'] = round($this->teamAwinRate * $adjusted_k_factor);
	    // Calculate Loss Points
	    $player['loss'] = round($this->teamBwinRate * $adjusted_k_factor);
	    $output['teamB'][] = $player;
	}
	return $output;
    }

    public function getTeamAWinRate() {
	return $this->teamAwinRate;
    }

    public function getTeamBWinRate() {
	return $this->teamBwinRate;
    }

    private function calculateTotalTeamELO($team) {
	$total = 0;
	foreach ($team as $player)
	    $total += $player['elo'];
	return $total;
    }

    private function calculateTeamsValueAndWinRating() {
	$teamAvalue = 0;
	$teamBvalue = 0;

	foreach ($this->teamA as $player)
	    $teamAvalue += pow($player['elo'], $this->teamk_rank_weight);

	foreach ($this->teamB as $player)
	    $teamBvalue += pow($player['elo'], $this->teamk_rank_weight);

	$this->teamAvalue = round(pow($teamAvalue, $this->pow_fix));
	$this->teamBvalue = round(pow($teamBvalue, $this->pow_fix));

	$this->teamAwinRate = round((1 / (1 + exp(-($this->teamAvalue - $this->teamBvalue) / $this->logistic_prediction_scale))), 2, PHP_ROUND_HALF_ODD);
	$this->teamBwinRate = round((1 / (1 + exp(-($this->teamBvalue - $this->teamAvalue) / $this->logistic_prediction_scale))), 2, PHP_ROUND_HALF_ODD);
    }

    private function incompleteGamma($a, $x) {
	static $max = 32;
	$summer = 0;
	for ($n = 0; $n <= $max; ++$n) {
	    $divisor = $a;
	    for ($i = 1; $i <= $n; ++$i) {
		$divisor *= ($a + $i);
	    }
	    $summer += (pow($x, $n) / $divisor);
	}
	return pow($x, $a) * exp(0 - $x) * $summer;
    }

    private function gamma($data) {
	if ($data == 0.0)
	    return 0;
	static $sqrt2pi = 2.5066282746310005024157652848110452530069867406099;
	static $p0 = 1.000000000190015;
	static $p = array(1 => 76.18009172947146, 2 => -86.50532032941677, 3 => 24.01409824083091, 4 => -1.231739572450155, 5 => 1.208650973866179e-3, 6 => -5.395239384953e-6);

	$y = $x = $data;
	$tmp = $x + 5.5;
	$tmp -= ($x + 0.5) * log($tmp);

	$summer = $p0;
	for ($j = 1; $j <= 6; ++$j) {
	    $summer += ($p[$j] / ++$y);
	}
	return exp(0 - $tmp + log($sqrt2pi * $summer / $x));
    }

}

/*
 * Usage Example
 * 
 * Two arrays needed with the column elo of the players.
 * You can pass another identifying parameters such as id, or name inside the array
 * 
 * The same array will be returned in the end with the addition of a gain and loss column, which contains the respective points.
 * 
 */
$teamRating = new teamRating;

$teamA = array(
    array("elo" => 1667),
    array("elo" => 1432),
    array("elo" => 1626),
    array("elo" => 1559),
    array("elo" => 1714)
);

$teamB = array(
    array("elo" => 1495),
    array("elo" => 1500),
    array("elo" => 1488),
    array("elo" => 1477),
    array("elo" => 1437)
);

$teamRating->setTeams($teamA, $teamB);
$return = $teamRating->calculate();

// After you declare the teams, you can obtain the team's probability
echo "Team A WinRate Probablity is: " . ($teamRating->getTeamAWinRate() * 100) . "%";
echo "<br />";
echo "Team B WinRate Probablity is: " . ($teamRating->getTeamBWinRate() * 100) . "%";

echo "<pre>";
print_r($return);
echo "</pre>";


/*
 * 
 * End of Team ELO Rating
 * 
 */