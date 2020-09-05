<?php

# Init function

function init($output, $end_level, $language_labels, $links_wikisites) {

	# Define global configuration variables
	$conf=array();
	$conf["wikidata_endpoint"]="https://query.wikidata.org/sparql";

	$conf["file_csv_output"]=fopen("results/".$output.".csv","w");
	$conf["file_owl_output"]=fopen("results/".$output.".ttl","w");
	$conf["file_gml_output"]=fopen("results/".$output.".gml","w");
	$conf["file_gexf_output"]=fopen("results/".$output.".gexf","w");
	$conf["file_links_used_output"]=fopen("results/".$output."-links-used.csv","w");
	$conf["file_backlinks_used_output"]=fopen("results/".$output."-backlinks-used.csv","w");
	$conf["output_csv"]=array();
	$conf["output_owl"]=array();
	$conf["end_level"]=$end_level;
	$conf["languages_labels"]=$language_labels;
	$conf["links_wikisites"]=$links_wikisites;
	$conf["visited_items"]=array();
	$conf["visited_properties"]=array();
	$conf["entity_labels"]=array();
	$conf["allowed_properties"]=array();
	$conf["start_items_links"]=array();
	$conf["start_items_links_used"]=array();
	$conf["start_items_backlinks"]=array();
	$conf["start_items_backlinks_used"]=array();
	$conf["is_leaf"]=array();
	$conf["hide_required_leaf_properties"]=false;
	$conf["minus_items_exceptions"]=array();
	$conf["minus_class_exceptions"]=array();
	$conf["minus_items"]=array();
	$conf["simple_leaf_items"]=array();
	$conf["simple_leaf_properties"]=array();
	$conf["simple_leaf_class"]=array();
	$conf["item_detected"]=array();
	$conf["property_detected"]=array();
	$conf["count_validated_by_links"]=0;
	$conf["count_validated_by_backlinks"]=0;
	$conf["count_validated_by_both"]=0;
	$conf["wbr_validated_by_links"]=0;
	$conf["wbr_validated_by_backlinks"]=0;
	$conf["wbr_validated_by_both"]=0;
	$conf["number_of_claims"]=0;
	$conf["wbr_number_of_claims"]=0;
	$conf["current_seed"]="";
	$conf["found_wplinks"]=array();
	$conf["nodes"]=array();
	$conf["edges"]=array();

	$conf["config_sections"] = array(
		"start_items"=>"Start items",
		"leaf_items"=>"Leaf items",
		"leaf_properties"=>"Leaf properties",
		"leaf_class"=>"Leaf class",
		"leaf_items_exceptions"=>"Leaf items exceptions",
		"required_leaf_properties"=>"Required Leaf Properties",
		"minus_items"=>"Minus items",
		"minus_properties"=>"Minus properties",
		"minus_class"=>"Minus class",
		"minus_items_exceptions"=>"Minus items exceptions",
		"minus_class_exceptions"=>"Minus class exceptions"
	);

	# Read configuration file
	foreach ($conf["config_sections"] as $section=>$header) {
		$conf[$section]=array();
	}
	$fconfig=fopen("conf/".$output.".ini","r");
	if ($fconfig) {
		while (($line = fgets($fconfig)) !== false) {
			$line=trim($line);
			if(!empty($line)) {
				if ($line[0]=="[" and $line[-1]=="]") {
					$section=mb_substr(mb_substr($line, 0, -1),1);
				} else {
					$conf[$section][]=$line;
				}
			}		
		}
	} else {
		echo "\n\nConfiguration file lost\n\n";
		exit();
	}
	return($conf);
}


