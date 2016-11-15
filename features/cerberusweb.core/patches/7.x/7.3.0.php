<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// Modify `decision_node` to add 'subroutine' and 'loop' types
// Add `status_id` field to nodes

if(!isset($tables['decision_node'])) {
	$logger->error("The 'decision_node' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('decision_node');

if(isset($columns['node_type']) && 0 != strcasecmp('varchar(16)', $columns['node_type']['type'])) {
	$db->ExecuteMaster("ALTER TABLE decision_node MODIFY COLUMN node_type varchar(16) not null default ''");
}

if(!isset($columns['status_id']))
	$db->ExecuteMaster("ALTER TABLE decision_node ADD COLUMN status_id tinyint(1) unsigned not null default 0");

// ===========================================================================
// Modify `trigger_event` to add 'updated_at'

if(!isset($tables['trigger_event'])) {
	$logger->error("The 'trigger_event' table does not exist.");
	return FALSE;
}

list($columns, $indexes) = $db->metaTable('trigger_event');

if(!isset($columns['updated_at'])) {
	$db->ExecuteMaster("ALTER TABLE trigger_event ADD COLUMN updated_at int unsigned not null default 0");
	$db->ExecuteMaster("UPDATE trigger_event SET updated_at = UNIX_TIMESTAMP()");
}

// ===========================================================================
// Finish up

return TRUE;
