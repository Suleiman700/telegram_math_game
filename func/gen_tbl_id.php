<?php

//Called when need to add new row to table (this function will generate new table primary key based on key and table name)
function get_new_id($key, $tbl){
    require 'inc/db.php';
    //Find lowest id that does not exist
    //$sql = "SELECT $key+1 FROM $tbl WHERE ($key+1) not in (SELECT $key FROM $tbl) limit 1";
    $sql = "SELECT max($key) FROM $tbl";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array());
    $id = $stmt->fetchColumn();

    //If id in NULL or zero
    if (!$id)
        return 1;
    else
        return $id+1;
}


//Check if item exists in table
function record_info_in_tbl($col, $key, $key_value, $tbl){
    require 'inc/db.php';
    $sql = "SELECT $col FROM $tbl WHERE $key=:key_value";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array(':key_value' => $key_value));
    return $stmt->fetchColumn();
}

//Function that can get any query from the developer
function custom_query($query){
    require 'inc/db.php';
    $sql = $query;
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $value = $stmt->fetchColumn();
    if (empty($value)) return 0;
    else return $value;
}