# Get links and backlinks of wikipedia pages for every start items
function get_l_bl($conf) {
	$link_types=array(
		"links"=>array("title"=>"titles","prefix"=>"gpl"),
		"backlinks"=>array("title"=>"gbltitle","prefix"=>"gbl")
	);
	foreach ($conf["start_items"] as $q) {
		echo "\n>>>>>>>>>>>>>>>>>>>>>> Processing links and backlinks for ".$q;
		$item=get_entity($q);
		foreach ($link_types as $type=>$wplinks) {

			foreach ($conf["links_wikisites"] as $wikisite) {
				$repeat_query=true;
				$continue="";
				$p = parse_url($item["entities"][$q]["sitelinks"][$wikisite]["url"]);
				while ($repeat_query==true) {
					$url=$p["scheme"]."://".$p["host"]."/w/api.php?action=query&generator=".$type."&".$wplinks["title"]."=".basename($p["path"])."&prop=pageprops&ppprop=wikibase_item&".$wplinks["prefix"]."limit=500&format=json".$continue;
					$page=json_decode(file_get_contents($url), true);
					$links=$page["query"]["pages"];
					foreach ($links as $data_link) {
						if (isset($data_link["pageprops"]["wikibase_item"])) {
							$conf["start_items_".$type][$data_link["pageprops"]["wikibase_item"]]=$data_link["title"];
							$conf["start_items_".$type."_used"][$data_link["pageprops"]["wikibase_item"]]=false;
							$conf["found_wplinks"][$type][$q][$data_link["pageprops"]["wikibase_item"]]="no";
						}				
					}
					if (isset($page["continue"]["continue"])) {
						$continue="&".$wplinks["prefix"]."continue=".urlencode($page["continue"][$wplinks["prefix"]."continue"]);
					} else {
						$repeat_query=false;
					}
				}
			}
		}
	}
	return ($conf);
}


# Close output file
function close($conf) {
	fclose($conf["file_csv_output"]);
	fclose($conf["file_owl_output"]);
	fclose($conf["file_links_used_output"]);
	fclose($conf["file_backlinks_used_output"]);
}

# Get JSON-LD from wikipedia entity
function get_entity($q) {
	$url = "https://www.wikidata.org/wiki/Special:EntityData/".$q.".json"; 
	$a = json_decode(file_get_contents($url), true);
	return($a);
}

# Send sparql query to endpoint
function sparql_query($endpoint,$query,$format) {
	$fields = [
		"query"=>$query,
		"format" =>$format
	];
		
	$postfields = http_build_query($fields);	
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$endpoint);
	curl_setopt($ch,CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36");
	curl_setopt($ch,CURLOPT_POST, true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$postfields);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	// curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 0);
	return(json_decode(curl_exec($ch), true));

}

