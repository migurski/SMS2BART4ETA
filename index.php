<?php

    require_once 'ETA_Feed.php';

   /**
    * For a given search, return fresh arrival estimates for closest matching station.
    */
    function estimates($search)
    {
        $p = new ETA_Feed();
        $p->parse(curl_get_file_contents('http://www.bart.gov/dev/eta/bart_eta.xml'));
        
        $words = preg_split('#[/\s\.]+#', strtolower($search));
        
        $special_cases = array();
        $lev_distances = array();
        $station_matches = array();
        
        // look for possible matches
        foreach($p->stations as $s => $station)
        {
            $station_match = false;
        
            if(array_intersect($words, $station['words']))
                $station_match = true;
            
            foreach($words as $search_word)
                if($search_word)
                    foreach($station['words'] as $station_word)
                        if(substr($station_word, 0, strlen($search_word)) == $search_word)
                            $station_match = true;
            
            switch(true)
            {
                // airport code for SF airport
                case strtoupper($search) == 'SFO' && $station['name'] == "San Francisco Int'l Airport":
                
                // airport code for Oakland airport
                case strtoupper($search) == 'OAK' && $station['name'] == 'Coliseum/Oakland Airport':
                
                // just searching for "berkeley" should match Downtown Berkeley
                case in_array(strtolower($search), array('berk', 'berkeley')) && $station['name'] == 'Downtown Berkeley':
                
                // does it seem more likely that someone would mean the SF airport over the Oakland one?
                case strtolower($search) == 'airport' && $station['name'] == "San Francisco Int'l Airport":

                // just searching for "downtown" should match downtown SF
                case strtolower($search) == 'downtown' && $station['name'] == 'Montgomery St.':

                    $special_case = 1;
                    $station_match = true;
                    break;
                
                default:
                    $special_case = 0;
                    break;
            }
            
            if($station_match)
            {
                $station_matches[] = $station;
                $lev_distances[] = levenshtein($search, $station['name']);
                $special_cases[] = $special_case;
            }
        }
        
        // sort possible matches
        array_multisort($special_cases, SORT_DESC, $lev_distances, $station_matches);
        
        return count($station_matches) ? $station_matches[0] : null;
    }
        
   /**
    * Use curl when available, file_get_contents (with url wrappers) when not.
    */
    function curl_get_file_contents($url)
    {
        if(!function_exists('curl_init'))
            return file_get_contents($url);
        
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $url);
        $contents = curl_exec($c);
        curl_close($c);
        
        if($contents)
            return $contents;
        
        return false;
    }

    $q = isset($_POST['Body']) ? $_POST['Body'] : $_GET['q'];
    $q = preg_replace('/[^\w\s]+/', ' ', $q);
    
    $station = estimates($q);
    $lines = array();
    
    if(empty($station)) {
        $lines[] = 'Not sure what to do with "'.$q.'"';
    
    } else {
        foreach($station['eta'] as $direction => $times)
            $lines[] = $direction.': '.preg_replace('/\b(\d+) min\b/', '\1m', $times);
        
        $lines[] = "...from {$station['name']}";
    }

    $message = join("\n", $lines);
    
    header('Content-Type: text/plain');
    echo substr($message, 0, 160);

?>
