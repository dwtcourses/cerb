<?php
$db = DevblocksPlatform::services()->database();
$logger = DevblocksPlatform::services()->log();
$tables = $db->metaTables();

// ===========================================================================
// Import profile tab/widget defaults

$result = $db->GetOneMaster("SELECT COUNT(id) FROM profile_tab WHERE context = 'cerberusweb.contexts.datacenter.server'");

if(!$result) {
	// Server
	$db->ExecuteMaster("INSERT INTO profile_tab (name, context, extension_id, extension_params_json, updated_at) VALUES ('Overview','cerberusweb.contexts.datacenter.server','cerb.profile.tab.dashboard','{\"layout\":\"sidebar_left\"}',UNIX_TIMESTAMP())");
	$db->ExecuteMaster("SET @last_tab_id = LAST_INSERT_ID()");
	$db->ExecuteMaster("INSERT INTO profile_widget (name, profile_tab_id, extension_id, extension_params_json, zone, pos, width_units, updated_at) VALUES ('Server',@last_tab_id,'cerb.profile.tab.widget.fields','{\"context\":\"cerberusweb.contexts.datacenter.server\",\"context_id\":\"{{record_id}}\",\"properties\":[[\"created\",\"updated\"]],\"links\":{\"show\":\"1\"},\"search\":{\"context\":[\"cerberusweb.contexts.datacenter.domain\"],\"label_singular\":[\"Domain\"],\"label_plural\":[\"Domains\"],\"query\":[\"server.id:{{record_id}}\"]}}','sidebar',1,4,UNIX_TIMESTAMP())");
	$db->ExecuteMaster("INSERT INTO profile_widget (name, profile_tab_id, extension_id, extension_params_json, zone, pos, width_units, updated_at) VALUES ('Recent activity',@last_tab_id,'cerb.profile.tab.widget.worklist','{\"context\":\"cerberusweb.contexts.activity_log\",\"query_required\":\"target.server:(id{{record_id}})\",\"query\":\"sort:-created subtotal:activity\",\"render_limit\":\"5\",\"header_color\":\"#6a87db\",\"columns\":[\"c_created\"]}','content',1,4,UNIX_TIMESTAMP())");
	$db->ExecuteMaster("INSERT INTO profile_widget (name, profile_tab_id, extension_id, extension_params_json, zone, pos, width_units, updated_at) VALUES ('Discussion',@last_tab_id,'cerb.profile.tab.widget.comments','{\"context\":\"cerberusweb.contexts.datacenter.server\",\"context_id\":\"{{record_id}}\",\"height\":\"\"}','content',2,4,UNIX_TIMESTAMP())");
	$db->ExecuteMaster("INSERT IGNORE INTO devblocks_setting (plugin_id, setting, value) VALUES ('cerberusweb.core','profile:tabs:cerberusweb.contexts.datacenter.server',CONCAT('[',@last_tab_id,']'))");
}

return TRUE;