<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     resource
 * Name:     string
 * Version:  1.1
 * Author:   Joshua Thijssen <jthijssen@noxlogic.nl>
 * Credits:  
 * Purpose:  using php strings as smarty resouces
 * Input:    
 *
 * Examples: $smarty->display ("string:<b>{$foo}</b>");
 * -------------------------------------------------------------
 */



function smarty_resource_string_source($tpl_name, &$tpl_source, &$smarty_obj){
  $tpl_source = $tpl_name;
  return true;
}

function smarty_resource_string_timestamp($tpl_name, &$tpl_timestamp, &$smarty_obj){
  $tpl_timestamp = time ();
  return true;
}

function smarty_resource_string_secure($tpl_name, &$smarty_obj){
  // assume all templates are secure
  return true;
}

function smarty_resource_string_trusted($tpl_name, &$smarty_obj){
  // not used for templates
}

// register the resource name "string"
$smarty->register_resource("string", array("smarty_resource_string_source",
                                           "smarty_resource_string_timestamp",
                                           "smarty_resource_string_secure",
                                           "smarty_resource_string_trusted"));