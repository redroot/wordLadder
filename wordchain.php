<?php
/**
*
*	Word Chain Generator
*	@author 	Luke Williams
*	@modified 	04.07.11
*/

// Output Variables

$out 		= null;
$wordsPath 	= "words.txt"; // newline separated list of words
$cacheKey 	= "wordLadder";

// functions

/**
 * 	Handy inspector function
 *  @param var var	the variable to inspect
 */
function inspect($var){
	echo '<pre style="border:1px solid red;padding:10px;">';
	print_r($var);
	echo '</pre>';
}

/**
 * 	Cache functions
 * 	writes a text file containing words of a certain length to stop long loadng times
 * 
 *  @param string filename	name of the file
 * 	@param string contents  contents of the file
*/
function cacheWrite($filename,$contents){
	global $cacheKey;	
		
	$fh = fopen($cacheKey.$filename.".txt","w");
	fwrite($fh,serialize($contents));
	fclose($fh);
}

/**
 * 	Cache Read, attempts to read the cache file
 *  
 *  @param string filename	name of the file
*/

function cacheRead($filename){
	global $cacheKey;
	$filename = $cacheKey.$filename.".txt";
	$path = str_replace("wordchain.php","",$_SERVER["SCRIPT_FILENAME"]);
	
	if(!file_exists($path.$filename)){
		return false;
	}else{
		$contents =  file_get_contents($filename);
		return unserialize($contents);
	}
}

/**
 *	Builds a dictionary of one letter variations of all words of a certain length
 *  This allows us to store permuatations neatly for use later
 * 
 * 	@param integer length	the length of the words
 * 
 */
function buildDictionary($length){
	
	global $wordsPath;	
		
	$wordsContents = file_get_contents($wordsPath);
	

	// get an array of all words that length
	preg_match_all('/\b\w{'.$length.'}\b/', $wordsContents, $words);
	
	// flip keys and values for later
	$words = array_flip($words[0]);
	
	// set time limit so we don't time out
    set_time_limit(count($words));
	
	// rebuild a string of words
    $dict = join("\n", array_keys($words));
	
	// loop throuh building arrays of possile one-letter variant permuatations
	// to give us a nice array like:
	// [able] => Array
    //    (
    //        [0] => abbe
    //        [1] => ably
    //        [2] => axle
    //    )
    foreach($words as $k => $w)
    {
       $subwords = array();
       $variants = array();
	   
	   // build a array to match words which are one letter off this word e.g. zo[^o]m
       for($j = 0; $j < $length; $j++){
            $variants[] = '\b' . 
            				substr($k, 0, $j) . 
            				'[^' . $k{$j} . ']' . 
            				substr($k, $j+1) . '\b';
	   }
	   
       if(preg_match_all('/'.join("|", $variants) . '/', $dict, $subwords))
        	$words[$k] = $subwords[0];
       else
        	$words[$k] = array();
    }
	
	// write to cache and return the array
	cacheWrite($length,$words);
	return $words;
}
 
/**
 * 	Function that takes two words and attempts to find a chain
 * 	between the two, appending intermediary steps to a reference variable
 * 
 * 	@param string word_s 	start word
 *  @param string word_d 	destination word
 * 	@param string output	referenced variable, output string
 */