# Function to generate relation graph from wikidata items
function generate_graph($conf) {
	$last_processed_item_tail = 0;
	$tail_items = $conf["start_items"];
	for ($level_loop=0; $level_loop<=$conf["end_level"]; $level_loop++) {
		$number_items_in_tail = sizeof($tail_items);
		$number_items_found_loop[$level_loop]=0;
		for ($index_current_item=$last_processed_item_tail; $index_current_item<$number_items_in_tail; $index_current_item++) {
			$current_item = $tail_items[$index_current_item];
			if (!in_array($current_item,$conf["minus_items"])) {
				$wikidata_query='SELECT ?subject ?subjectLabel ?prop ?propLabel ?object ?objectLabel WHERE {
					BIND(wd:'.$current_item.' AS ?subject)
					?subject ?p ?object.
					?prop wikibase:directClaim ?p .
					FILTER(STRSTARTS(STR(?object), "http://www.wikidata.org/entity/Q"))
					SERVICE wikibase:label {
							bd:serviceParam wikibase:language "en,es".
						}
					}';

				$results=sparql_query($conf["wikidata_endpoint"],$wikidata_query,"json");
				$is_minus_by_class = false;
				$is_exception=false;
				$is_rich_leaf_byitem=false;
				$is_rich_leaf_byclass=false;
				$processing_type="normal";

				foreach ($results["results"]["bindings"] as $triplet) {

					$subject_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["subject"]["value"]);
					$object_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["object"]["value"]);
					$prop_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["prop"]["value"]);


					if (in_array($subject_entity,$conf["leaf_items"])) {
						$is_rich_leaf_byitem=true;
						$processing_type="rich_leaf_byitem";
					}

					if (in_array($prop_entity,$conf["required_leaf_properties"]) and in_array($object_entity,$conf["leaf_class"])) {
						$is_rich_leaf_byclass=true;
						$processing_type="rich_leaf_byclass";
					}

					# Check if current item is a SIMPLE LEAF BY CLASS
					if (in_array($prop_entity,$conf["required_leaf_properties"]) and in_array($object_entity,$conf["simple_leaf_class"])) {
						$processing_type="simple_leaf_byclass";
					}

					if (in_array($prop_entity,$conf["required_leaf_properties"]) and in_array($object_entity,$conf["minus_class_exceptions"])) {
						$is_minus_by_class=false;
						$is_exception=true;
					}

					# Check if current item is MINUS BY CLASS and NOT is a MINUS CLASS EXCEPTION
					if (!in_array($subject_entity,$conf["minus_items_exceptions"]) and !$is_exception) {
						if (in_array($prop_entity,$conf["required_leaf_properties"]) and in_array($object_entity,$conf["minus_class"]) and $level_loop>0) {
							$is_minus_by_class=true;
						}
					}
				}

				if ($is_minus_by_class) {
					unset($tail_items[$index_current_item]);
					$index_current_item--;
					$number_items_in_tail--;
					$tail_items=array_values($tail_items);
				}

				if (!$is_minus_by_class and $processing_type!="simple_leaf_byclass") {
					$number_items_found_loop[$level_loop]++;
					foreach ($results["results"]["bindings"] as $triplet) {
						if ($triplet["object"]["type"]!="uri") {continue;}
						$subject_label = "";
						$object_label = "";
						$prop_label = "";
						$subject_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["subject"]["value"]);
						$object_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["object"]["value"]);
						$prop_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["prop"]["value"]);
						if ($object_entity[0]!="Q") {continue;}
						$subject_label = $triplet["subjectLabel"]["value"];
						$object_label = $triplet["objectLabel"]["value"];
						$prop_label = $triplet["propLabel"]["value"];

						$add_object = true;
						$get_statement = true;

						# For previously added items
						if (in_array($object_entity,$tail_items)) {
							$add_object = false;
						}

						# Leaf by item or by class
						if ($is_rich_leaf_byitem or $is_rich_leaf_byclass) {
							$add_object = false;
							if (!in_array($prop_entity,$conf["required_leaf_properties"])) {
								$get_statement = false;
							}
						}

						# Simple leaf by property
						if (in_array($prop_entity,$conf["simple_leaf_properties"])) {
							$add_object = false;
							$processing_type="simple leaf by property";
						}

						# Check if object is s SIMPLE_LEAF_ITEM
						if (in_array($object_entity,$conf["simple_leaf_items"])) {
							$add_object = false;
							$processing_type="object is simple leaf item";
						}

						# Minus property
						if (in_array($prop_entity,$conf["minus_properties"])) {
							$add_object = false;
							$get_statement = false;
						}


						# Check if object item is MINUS
						if (in_array($object_entity,$conf["minus_items"])) {
							$add_object = false;
							$get_statement = false;
						}


						if ($add_object) {
							$tail_items[]=$object_entity;
						}


						if ($get_statement) {

							$validated_by_link = "";
							$validated_by_backlink = "";
							$leaf_node = $processing_type;
		
		
							# Validated by Backlink
							if (isset($conf["start_items_backlinks"][$object_entity])) {
								$validated_by_backlink="Validated by backlink";
								$conf["start_items_backlinks_used"][$object_entity]=true;
								$conf["count_validated_by_backlinks"]++;
							}
		
							# Validated by Link
							if (isset($conf["start_items_links"][$object_entity])) {
								$validated_by_link="Validated by link";
								$conf["start_items_links_used"][$object_entity]=true;
								$conf["count_validated_by_links"]++;
							}
		
							# Validated by both (Link & Backlink)
							if (isset($conf["start_items_links"][$object_entity]) and isset($conf["start_items_backlinks"][$object_entity])) {
								$conf["count_validated_by_both"]++;
							}
		
							# For STATISTICS of distinct ITEMS and PROPERTIES
							$conf["item_detected"][$subject_entity]=true;
							$conf["item_detected"][$object_entity]=true;
							$conf["property_detected"][$prop_entity]=true;
		
							# Create graph
							$conf["nodes"][$subject_entity]["label"]=$subject_label;
							$conf["nodes"][$object_entity]["label"]=$object_label;
							$conf["nodes"][$subject_entity]["level"]=$level_loop;
							$conf["nodes"][$object_entity]["level"]=$level_loop+1;
							$conf["edges"][]=array("source"=>$subject_entity,"target"=>$object_entity,"property"=>$prop_entity,"label"=>$prop_label);
							
							# CSV output
							$output="";
							$output=$level_loop."\t".$subject_entity."\t".$subject_label."\t".$prop_entity."\t".$prop_label."\t".$object_entity."\t".$object_label."\t".$leaf_node."\t".$validated_by_link."\t".$validated_by_backlink."\n";
							if (isset($conf["output_csv"][$subject_entity."-".$prop_entity."-".$object_entity])) {
								unset($conf["output_csv"][$subject_entity."-".$prop_entity."-".$object_entity]);
							}
							$conf["output_csv"][$subject_entity."-".$prop_entity."-".$object_entity]=$output;
							$conf["number_of_claims"]++;
							echo ($index_current_item+1)."/".$number_items_in_tail." - ".$output;
						
							# OWL output
							$output="";
							$output.="wd:".$subject_entity." rdfs:subClassOf owl:Thing .\n";
							$output.="wd:".$object_entity." rdfs:subClassOf owl:Thing .\n";
							$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdf:type owl:ObjectProperty .\n";
							$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdfs:domain wd:".$subject_entity." .\n";
							$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdfs:range wd:".$object_entity." .\n";
							$output.="wd:".$subject_entity." wdt:".$subject_entity.$prop_entity.$object_entity." wd:".$object_entity." .\n";
							$output.="wd:".$subject_entity." rdfs:label \"".$subject_label."\" .\n";
							$output.="wd:".$object_entity." rdfs:label \"".$object_label."\" .\n";
							$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdfs:label \"".$prop_label."\" .\n";
							if (isset($conf["output_owl"][$subject_entity."-".$prop_entity."-".$object_entity])) {
								unset($conf["output_owl"][$subject_entity."-".$prop_entity."-".$object_entity]);
							}
							$conf["output_owl"][$subject_entity."-".$prop_entity."-".$object_entity]=$output;	

						}
					}
				}
			}
		}
		$last_processed_item_tail=$index_current_item;
		print_r($number_items_found_loop); // verbose output
	}
	return($conf);
}

