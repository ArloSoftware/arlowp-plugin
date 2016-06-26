<?php

function array_ikey_exists($key,$arr) { 
    if(preg_match("/".$key."/i", join(",", array_keys($arr))))                
        return true; 
    else 
        return false; 
} 