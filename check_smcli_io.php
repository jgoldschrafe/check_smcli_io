<?php
    $colors = array(
        '#1552c8',
        '#6f10ca',
        '#009d91',
        '#ffa802',
        '#71ee02',
        '#ed022d'
    );
    
    $sources_per_graph = count($colors);
    
    $counter_metadata = array(
        'cache_hit_pct' => array(
            'title' => 'Cache Hit Pct',
            'label' => 'Percent',
            'match' => '/^\w+ (.*) Cache Hit Pct$/'
        ),
        'iops' => array(
            'title' => 'IOPS',
            'label' => 'Transfers',
            'match' => '/^\w+ (.*) IOPS$/'
        ),
        'read_pct' => array(
            'title' => 'Read Pct',
            'label' => 'Percent',
            'match' => '/^\w+ (.+) Read Pct$/',
        ),
        'throughput' => array(
            'title' => 'Throughput',
            'label' => 'KB/s',
            'match' => '/^\w+ (.*) Throughput$/',
        )
    );

    $class_metadata = array(
        'TOTAL' => array(
            'label' => 'Storage Processor Total'
        ),
        'CONTROLLER' => array(
            'label' => 'Controller'
        ),
        'ARRAY' => array(
            'label' => 'Physical Array'
        ),
        'LU' => array(
            'label' => 'Logical Unit'
        )
    );

    
    $class_data = array();

    // Organize graph data by class and counter
    $num_data_sources = count($this->DS);
    for ($i = 0; $i < $num_data_sources; $i++) {
        $data_source = $this->DS[$i];
        $ds_label = $data_source['LABEL'];
        
        $class_name = substr($ds_label, 0, strpos($ds_label, ' '));
        
        $counter_matched = false;
        foreach($counter_metadata as $k => $v) {
            $match_regex = $counter_metadata[$k]['match']; 

            $m = array();
            if (preg_match($match_regex, $ds_label, $m)) {
                $counter_matched = true;
                $source_name = $m[1];

                if (!array_key_exists($class_name, $class_data)) {
                    $class_data[$class_name] = array();
                }

                if (!array_key_exists($k, $class_data[$class_name])) {
                    $class_data[$class_name][$k] = array();
                }

                $class_data[$class_name][$k][$source_name] = $data_source;
                
                break;
            }
        }
        
        if (!$counter_matched) {
            die("Didn't match: $ds_label");
        }
    }

    function check_smcli_io_sortfunc($a, $b) {
        return strcmp($a['LABEL'], $b['LABEL']);
    }

    ksort($class_data);

    $i = -1;
    foreach ($class_data as $class_name => $counter_list) {
        foreach ($counter_list as $counter_name => $unit_list) {
            $j = 0;

            usort($unit_list, 'check_smcli_io_sortfunc');

            foreach ($unit_list as $unit_name => $unit_ds) {
                $graph_title = $counter_metadata[$counter_name]['title'];
                $graph_label = $counter_metadata[$counter_name]['label'];
                $graph_class = $class_metadata[$class_name]['label'];

                // Start new graph if the number of plots per graph has been
                // exceeded
                if ($j % $sources_per_graph == 0) {
                    $i++;
                    
                    $def[$i] = '';
                    $opt[$i] = "-E --vertical-label '$graph_label' --title '$graph_class $graph_title'";

                    $rrd_var_defs = '';
                    $rrd_chart_defs = '';
                }
                
                $ds_label = $unit_ds['LABEL'];
                $ds_rrdfile = $unit_ds['RRDFILE'];
                $ds_idx = $unit_ds['DS'];
                $color = $colors[$j % count($colors)];
                
                $rrd_var_defs   .= "DEF:var$j=$ds_rrdfile:$ds_idx:AVERAGE ";
                $rrd_chart_defs .= "LINE1:var$j$color:\"$ds_label\" ";
                $rrd_chart_defs .= "GPRINT:var$j:LAST:\"%7.2lf %S last\" ";
                $rrd_chart_defs .= "GPRINT:var$j:MAX:\"%7.2lf %S max\" ";
                $rrd_chart_defs .= "GPRINT:var$j:AVERAGE:\"%7.2lf %S avg\\n\" ";
            
                $def[$i] = $rrd_var_defs . $rrd_chart_defs;
                
                $j++;
            }
        }
    }
?>