function enrich_with_wikilinks($conf,$q) {

	$wikidata_query='SELECT ?subject ?subjectLabel ?prop ?propLabel ?object ?objectLabel WHERE {
		BIND(wd:'.$q.' AS ?object)
		?subject ?p ?object.
		?prop wikibase:directClaim ?p .
		FILTER(STRSTARTS(STR(?object), "http://www.wikidata.org/entity/Q"))
		SERVICE wikibase:label {bd:serviceParam wikibase:language "en,es".}
	}';
	$results=sparql_query($conf["wikidata_endpoint"],$wikidata_query,"json");

	foreach ($results["results"]["bindings"] as $triplet) {
		$subject_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["subject"]["value"]);
		$object_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["object"]["value"]);
		$prop_entity = str_replace("http://www.wikidata.org/entity/","",$triplet["prop"]["value"]);
		$q_label = $triplet["objectLabel"]["value"];
		$subject_label = $triplet["subjectLabel"]["value"];
		$object_label = $triplet["objectLabel"]["value"];
		$prop_label = $triplet["propLabel"]["value"];
		
		$is_minus=false;

		if (in_array($prop_entity,$conf["minus_properties"])) {$is_minus=true;}
		if (in_array($object_entity,$conf["minus_items"])) {$is_minus=true;}

		if (!$is_minus) {

			$wikidata_query='SELECT ?subject ?prop ?object WHERE {
				BIND(wd:'.$subject_entity.' AS ?subject)
				?subject ?prop ?object.
			}';

			$results2=sparql_query($conf["wikidata_endpoint"],$wikidata_query,"json");

			foreach ($results2["results"]["bindings"] as $triplet2) {
				$subject_entity2 = str_replace("http://www.wikidata.org/entity/","",$triplet2["subject"]["value"]);
				$object_entity2 = str_replace("http://www.wikidata.org/entity/","",$triplet2["object"]["value"]);
				$prop_entity2 = str_replace("http://www.wikidata.org/entity/","",$triplet2["prop"]["value"]);

				if (!in_array($subject_entity2,$conf["minus_items_exceptions"])) {
					if (in_array($prop_entity2,$conf["required_leaf_properties"]) and in_array($object_entity,$conf["minus_class"])) {
						$is_minus=true;
					}
					if (in_array($subject_entity2,$conf["minus_items"])) {
						$is_minus=true;
					}
				}
			}

			$condition_a = !(in_array($prop_entity,$conf["minus_properties"]));
			$condition_b = !(in_array($object_entity,$conf["minus_items"])) and !(in_array($subject_entity,$conf["minus_items"])) ;
			$condition_c = !$is_minus;
			$subject_label = $triplet["subjectLabel"]["value"];
			$object_label = $triplet["objectLabel"]["value"];
			$prop_label = $triplet["propLabel"]["value"];
			
			if ($condition_a and $condition_b and $condition_c) {
				$validated_by_link = "";
				$validated_by_backlink = "";
				$leaf_node = "";

				# Validated by Backlink
				if (isset($conf["start_items_backlinks"][$subject_entity])) {
					$validated_by_backlink="Validated by backlink";
					$conf["start_items_backlinks_used"][$subject_entity]=true;
					$conf["wbr_validated_by_backlinks"]++;
				}

				# Validated by Link
				if (isset($conf["start_items_links"][$subject_entity])) {
					$validated_by_link="Validated by link";
					$conf["start_items_links_used"][$subject_entity]=true;
					$conf["wbr_validated_by_links"]++;
				}

				# Validated by both (Link & Backlink)
				if (isset($conf["start_items_links"][$subject_entity]) and isset($conf["start_items_backlinks"][$subject_entity])) {
					$conf["wbr_validated_by_both"]++;
				}			

				$conf["wbr_number_of_claims"]++;


				# For STATISTICS of distinct ITEMS and PROPERTIES
				$conf["item_detected"][$subject_entity]=true;
				$conf["item_detected"][$object_entity]=true;
				$conf["property_detected"][$prop_entity]=true;

				# Create graph
				$conf["nodes"][$subject_entity]["label"]=$subject_label;
				$conf["nodes"][$object_entity]["label"]=$object_label;
				$conf["edges"][]=array("source"=>$subject_entity,"target"=>$object_entity,"property"=>$prop_entity,"label"=>$prop_label);
				
				# CSV output
				$output="";
				$output="WBR\t".$subject_entity."\t".$subject_label."\t".$prop_entity."\t".$prop_label."\t".$object_entity."\t".$object_label."\t".$leaf_node."\t".$validated_by_link."\t".$validated_by_backlink."\n";
				if (isset($conf["output_csv"][$subject_entity."-".$prop_entity."-".$object_entity])) {
					unset($conf["output_csv"][$subject_entity."-".$prop_entity."-".$object_entity]);
				}
				$conf["output_csv"][$subject_entity."-".$prop_entity."-".$object_entity]=$output;
				$conf["number_of_claims"]++;
				echo "WBR - ".$output;
			
				# OWL output
				$output="";
				$output.="wd:".$subject_entity." rdfs:subClassOf owl:Thing .\n";
				$output.="wd:".$object_entity." rdfs:subClassOf owl:Thing .\n";
				$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdf:type owl:ObjectProperty .\n";
				$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdfs:domain wd:".$subject_entity." .\n";
				$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdfs:range wd:".$object_entity." .\n";
				$output.="wd:".$subject_entity." wdt:".$subject_entity.$prop_entity.$object_entity." wd:".$object_entity." .\n";
				$output.="wd:".$subject_entity." rdfs:label \"".$subject_label."\" .\n";
				$output.="wd:".$object_entity." rdfs:label \"".$object_label."\" .\n";
				$output.="wdt:".$subject_entity.$prop_entity.$object_entity." rdfs:label \"".$prop_label."\" .\n";
				if (isset($conf["output_owl"][$subject_entity."-".$prop_entity."-".$object_entity])) {
					unset($conf["output_owl"][$subject_entity."-".$prop_entity."-".$object_entity]);
				}
				$conf["output_owl"][$subject_entity."-".$prop_entity."-".$object_entity]=$output;	
			}
		}
	}

	return($conf);
}

