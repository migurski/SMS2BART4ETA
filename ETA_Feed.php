<?php

   /**
    * 
    */
    class ETA_Feed
    {
        var $p;
        var $stack;
        var $trail;
        
        var $eta;
        var $station;

        var $stations;

        function ETA_Feed()
        {
            $this->trail = array();
            $this->stack = array();
            $this->p =& xml_parser_create();
            
            $this->stations = array();

            xml_set_element_handler($this->p, array(&$this, 'on_open_element'), array(&$this, 'on_close_element'));
            xml_set_character_data_handler($this->p, array(&$this, 'on_character_data'));
        }
    
        function on_open_element(&$p, $n, $a)
        {
            if($n == 'STATION' && end($this->stack) == 'ROOT')
                $this->station = array('eta' => array(), 'words' => array());
        
            if($n == 'ETA' && end($this->stack) == 'STATION')
                $this->eta = array();
        
            array_push($this->stack, $n);
            array_push($this->trail, "open {$n}");
        }
        
        function on_character_data(&$p, $d)
        {
            if(end($this->stack) == 'NAME' && prev($this->stack) == 'STATION')
            {
                $words = preg_split('#[/\s\.]+#', strtolower($d));
                $this->station['words'] = array_merge($this->station['words'], $words);
                $this->station['name'] = trim($d);
            }

            if(end($this->stack) == 'ABBR' && prev($this->stack) == 'STATION')
            {
                $this->station['words'][] = strtolower($d);
                $this->station['abbr'] = trim($d);
            }

            if(end($this->stack) == 'DESTINATION' && prev($this->stack) == 'ETA')
                $this->eta['name'] = trim($d);

            if(end($this->stack) == 'ESTIMATE' && prev($this->stack) == 'ETA')
                $this->eta['time'] = trim($d);
        }
        
        function on_close_element(&$p, $n)
        {
            array_pop($this->stack);
            array_push($this->trail, "close {$n}");

            if($n == 'ETA')
            {
                $name = $this->eta['name'];
                $time = $this->eta['time'];
                $this->station['eta'][$name] = $time;
            }

            if($n == 'STATION')
                $this->stations[] = $this->station;
        }
        
        function parse($xml)
        {
            xml_parse($this->p, $xml, true);
        }
    }

?>