function generateWordChain($word_s,$word_d,$output){
	
	// some vars
	$length = strlen($word_s);
	$orig_word_d = $word_d;
	
	// get our list of words from the dictionary, either from cache or build
	$words = cacheRead($length);
	if(!$words){
		$words = buildDictionary($length);
	}
	
	// lets begin, some vars
	$i 		= 0;
    $found  = false;
    $search = array(array($word_s=>null));
	
	// words we've already come across
	// This helps us stop an infinitely loop of permuatations
	// since eventually this list will contain all words of that length
	// and hence a match was not found e.g. igloo -> yacht
    $used = array();

	
	// Master loop. what this does is loop through every permuatation 
	// of each word recursively until we find a match, or run out of words as it stores words already found
	// e.g. for cat, one permuatation would be 'cot', then all the permuatations
	// for that would be found.The downside is the more steps needed, the longer it takes.
	//
	// Also, for format, the elements of $search are themselves arrays, with the key being
	// a permuatation and the value being the root element e.g. [cot] => cat, [nat] => cat
	// the reason for this is to allow user to back track later up the chain from the destination
	// word to the starting word
	do
    {
        foreach($search[$i] as $perm => $root)
        {
            // build array into search if empty
            if(!is_array($search[$i+1])) $search[$i+1] = array();
           
		    // if the permuatation actually has further permuatations by looking in $words
		    if(count($words[$perm]))
            {
                // two options here
				// check for a match in the current permuatations
                if(in_array($word_d, $words[$perm])){
                    $found = true;
				}

			    // no luck, then try looping through the subperms 
                $rperms = array_flip($words[$perm]); // get the subperms as keys
                foreach($rperms as $np=>$nr)
                {
                    if(!in_array($np,$used)){
                    	// if not already checked, then assign the original parm
                    	// as a value of this subperm and add to already checked
                        $rperms[$np] = $perm;
                        $used[] = $np;
                    }else{
                    	// if already used then unset from current perms
                    	// ensuring we get unique roots
                        unset($rperms[$np]);
					}
                }
				
				// now we have a nice list of further subperms, merge with the previous
				// iterations and start again
                $search[$i+1] = array_merge($rperms,$search[$i+1]);
            }
        }
    }
    while(!$found && count($search[$i++ + 1]));
	
	

	// if a match is found, construct an array of steps
    $steps = array();
    if($found){
    	
        $count = count($search);	
		
        for($i = $count; $i > -1; $i--){
            if($word_d){
            	$steps[] = $word_d;
			}
			// go upwards through the permuatations to the root, using the 
			// [perm] => root structure defined earlier
            $word_d = $search[$i-1][$word_d];
        }
		
		// reverssse
        $steps = array_reverse($steps);
		
		// output time
		foreach($steps as $step){
		   	
			if($step == $word_s || $step == $orig_word_d){
				$step = "<strong>".$step."</strong>";
			}
				
		   	$output .= $step . '<br/>';
		}
		 
		$output .= '---<br/>'.$count.' steps';
		 
    }else{
    	$output = "No path found";
    }
   
  
	
}

// Handle POST

if($_POST["word_a"] && $_POST["word_b"] && $_POST["word_a"] != "" && $_POST["word_b"] != ""){
	
	$word_a = strtolower(trim($_POST["word_a"]));
	$word_b = strtolower(trim($_POST["word_b"]));
	
	// some validation first
	// 1) check words aren't the same
	// 2) check they are the same length
	// 3) check words are actually in the dictionary
	if($word_a === $word_b || strlen($word_a) != strlen($word_b)){
		$out = "Please enter two different words of the same length!";
	}else{
		// let the fun begin
		$out = "Finding word chain between <strong>".$word_a."</strong> and <strong>".$word_b."</strong>:<br/>---<br/>";
		generateWordChain($word_a,$word_b,&$out);
	}
}

// HTML page below vvvv
?>
<!doctype>
<html>
	<head>
		<title>Word Chain Generator</title>
	</head>
	<body>
		<h1>Word Chain Generator</h1>
		<div>
			<form action="" method="post">
				<fieldset>
					<legend>Please enter your two words</legend>
					<label for="word_a">First Word:</label>
					<input type="text" name="word_a" id="word_a" />
					<label for="word_b">Second Word:</label>
					<input type="text" name="word_b" id="word_b" />
					<p>
						<input type="submit" name="submit" value="Submit!" />
					</p>
				</fieldset>
			</form>
			<?php
				if($out){
					echo "<h2>Results</h2>";
					echo $out;
				}
			?>
		</div>
	</body>
</html>