# Save final graph into OWL file
function generate_owl($conf) {

	fwrite($conf["file_owl_output"],"@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n");
	fwrite($conf["file_owl_output"],"@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n");
	fwrite($conf["file_owl_output"],"@prefix owl: <http://www.w3.org/2002/07/owl#> .\n");
	fwrite($conf["file_owl_output"],"@prefix wd: <http://www.wikidata.org/entity/> .\n");
	fwrite($conf["file_owl_output"],"@prefix wdt: <http://www.wikidata.org/prop/direct/> .\n");
	fwrite($conf["file_owl_output"],"@prefix wdcovid19: <http://www.wikidata.org/voc/coronavirus/> .\n");
	fwrite($conf["file_owl_output"],"@base <http://www.wikidata.org/voc/coronavirus/> .\n");
	fwrite($conf["file_owl_output"],"<http://www.wikidata.org/voc/coronavirus/> rdf:type owl:Ontology .\n");

	foreach ($conf["output_owl"] as $output) {
		fwrite($conf["file_owl_output"], $output);
	} 
}

# Save final graph into CSV files (with statistics + links and backlinks usage)
function generate_csv($conf) {

	foreach ($conf["output_csv"] as $output) {
		fwrite($conf["file_csv_output"], $output);
	} 

	fwrite($conf["file_csv_output"],"Q claims\t".$conf["number_of_claims"]."\n");
	fwrite($conf["file_csv_output"],"WBR claims\t".$conf["wbr_number_of_claims"]."\n");
	fwrite($conf["file_csv_output"],"Q-VAL only by links\t".$conf["count_validated_by_links"]."\n");
	fwrite($conf["file_csv_output"],"Q-VAL only by backlinks\t".$conf["count_validated_by_backlinks"]."\n");
	fwrite($conf["file_csv_output"],"Q-VAL by links+backlinks)\t".$conf["count_validated_by_both"]."\n");
	fwrite($conf["file_csv_output"],"WBR-VAL only by links\t".$conf["wbr_validated_by_links"]."\n");
	fwrite($conf["file_csv_output"],"WBR-VAL only by backlinks\t".$conf["wbr_validated_by_backlinks"]."\n");
	fwrite($conf["file_csv_output"],"WBR-VAL by links+backlinks)\t".$conf["wbr_validated_by_both"]."\n");
	fwrite($conf["file_csv_output"],"Distinct items\t".sizeof($conf["item_detected"])."\n");
	fwrite($conf["file_csv_output"],"Distinct properties\t".sizeof($conf["property_detected"])."\n");

	$used_count=0;
	$unused_count=0;
	foreach ($conf["start_items_links"] as $item=>$title) {
		fwrite($conf["file_links_used_output"],$title."\t".$item."\t");
		if ($conf["start_items_links_used"][$item]) {
			fwrite($conf["file_links_used_output"],"Used\n");
			$used_count++;
		} else {
			fwrite($conf["file_links_used_output"],"Not used\n");
			$unused_count++;
		}
	}
	fwrite($conf["file_csv_output"],"Links used\t".$used_count."\n");
	fwrite($conf["file_csv_output"],"Links unused\t".$unused_count."\n");

	$used_count=0;
	$unused_count=0;
	foreach ($conf["start_items_backlinks"] as $item=>$title) {
		fwrite($conf["file_backlinks_used_output"],$title."\t".$item."\t");
		if ($conf["start_items_backlinks_used"][$item]) {
			fwrite($conf["file_backlinks_used_output"],"Used\n");
			$used_count++;
		} else {
			fwrite($conf["file_backlinks_used_output"],"Not used\n");
			$unused_count++;
		}
	}
	fwrite($conf["file_csv_output"],"Backlinks used\t".$used_count."\n");
	fwrite($conf["file_csv_output"],"Backlinks unused\t".$unused_count."\n");
}

