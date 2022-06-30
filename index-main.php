<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<link rel="stylesheet" type="text/css" href="master.css">
<?php
include 'definitions.php';

$competition = array_key_exists( 'competition', $_GET ) ? $_GET['competition'] : 'Y2022';
include $competition.'_info.php';
$mcats = make_categories($categories,$closed);

$refresh = array_key_exists( 'refresh', $_GET );
$finalize = array_key_exists( 'finalize', $_GET );

?>
 <title><?php echo $title; ?></title>
</head>
<body>
 <h1><?php echo $title; ?>
 <span class="headerFollower">
  <?php echo $note.PHP_EOL; ?>
  <span id=configToggler class=button></span>
  <span id=scoreToggler class=button></span>
  <span id=columnToggler class=button></span>
 </span>
</h1>
<script>
var configToggler = StyleToggler(
	document.getElementById("configToggler"), ".config", [
		{ text: "Show configs", assign: { display: "none" } },
		{ text: "Hide configs", assign: { display: "" } },
	],
	<?php echo $showconfig ? '1' : '0'; ?>
);
var scoreToggler = StyleToggler(
	document.getElementById("scoreToggler"), ".score", [
		{ text: "Show scores", assign: { display: "none" } },
		{ text: "Hide scores", assign: { display: "" } },
	],
	<?php echo $showscore ? '1' : '0'; ?>
);
var columnToggler = StyleToggler(
	document.getElementById("columnToggler"), "span.category", [
		{ text: "One column", assign: { display: "inline" } },
		{ text: "Many column", assign: { display: "block" } },
	]
);
// team scoring
var teamScores = [<?php foreach($teams as $team) { echo '[],'; } ?>];
const solver_id2team_id = {
<?php
$team_id = 0;
foreach( $teams as $team => $solvers ) {
	foreach( $mcats as $mcat => $cats ) {
		foreach( $cats as $cat => $info ) {
			foreach( $solvers as $solver ) {
				if( array_key_exists($solver,$info['participants']) ) {
					echo '	"'.$info['participants'][$solver].'": "'.$team_id.'",'.PHP_EOL;
				}
				if( array_key_exists($solver,$info['certified']['participants']) ) {
					echo '	"'.$info['certified']['participants'][$solver].'": "'.$team_id.'",'.PHP_EOL;
				}
			}
		}
	}
	$team_id++;
}
?>
};
function updateScores(catname,participants) {
	// first set score for the team of this category 0
	for( let solver_id in participants ) {
		teamScores[solver_id2team_id[solver_id]][catname] = 0;
	}
	// then add up scores of the team, in case it participates in certified category. We don't support two tools from one team!
	for( let solver_id in participants ) {
		teamScores[solver_id2team_id[solver_id]][catname] += participants[solver_id].score;
	}
	for( var i = 0; i < teamScores.length; i++ ) {
		let elt = document.querySelector("#team"+i+">.score");
		let score = Object.values(teamScores[i]).reduce((x,y) => { return x + y; }, 0);
		elt.innerHTML = score;
	}
}
</script>
<?php
if( !$closed ) {
	echo 'Registration is open! Please register following the README of <a href="https://github.com/TermCOMP/starexec-master">this repository</a>';
}
// creating team ranking
$team_id = 0;
foreach( $teams as $team => $solvers ) {
	echo '<span id="team'.$team_id.'">'.$team.'<span class="score"></span></span>'.PHP_EOL;
	$team_id++;
}
$mcatid = 0;
$catid = 0;
foreach( $mcats as $mcatname => $cats ) {
	$total_done = 0;
	$total_togo = 0;
	$total_cpu = 0;
	$total_time = 0;
	echo '<h2>'.$mcatname.' <span id=stat'.$mcatid.' class=stats></span></h2>'.PHP_EOL;
	$table = [];
	$tools = [];
	// creating progress summary
	echo ' <script>'.PHP_EOL.
	     '  function summer(a,b){'.PHP_EOL.
	     '   return {done: a.done + b.done, cpu: a.cpu + b.cpu, time: a.time + b.time, togo: a.togo + b.togo};'.PHP_EOL.
	     '  }'.PHP_EOL.
	     '  var progress'.$mcatid.' = [];'.PHP_EOL.
	     '  function updateProgress'.$mcatid.'() {'.PHP_EOL.
	     '   var sum = progress'.$mcatid.'.reduce(summer);'.PHP_EOL.
	     '   document.getElementById("stat'.$mcatid.'").innerHTML ='.PHP_EOL.
	     '    "Progress: " + Math.floor(1000 * sum.done / (sum.done + sum.togo))/10 +'.PHP_EOL.
	     '    "%, CPU Time: " + seconds2str(sum.cpu) + ", Node Time: "+ seconds2str(sum.time);'.PHP_EOL.
	     '  }'.PHP_EOL.
	     '</script>'.PHP_EOL;
	$catindex = 0;
	foreach( $cats as $catname => $cat ) {
		$type = $cat['type'];
		$id = $cat['id'];
		$spaceid = $cat['spaceid'];
		$jobids = explode('_',$id);
		$jobid = $jobids[0];
		$overlay = array_key_exists( 1, $jobids ) ? $jobids[1] : false;
		if( $id == null ) {// This means the job is not yet started or linked to starexec-master.
			echo ' <div class=category>';
			if( $spaceid != null ) {
				echo '<a href="'.spaceid2url($spaceid).'">'.$catname.'</a>'.PHP_EOL;
			} else {
				echo $catname.PHP_EOL;
			}
			echo '  <div class=ranking>'.PHP_EOL;
			foreach( $cat['certified']['participants'] as $partname => $configid ) {
				echo '   <div class="CERTIFIED participant">'.PHP_EOL.
				     '   '.$partname.'<a class=starexecid href="'. configid2url($configid) .'">'. $configid .'</a></div>'.PHP_EOL;
			}
			foreach( $cat['participants'] as $partname => $configid ) {
				echo '   <div class="participant">'. $partname.
				     '<a class=starexecid href="'. configid2url($configid) .'">'. $configid .'</a></div>'.PHP_EOL;
			}
			echo '  </div>'.PHP_EOL.
			     ' </div>';
			continue;
		}
		// creating job html
		$jobpath = 'job_'.$id.'.html';
		$graphpath = 'graph_'.$id.'.html';
		$jobargs = [
			'id' => $id,
			'name' => $catname,
			'mcatname' => $mcatname,
			'type' => $type,
			'competitionname' => $shortname,
			'db' => $db,
			'refresh' => $refresh,
		];
		$query = http_build_query( $jobargs, '', ' ' );
		$tmp = tempnam('','');
		system( 'php-cgi -f "job.php" '. $query .' > "'. $tmp . '"');
		rename($tmp,$jobpath);
		$tmp = tempnam('','');
		system( 'php-cgi -f "graph.php" '. $query .' > "'. $tmp . '"');
		rename($tmp,$graphpath);
		echo ' <span id="'.$id.'" class=category></span>'.PHP_EOL.
		     ' <script>'.PHP_EOL.
		     '  function load'.$id.'() {'.PHP_EOL.
		     '   var elm = document.getElementById("'.$id.'");'.PHP_EOL.
		     '   loadURL("'.$graphpath.'", function(xhttp) {'.PHP_EOL.
		     '    elm.innerHTML = xhttp.responseText;'.PHP_EOL.
		     '    scoreToggler.apply(elm);'.PHP_EOL.
		     '    configToggler.apply(elm);'.PHP_EOL.
		     '   });'.PHP_EOL.
		     '   loadURL("'.id2sumfile($id).'", function(xhttp) {'.PHP_EOL.
		     '    var data = JSON.parse(xhttp.responseText);'.PHP_EOL.
		     '    progress'.$mcatid.'['.$catindex.'] = data["layers"].reduce(summer);'.PHP_EOL.
		     '    updateProgress'.$mcatid.'();'.PHP_EOL.
		     '    updateScores("'.$catname.'",data["participants"]);'.PHP_EOL.
		     '   });'.PHP_EOL.
		     '  }'.PHP_EOL.
		     '  load'.$id.'();'.PHP_EOL.
		     '  setInterval(load'.$id.', 10000);'.PHP_EOL.
		     ' </script>'.PHP_EOL;
		$catindex++;
		$catid++;
	}
	$mcatid++;
}

?>

</body>
</html>
