<?php
$path = realpath(dirname(__FILE__).'/../') . DIRECTORY_SEPARATOR;

//DevblocksPlatform::registerClasses($path. 'api/App.php', array(
//    'C4_TicketAuditLogView'
//));

class ChReportsPlugin extends DevblocksPlugin {
	function load(DevblocksPluginManifest $manifest) {
	}
};

abstract class Extension_Report extends DevblocksExtension {
	function __construct($manifest) {
		parent::DevblocksExtension($manifest);
	}
	
	function render() {
		// Overload 
	}
};

abstract class Extension_ReportGroup extends DevblocksExtension {
	function __construct($manifest) {
		parent::DevblocksExtension($manifest);
	}
};

class ChReportGroupTickets extends Extension_ReportGroup {
	function __construct($manifest) {
		parent::__construct($manifest);
	}
};

class ChReportGroupWorkers extends Extension_ReportGroup {
	function __construct($manifest) {
		parent::__construct($manifest);
	}
};

class ChReportGroupSpam extends Extension_ReportGroup {
	function __construct($manifest) {
		parent::__construct($manifest);
	}
};

class ChReportGroupRoster extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$rosters = DAO_Group::getRosters();
		$tpl->assign('rosters', $rosters);

		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);

		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/group_roster/index.tpl.php');
	}
};

class ChReportNewTickets extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$tpl->assign('start', '-30 days');
		$tpl->assign('end', 'now');
		
		$db = DevblocksPlatform::getDatabaseService();
		
		// Year shortcuts
		$years = array();
		$sql = "SELECT date_format(from_unixtime(created_date),'%Y') as year FROM ticket WHERE created_date > 0 GROUP BY year having year <= date_format(now(),'%Y') ORDER BY year desc limit 0,10";
		$rs = $db->query($sql);
		while(!$rs->EOF) {
			$years[] = intval($rs->fields['year']);
			$rs->MoveNext();
		}
		$tpl->assign('years', $years);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/new_tickets/index.tpl.php');
	}
	
	function getNewTicketsReportAction() {
		@$age = DevblocksPlatform::importGPC($_REQUEST['age'],'string','30d');
		
		$db = DevblocksPlatform::getDatabaseService();

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);

		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
				
		if($start_time === false || $end_time === false) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
			
			$tpl->assign('invalidDate', true);
		}
		
		// reload variables in template
		$tpl->assign('start', $start);
		$tpl->assign('end', $end);
		$tpl->assign('age_dur', abs(floor(($start_time - $end_time)/86400)));
		
	   	// Top Buckets
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		$group_buckets = DAO_Bucket::getTeams();
		$tpl->assign('group_buckets', $group_buckets);
		
		$sql = sprintf("SELECT count(*) AS hits, team_id, category_id ".
			"FROM ticket ".
			"WHERE created_date > %d AND created_date <= %d ".
			"AND is_deleted = 0 ".
			"AND spam_score < 0.9000 ".
			"AND spam_training != 'S' ".
			"GROUP BY team_id, category_id ",
			$start_time,
			$end_time
		);
		$rs_buckets = $db->Execute($sql);
	
		$group_counts = array();
		while(!$rs_buckets->EOF) {
			$team_id = intval($rs_buckets->fields['team_id']);
			$category_id = intval($rs_buckets->fields['category_id']);
			$hits = intval($rs_buckets->fields['hits']);
			
			if(!isset($group_counts[$team_id]))
				$group_counts[$team_id] = array();
				
			$group_counts[$team_id][$category_id] = $hits;
			@$group_counts[$team_id]['total'] = intval($group_counts[$team_id]['total']) + $hits;
			
			$rs_buckets->MoveNext();
		}
		$tpl->assign('group_counts', $group_counts);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/new_tickets/html.tpl.php');
	}
	
	function getTicketChartDataAction() {
		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		@$countonly = DevblocksPlatform::importGPC($_REQUEST['countonly'],'integer',0);
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$groups = DAO_Group::getAll();
		
		$sql = sprintf("SELECT team.id as group_id, ".
				"count(*) as hits ".
				"FROM ticket t inner join team on t.team_id = team.id ".
				"WHERE t.created_date > %d ".
				"AND t.created_date <= %d ".
				"AND t.is_deleted = 0 ".
				"AND t.spam_score < 0.9000 ".
				"AND t.spam_training != 'S' ".
				"GROUP BY group_id ORDER by team.name desc ",
				$start_time,
				$end_time
				);
		$rs = $db->Execute($sql);

		if($countonly) {
			echo intval($rs->RecordCount());
			return;
		}
		
	    while(!$rs->EOF) {
	    	$hits = intval($rs->fields['hits']);
			$group_id = $rs->fields['group_id'];
			
			echo $groups[$group_id]->name, "\t", $hits . "\n";
			
		    $rs->MoveNext();
	    }
	}
}