function generate_gml($conf) {
	fwrite($conf["file_gml_output"],"graph\n[\n  Creator \"Wikidata Graph Constructor\"\n  directed 1\n");
	$node = array();
	$edge = array();
	$count = 0;
    
	foreach ($conf["nodes"] as $item=>$node_data) {
		fwrite($conf["file_gml_output"],"  node\n  [\n    id \"".$item."\"\n    label \"".$node_data["label"]."\"\n  ]\n");
	}

	foreach ($conf["edges"] as $number=>$edge_data) {
		fwrite($conf["file_gml_output"],"  edge\n  [\n    id ".$number."\n    source \"".$edge_data["source"].
		"\"\n    target \"".$edge_data["target"]."\"\n    label \"".$edge_data["property"].":".$edge_data["label"]."\"\n  ]\n");
	}
	fwrite($conf["file_gml_output"],"]\n");
	fclose($conf["file_gml_output"]);
}

function generate_gefx($conf) {
	fwrite($conf["file_gexf_output"],'<?xml version="1.0" encoding="UTF-8"?>
  <gexf xmlns="http://www.gexf.net/1.3" version="1.3" xmlns:viz="http://www.gexf.net/1.3/viz" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.gexf.net/1.3 http://www.gexf.net/1.3/gexf.xsd">
	<meta lastmodifieddate="2020-06-09">
	  <creator>Gephi 0.9</creator>
	  <description></description>
	</meta>
	<graph defaultedgetype="directed" idtype="string" mode="static">'."\n");

	fwrite($conf["file_gexf_output"],'<nodes count="'.sizeof($conf["nodes"]).'">'."\n");
	foreach ($conf["nodes"] as $item=>$node_data) {
		fwrite($conf["file_gexf_output"],'<node id="'.$item.'" label="'.$node_data["label"].'"/>'."\n");
	}
	fwrite($conf["file_gexf_output"],'</nodes>'."\n");

	fwrite($conf["file_gexf_output"],'<edges count="'.sizeof($conf["edges"]).'">'."\n");
	foreach ($conf["edges"] as $number=>$edge_data) {
		fwrite($conf["file_gexf_output"],'<edge id="'.$number.'" source="'.$edge_data["source"].'" target="'.$edge_data["target"].'" label="'.$edge_data["property"].":".$edge_data["label"].'" weight="1.0"/>'."\n");
	}
	fwrite($conf["file_gexf_output"],'</edges>'."\n");
	fwrite($conf["file_gexf_output"],'</graph>'."\n");
	fwrite($conf["file_gexf_output"],'</gexf>'."\n");
	fclose($conf["file_gexf_output"]);
}

?>



