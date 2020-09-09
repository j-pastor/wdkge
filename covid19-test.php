<?php

	// This script test the Wikidata Knowledge Graph Extractor Library (wdkge)
	// Usage from command line: php covid-19.php <base_conf_file (without .ini extension)> <number_of_exploration leves>
	// i.e.: php covid19.php covid19-3 3

	require("wdkge-lib.php"); // load wdkge library

	$start_time = microtime(true);

	if (!isset($argv[2])) {
		echo "\n\nLevels and prefix files are needed\n\n";
		exit();
	}

	$end_level=$argv[2]; // number onf levels
	$output=$argv[1]; // base configuration file

	$conf=init($output,$end_level,"en,es",array("enwiki","eswiki")); // init configuration data
	$conf=get_l_bl($conf); // get links and backlinks of start items
	$conf=generate_graph($conf); // generate graph

	foreach ($conf["start_items"] as $q) {
		// $conf=enrich_with_inverse($conf, $q); // enrich with inverse relations
	}

	// save results files
	generate_csv($conf);
	generate_owl($conf);
	generate_gefx($conf);
	generate_gml($conf);
	close($conf);

	$end_time = microtime(true);
	$execution_time = ($end_time - $start_time); 
	echo "\n\n\n Execution time of script = ".$execution_time." sec"; 

?>