class ChReportWorkerReplies extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$tpl->assign('start', '-30 days');
		$tpl->assign('end', 'now');
		
		$db = DevblocksPlatform::getDatabaseService();
		
		// Years
		$years = array();
		$sql = "SELECT date_format(from_unixtime(created_date),'%Y') as year FROM message WHERE created_date > 0 AND is_outgoing = 1 GROUP BY year having year <= date_format(now(),'%Y') ORDER BY year desc limit 0,10";
		$rs = $db->query($sql);
		while(!$rs->EOF) {
			$years[] = intval($rs->fields['year']);
			$rs->MoveNext();
		}
		$tpl->assign('years', $years);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/worker_replies/index.tpl.php');
	}
	
	function getWorkerRepliesReportAction() {
		@$age = DevblocksPlatform::importGPC($_REQUEST['age'],'string', '30d');
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
		
		if($start_time === false || $end_time === false) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
			
			$tpl->assign('invalidDate', true);
		}
		
		// reload variables in template
		$tpl->assign('start', $start);
		$tpl->assign('end', $end);
		$tpl->assign('age_dur', abs(floor(($start_time - $end_time)/86400)));
		
		// Top Workers
		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);
		
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		$sql = sprintf("SELECT count(*) AS hits, t.team_id, m.worker_id ".
			"FROM message m ".
			"INNER JOIN ticket t ON (t.id=m.ticket_id) ".
			"WHERE m.created_date > %d AND m.created_date <= %d ".
			"AND m.is_outgoing = 1 ".
			"AND t.is_deleted = 0 ".
			"GROUP BY t.team_id, m.worker_id ",
			$start_time,
			$end_time
		);
		$rs_workers = $db->Execute($sql);
		
		$worker_counts = array();
		while(!$rs_workers->EOF) {
			$hits = intval($rs_workers->fields['hits']);
			$team_id = intval($rs_workers->fields['team_id']);
			$worker_id = intval($rs_workers->fields['worker_id']);
			
			if(!isset($worker_counts[$worker_id]))
				$worker_counts[$worker_id] = array();
			
			$worker_counts[$worker_id][$team_id] = $hits;
			@$worker_counts[$worker_id]['total'] = intval($worker_counts[$worker_id]['total']) + $hits;
			$rs_workers->MoveNext();
		}
		$tpl->assign('worker_counts', $worker_counts);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/worker_replies/html.tpl.php');
	}
	
	function getWorkerRepliesChartAction() {
		header("content-type: text/plain");
	
		$db = DevblocksPlatform::getDatabaseService();
		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		@$countonly = DevblocksPlatform::importGPC($_REQUEST['countonly'],'integer',0);
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
		
		// Top Workers
		$workers = DAO_Worker::getAll();
		
		$sql = sprintf("SELECT count(*) AS hits, m.worker_id ".
			"FROM message m ".
			"INNER JOIN ticket t ON (t.id=m.ticket_id) ".
			"INNER JOIN worker w ON w.id=m.worker_id ".
			"WHERE m.created_date > %d AND m.created_date <= %d ".
			"AND m.is_outgoing = 1 ".
			"AND t.is_deleted = 0 ".
			"GROUP BY m.worker_id ORDER BY w.last_name DESC ",
			$start_time,
			$end_time
		);

		$rs_workers = $db->Execute($sql); /* @var $rs_workers ADORecordSet */
		
		if($countonly) {
			echo intval($rs_workers->RecordCount());
			return;
		}
		
		$worker_counts = array();
		
		while(!$rs_workers->EOF) {
			$hits = intval($rs_workers->fields['hits']);
			$worker_id = intval($rs_workers->fields['worker_id']);
			
			if(!isset($workers[$worker_id]))
				continue;

			echo $workers[$worker_id]->getName() , "\t" , $hits , "\n";
			$rs_workers->MoveNext();
		}
	}
	
}

class ChReportSpamWords extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT spam, nonspam FROM bayes_stats";
		if(null != ($row = $db->GetRow($sql))) {
			$num_spam = $row['spam'];
			$num_nonspam = $row['nonspam'];
		}
		
		$tpl->assign('num_spam', intval($num_spam));
		$tpl->assign('num_nonspam', intval($num_nonspam));
		
		$top_spam_words = array();
		$top_nonspam_words = array();
		
		$sql = "SELECT word,spam,nonspam FROM bayes_words ORDER BY spam desc LIMIT 0,100";
		$rs_spam = $db->Execute($sql);
		
		while(!$rs_spam->EOF) {
			$top_spam_words[$rs_spam->fields['word']] = array($rs_spam->fields['spam'], $rs_spam->fields['nonspam']);
			$rs_spam->MoveNext();
		}
		$tpl->assign('top_spam_words', $top_spam_words);
		
		$sql = "SELECT word,spam,nonspam FROM bayes_words ORDER BY nonspam desc LIMIT 0,100";
		$rs_nonspam = $db->Execute($sql);
		
		while(!$rs_nonspam->EOF) {
			$top_nonspam_words[$rs_nonspam->fields['word']] = array($rs_nonspam->fields['spam'], $rs_nonspam->fields['nonspam']);
			$rs_nonspam->MoveNext();
		}
		$tpl->assign('top_nonspam_words', $top_nonspam_words);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/spam_words/index.tpl.php');
	}
};

