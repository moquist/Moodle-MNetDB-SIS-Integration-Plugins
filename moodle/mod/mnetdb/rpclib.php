<?php
function mnetdb_mnet_publishes() {
    return array(array(
        'name'       => 'mnetdb',
        'apiversion' => 1,
        'methods'    => array(
            'sql_execute',
        ),
    ));
}

?>