class ChReportSpamAddys extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$top_spam_addys = array();
		$top_nonspam_addys = array();
		
		$sql = "SELECT email,num_spam,num_nonspam,is_banned FROM address WHERE num_spam+num_nonspam > 0 ORDER BY num_spam desc LIMIT 0,100";
		$rs_spam = $db->Execute($sql);
		
		while(!$rs_spam->EOF) {
			$top_spam_addys[$rs_spam->fields['email']] = array($rs_spam->fields['num_spam'], $rs_spam->fields['num_nonspam'], $rs_spam->fields['is_banned']);
			$rs_spam->MoveNext();
		}
		$tpl->assign('top_spam_addys', $top_spam_addys);
		
		$sql = "SELECT email,num_spam,num_nonspam,is_banned FROM address WHERE num_spam+num_nonspam > 0 ORDER BY num_nonspam desc LIMIT 0,100";
		$rs_nonspam = $db->Execute($sql);
		
		while(!$rs_nonspam->EOF) {
			$top_nonspam_addys[$rs_nonspam->fields['email']] = array($rs_nonspam->fields['num_spam'], $rs_nonspam->fields['num_nonspam'], $rs_spam->fields['is_banned']);
			$rs_nonspam->MoveNext();
		}
		$tpl->assign('top_nonspam_addys', $top_nonspam_addys);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/spam_addys/index.tpl.php');
	}
};

class ChReportSpamDomains extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$top_spam_domains = array();
		$top_nonspam_domains = array();
		
		$sql = "select count(*) as hits, substring(email,locate('@',email)+1) as domain, sum(num_spam) as num_spam, sum(num_nonspam) as num_nonspam from address where num_spam+num_nonspam > 0 group by domain order by num_spam desc limit 0,100";
		$rs_spam = $db->Execute($sql);
		
		while(!$rs_spam->EOF) {
			$top_spam_domains[$rs_spam->fields['domain']] = array($rs_spam->fields['num_spam'], $rs_spam->fields['num_nonspam'], $rs_spam->fields['is_banned']);
			$rs_spam->MoveNext();
		}
		$tpl->assign('top_spam_domains', $top_spam_domains);
		
		$sql = "select count(*) as hits, substring(email,locate('@',email)+1) as domain, sum(num_spam) as num_spam, sum(num_nonspam) as num_nonspam from address where num_spam+num_nonspam > 0 group by domain order by num_nonspam desc limit 0,100";
		$rs_nonspam = $db->Execute($sql);
		
		while(!$rs_nonspam->EOF) {
			$top_nonspam_domains[$rs_nonspam->fields['domain']] = array($rs_nonspam->fields['num_spam'], $rs_nonspam->fields['num_nonspam'], $rs_spam->fields['is_banned']);
			$rs_nonspam->MoveNext();
		}
		$tpl->assign('top_nonspam_domains', $top_nonspam_domains);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/spam_domains/index.tpl.php');
	}
};

class ChReportAverageResponseTime extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/average_response_time/index.tpl.php');
	}
	
	function getAverageResponseTimeReportAction() {
		// init
		$db = DevblocksPlatform::getDatabaseService();
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);

		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		if (empty($start) && empty($end)) {
			$start_time = strtotime("-30 days");
			$end_time = strtotime("now");
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
		
		if($start_time === false || $end_time === false) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
			
			$tpl->assign('invalidDate', true);
		}
		
		// reload variables in template
		$tpl->assign('start', $start);
		$tpl->assign('end', $end);
		
		// set up necessary reference arrays
	   	$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		$group_buckets = DAO_Bucket::getTeams();
		$tpl->assign('group_buckets', $group_buckets);
	   	$workers = DAO_Worker::getAll();
	   	$tpl->assign('workers',$workers);
		
	   	// pull data from db
	   	$sql = sprintf("SELECT mm.id, mm.ticket_id, mm.created_date, mm.worker_id, mm.is_outgoing, t.team_id, t.category_id ".
			"FROM message m ".
	   		"INNER JOIN ticket t ON (t.id=m.ticket_id) ".
	   		"INNER JOIN message mm ON (mm.ticket_id=t.id) ".
			"WHERE m.created_date > %d AND m.created_date <= %d AND m.is_outgoing = 1 ".
	   		"ORDER BY ticket_id,id ",
			$start_time,
			$end_time
		);
		$rs_responses = $db->Execute($sql);
		
		// process and count results
	   	$group_responses = array();
	   	$worker_responses = array();
	   	$prev = array();
		while(!$rs_responses->EOF) {
			// load current data
			$id = intval($rs_responses->fields['id']);
			$ticket_id = intval($rs_responses->fields['ticket_id']);
			$created_date = intval($rs_responses->fields['created_date']);
			$worker_id = intval($rs_responses->fields['worker_id']);
			$is_outgoing = intval($rs_responses->fields['is_outgoing']);
			$team_id = intval($rs_responses->fields['team_id']);
			$category_id = intval($rs_responses->fields['category_id']);
			
			// we only add data if it's a worker reply to the same ticket as $prev
			if ($is_outgoing==1 && !empty($prev) && $ticket_id==$prev['ticket_id']) {
				// Initialize, if necessary
				if (!isset($group_responses[$team_id])) $group_responses[$team_id] = array();
				if (!isset($worker_responses[$worker_id])) $worker_responses[$worker_id] = array();
				
				// log reply and time
				@$group_responses[$team_id]['replies'] += 1;
				@$group_responses[$team_id]['time'] += $created_date - $prev['created_date'];
				@$worker_responses[$worker_id]['replies'] += 1;
				@$worker_responses[$worker_id]['time'] += $created_date - $prev['created_date'];
			}
			
			// Save this one as "previous" and move on
			$prev = array(
				'id'=>$id,
				'ticket_id'=>$ticket_id,
				'created_date'=>$created_date,
				'worker_id'=>$worker_id,
				'is_outgoing'=>$is_outgoing,
				'team_id'=>$team_id,
				'category_id'=>$category_id,
				);
			$rs_responses->MoveNext();
		}
		$tpl->assign('group_responses', $group_responses);
		$tpl->assign('worker_responses', $worker_responses);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/report/average_response_time/html.tpl.php');
	}
	
}

class ChReportsPage extends CerberusPageExtension {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);

		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
		
	function isVisible() {
		// check login
		$session = DevblocksPlatform::getSessionService();
		$visit = $session->getVisit();
		
		if(empty($visit)) {
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 * Proxy page actions from an extension's render() to the extension's scope.
	 *
	 */
	function actionAction() {
		@$extid = $_REQUEST['extid'];
		@$extid_a = DevblocksPlatform::strAlphaNumDash($_REQUEST['extid_a']);
		
		$action = $extid_a.'Action';
		
		$reportMft = DevblocksPlatform::getExtension($extid);
		
		// If it's a value report extension, proxy the action
		if(null != ($reportInst = DevblocksPlatform::getExtension($extid, true)) 
			&& $reportInst instanceof Extension_Report) {
				
			// If we asked for a value method on the extension, call it
			if(method_exists($reportInst, $action)) {
				call_user_method($action, $reportInst);
			}
		}
		
		return;
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;

		array_shift($stack); // reports
		@$reportId = array_shift($stack);
		$report = null;

		// We're given a specific report to display
		if(!empty($reportId)) {
			if(null != ($reportMft = DevblocksPlatform::getExtension($reportId))) {
				if(null != ($report = $reportMft->createInstance()) && $report instanceof Extension_Report) { /* @var $report Extension_Report */
					$report->render();
					return;
				}
			}
		}
		
		// If we don't have a selected report yet
		if(empty($report)) {
			// Organize into report groups
			$report_groups = array();
			$reportGroupMfts = DevblocksPlatform::getExtensions('cerberusweb.report.group', false);
			
			// [TODO] Alphabetize groups and nested reports
			
			// Load report groups
			if(!empty($reportGroupMfts))
			foreach($reportGroupMfts as $reportGroupMft) {
				$report_groups[$reportGroupMft->id] = array(
					'name' => $reportGroupMft->name,
					'reports' => array()
				);
			}
			
			$reportMfts = DevblocksPlatform::getExtensions('cerberusweb.report', false);
			
			// Load reports and file them under groups according to manifest
			if(!empty($reportMfts))
			foreach($reportMfts as $reportMft) {
				$report_group = $reportMft->params['report_group'];
				if(isset($report_group)) {
					$report_groups[$report_group]['reports'][] = $reportMft;
				}
			}
			
			$tpl->assign('report_groups', $report_groups);
		}

		$tpl->display('file:' . $this->tpl_path . '/reports/index.tpl.php');
	}
		
};

?>
