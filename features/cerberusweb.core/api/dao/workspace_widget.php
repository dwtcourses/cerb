<?php
/************************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2018, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
 ***********************************************************************/

class DAO_WorkspaceWidget extends Cerb_ORMHelper {
	const CACHE_TTL = 'cache_ttl';
	const EXTENSION_ID = 'extension_id';
	const ID = 'id';
	const LABEL = 'label';
	const PARAMS_JSON = 'params_json';
	const POS = 'pos';
	const UPDATED_AT = 'updated_at';
	const WORKSPACE_TAB_ID = 'workspace_tab_id';
	
	private function __construct() {}

	static function getFields() {
		$validation = DevblocksPlatform::services()->validation();
		
		// mediumint(8) unsigned
		$validation
			->addField(self::CACHE_TTL)
			->uint(3)
			;
		// varchar(255)
		$validation
			->addField(self::EXTENSION_ID)
			->string()
			->setRequired(true)
			->setMaxLength(255)
			;
		// int(10) unsigned
		$validation
			->addField(self::ID)
			->id()
			->setEditable(false)
			;
		// varchar(255)
		$validation
			->addField(self::LABEL)
			->string()
			->setRequired(true)
			->setMaxLength(255)
			;
		// text
		$validation
			->addField(self::PARAMS_JSON)
			->string()
			->setMaxLength(65535)
			;
		// char(4)
		$validation
			->addField(self::POS)
			->string()
			->setMaxLength(4)
			;
		// int(10) unsigned
		$validation
			->addField(self::UPDATED_AT)
			->timestamp()
			;
		// int(10) unsigned
		$validation
			->addField(self::WORKSPACE_TAB_ID)
			->id()
			->setRequired(true)
			->addValidator($validation->validators()->contextId(Context_WorkspaceTab::ID))
			;
		$validation
			->addField('_links')
			->string()
			->setMaxLength(65535)
			;
			
		return $validation->getFields();
	}
	
	static function create($fields) {
		$db = DevblocksPlatform::services()->database();
		
		$sql = "INSERT INTO workspace_widget () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $option_bits = 0, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$context = CerberusContexts::CONTEXT_WORKSPACE_WIDGET;
		self::_updateAbstract($context, $ids, $fields);
		
		if(!isset($fields[self::UPDATED_AT]))
			$fields[self::UPDATED_AT] = time();
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges($context, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'workspace_widget', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::services()->event();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.workspace_widget.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged($context, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('workspace_widget', $fields, $where);
	}
	
	static public function onBeforeUpdateByActor($actor, $fields, $id=null, &$error=null) {
		$context = CerberusContexts::CONTEXT_WORKSPACE_WIDGET;
		
		if(!self::_onBeforeUpdateByActorCheckContextPrivs($actor, $context, $id, $error))
			return false;
		
		if(!$id && !isset($fields[self::WORKSPACE_TAB_ID])) {
			$error = "A 'workspace_tab_id' is required.";
			return false;
		}
		
		if(isset($fields[self::WORKSPACE_TAB_ID])) {
			@$tab_id = $fields[self::WORKSPACE_TAB_ID];
			
			if(!$tab_id) {
				$error = "Invalid 'workspace_tab_id' value.";
				return false;
			}
			
			if(!Context_WorkspaceTab::isWriteableByActor($tab_id, $actor)) {
				$error = "You do not have permission to create widgets on this workspace tab.";
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_WorkspaceWidget[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::services()->database();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, extension_id, workspace_tab_id, label, updated_at, params_json, pos, cache_ttl ".
			"FROM workspace_widget ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}
	
	/**
	 *
	 * @param bool $nocache
	 * @return Model_WorkspaceWidget[]
	 */
	static function getAll($nocache=false) {
		//$cache = DevblocksPlatform::services()->cache();
		//if($nocache || null === ($objects = $cache->load(self::_CACHE_ALL))) {
			$objects = self::getWhere(null, DAO_WorkspaceWidget::LABEL, true, null, Cerb_ORMHelper::OPT_GET_MASTER_ONLY);
			
			//if(!is_array($objects))
			//	return false;
				
			//$cache->save($objects, self::_CACHE_ALL);
		//}
		
		return $objects;
	}

	/**
	 * @param integer $id
	 * @return Model_WorkspaceWidget
	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		// [TODO] Pull from cache
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	static function getByTab($tab_id) {
		return self::getWhere(sprintf("%s = %d",
				self::WORKSPACE_TAB_ID,
				$tab_id
			),
			DAO_WorkspaceWidget::POS,
			true
		);
	}
	
	/**
	 * 
	 * @param array $ids
	 * @return Model_WorkspaceWidget[]
	 */
	static function getIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		if(empty($ids))
			return [];
		
		if(!method_exists(get_called_class(), 'getWhere'))
			return [];
		
		$db = DevblocksPlatform::services()->database();

		$ids = DevblocksPlatform::importVar($ids, 'array:integer');

		$models = [];

		$results = static::getWhere(sprintf("id IN (%s)",
			implode(',', $ids)
		));

		// Sort $models in the same order as $ids
		foreach($ids as $id) {
			if(isset($results[$id]))
				$models[$id] = $results[$id];
		}

		unset($results);

		return $models;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_WorkspaceWidget[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_WorkspaceWidget();
			$object->cache_ttl = intval($row['cache_ttl']);
			$object->extension_id = $row['extension_id'];
			$object->id = intval($row['id']);
			$object->label = $row['label'];
			$object->pos = $row['pos'];
			$object->updated_at = intval($row['updated_at']);
			$object->workspace_tab_id = intval($row['workspace_tab_id']);
			
			if(false != ($params = @json_decode($row['params_json'], true)))
				$object->params = $params;
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('workspace_widget');
	}
	
	static function countByWorkspaceTabId($tab_id) {
		$db = DevblocksPlatform::services()->database();
		
		$sql = sprintf("SELECT count(workspace_tab_id) FROM workspace_widget WHERE workspace_tab_id = %d",
			$tab_id
		);
		return intval($db->GetOneSlave($sql));
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::services()->database();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM workspace_widget WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::services()->event();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_WORKSPACE_WIDGET,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	static function deleteByTab($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::services()->database();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM workspace_widget WHERE workspace_tab_id IN (%s)", $ids_list));
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_WorkspaceWidget::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_WorkspaceWidget', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"workspace_widget.id as %s, ".
			"workspace_widget.extension_id as %s, ".
			"workspace_widget.workspace_tab_id as %s, ".
			"workspace_widget.label as %s, ".
			"workspace_widget.updated_at as %s, ".
			"workspace_widget.params_json as %s, ".
			"workspace_widget.pos as %s, ".
			"workspace_widget.cache_ttl as %s ",
				SearchFields_WorkspaceWidget::ID,
				SearchFields_WorkspaceWidget::EXTENSION_ID,
				SearchFields_WorkspaceWidget::WORKSPACE_TAB_ID,
				SearchFields_WorkspaceWidget::LABEL,
				SearchFields_WorkspaceWidget::UPDATED_AT,
				SearchFields_WorkspaceWidget::PARAMS_JSON,
				SearchFields_WorkspaceWidget::POS,
				SearchFields_WorkspaceWidget::CACHE_TTL
			);
			
		$join_sql = "FROM workspace_widget ";
		
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_WorkspaceWidget');
	
		return array(
			'primary_table' => 'workspace_widget',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		);
	}
	
	/**
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::services()->database();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
			
		if($limit > 0) {
			if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
				return false;
		} else {
			if(false == ($rs = $db->ExecuteSlave($sql)))
				return false;
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_WorkspaceWidget::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(workspace_widget.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_WorkspaceWidget extends DevblocksSearchFields {
	const ID = 'w_id';
	const EXTENSION_ID = 'w_extension_id';
	const WORKSPACE_TAB_ID = 'w_workspace_tab_id';
	const LABEL = 'w_label';
	const UPDATED_AT = 'w_updated_at';
	const PARAMS_JSON = 'w_params_json';
	const POS = 'w_pos';
	const CACHE_TTL = 'w_cache_ttl';
	
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'workspace_widget.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_WORKSPACE_WIDGET => new DevblocksSearchFieldContextKeys('workspace_widget.id', self::ID),
			CerberusContexts::CONTEXT_WORKSPACE_TAB => new DevblocksSearchFieldContextKeys('workspace_widget.workspace_tab_id', self::WORKSPACE_TAB_ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, CerberusContexts::CONTEXT_WORKSPACE_WIDGET, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_HAS_FIELDSET:
				return self::_getWhereSQLFromVirtualSearchSqlField($param, CerberusContexts::CONTEXT_CUSTOM_FIELDSET, sprintf('SELECT context_id FROM context_to_custom_fieldset WHERE context = %s AND custom_fieldset_id IN (%%s)', Cerb_ORMHelper::qstr(CerberusContexts::CONTEXT_WORKSPACE_WIDGET)), self::getPrimaryKey());
				break;
				
			default:
				if('cf_' == substr($param->field, 0, 3)) {
					return self::_getWhereSQLFromCustomFields($param);
				} else {
					return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
				}
				break;
		}
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::CACHE_TTL => new DevblocksSearchField(self::CACHE_TTL, 'workspace_widget', 'cache_ttl', $translate->_('Cache TTL'), Model_CustomField::TYPE_NUMBER, true),
			self::EXTENSION_ID => new DevblocksSearchField(self::EXTENSION_ID, 'workspace_widget', 'extension_id', $translate->_('common.type'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::ID => new DevblocksSearchField(self::ID, 'workspace_widget', 'id', $translate->_('common.id'), null, true),
			self::LABEL => new DevblocksSearchField(self::LABEL, 'workspace_widget', 'label', $translate->_('common.label'), null, true),
			self::PARAMS_JSON => new DevblocksSearchField(self::PARAMS_JSON, 'workspace_widget', 'params_json', null, null, false),
			self::POS => new DevblocksSearchField(self::POS, 'workspace_widget', 'pos', $translate->_('common.order'), null, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'workspace_widget', 'updated_at', $translate->_('common.updated'), null, true),
			self::WORKSPACE_TAB_ID => new DevblocksSearchField(self::WORKSPACE_TAB_ID, 'workspace_widget', 'workspace_tab_id', $translate->_('common.workspace.tab'), Model_CustomField::TYPE_NUMBER, true),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_WorkspaceWidget {
	public $id = 0;
	public $extension_id = '';
	public $workspace_tab_id = 0;
	public $label = '';
	public $updated_at = 0;
	public $pos = '0000';
	public $cache_ttl = 60;
	public $params = [];
	
	function getExtension() {
		return Extension_WorkspaceWidget::get($this->extension_id);
	}
	
	function getWorkspaceTab() {
		return DAO_WorkspaceTab::get($this->workspace_tab_id);
	}
	
	function getWorkspacePage() {
		if(false == ($tab = $this->getWorkspaceTab()))
			return;
		
		return $tab->getWorkspacePage();
	}
	
	function render() {
		if(false == ($extension = $this->getExtension()))
			return;

		$extension->render($this);
	}
};

class View_WorkspaceWidget extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'workspace_widgets';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = DevblocksPlatform::translateCapitalized('common.workspace.widgets');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_WorkspaceWidget::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_WorkspaceWidget::LABEL,
			SearchFields_WorkspaceWidget::EXTENSION_ID,
			SearchFields_WorkspaceWidget::WORKSPACE_TAB_ID,
			SearchFields_WorkspaceWidget::UPDATED_AT,
		);

		$this->addColumnsHidden(array(
			SearchFields_WorkspaceWidget::PARAMS_JSON,
			SearchFields_WorkspaceWidget::VIRTUAL_CONTEXT_LINK,
			SearchFields_WorkspaceWidget::VIRTUAL_HAS_FIELDSET,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_WorkspaceWidget::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_WorkspaceWidget');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_WorkspaceWidget', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_WorkspaceWidget', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = [];

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_WorkspaceWidget::EXTENSION_ID:
				case SearchFields_WorkspaceWidget::WORKSPACE_TAB_ID:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_WorkspaceWidget::VIRTUAL_CONTEXT_LINK:
				case SearchFields_WorkspaceWidget::VIRTUAL_HAS_FIELDSET:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if(DevblocksPlatform::strStartsWith($field_key, 'cf_'))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = [];
		$fields = $this->getFields();
		$context = CerberusContexts::CONTEXT_WORKSPACE_WIDGET;

		if(!isset($fields[$column]))
			return [];
		
		switch($column) {
			case SearchFields_WorkspaceWidget::EXTENSION_ID:
				$widget_extensions = Extension_WorkspaceWidget::getAll(false);
				
				$label_map = array_map(
					function($manifest) {
						return DevblocksPlatform::translateCapitalized($manifest->name);
					},
					$widget_extensions
				);
				
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_map, '=', 'value');
				break;
				
			case SearchFields_WorkspaceWidget::WORKSPACE_TAB_ID:
				$label_func = function($ids) {
					$tabs = DAO_WorkspaceTab::getIds($ids);
					return array_column($tabs, 'name', 'id');
				};
				
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_func, '=', 'value');
				break;
				
			case SearchFields_WorkspaceWidget::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;
				
			case SearchFields_WorkspaceWidget::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;

			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_WorkspaceWidget::getFields();
	
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkspaceWidget::LABEL, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'fieldset' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_WorkspaceWidget::VIRTUAL_HAS_FIELDSET),
					'examples' => [
						['type' => 'search', 'context' => CerberusContexts::CONTEXT_CUSTOM_FIELDSET, 'qr' => 'context:' . CerberusContexts::CONTEXT_WORKSPACE_WIDGET],
					]
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_WorkspaceWidget::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_WORKSPACE_WIDGET, 'q' => ''],
					]
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkspaceWidget::LABEL, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'tab.id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_WorkspaceWidget::WORKSPACE_TAB_ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_WORKSPACE_TAB, 'q' => 'type:"core.workspace.tab"'],
					]
				),
			'tab.pos' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WorkspaceWidget::POS),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_WorkspaceWidget::UPDATED_AT),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links', SearchFields_WorkspaceWidget::VIRTUAL_CONTEXT_LINK);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			case 'fieldset':
				return DevblocksSearchCriteria::getVirtualQuickSearchParamFromTokens($field, $tokens, '*_has_fieldset');
				break;
			
			default:
				if($field == 'links' || substr($field, 0, 6) == 'links.')
					return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_WORKSPACE_WIDGET);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/workspaces/widgets/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_WorkspaceWidget::EXTENSION_ID:
				$widget_extensions = Extension_WorkspaceWidget::getAll(false);
				$label_map = array_column($widget_extensions, 'name', 'id');
				parent::_renderCriteriaParamString($param, $label_map);
				break;
				
			case SearchFields_WorkspaceWidget::WORKSPACE_TAB_ID:
				$label_map = function($ids) {
					$tabs = DAO_WorkspaceTab::getIds($ids);
					return array_column($tabs, 'name', 'id');
				};
				parent::_renderCriteriaParamString($param, $label_map);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_WorkspaceWidget::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
			
			case SearchFields_WorkspaceWidget::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_WorkspaceWidget::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_WorkspaceWidget::ID:
			case SearchFields_WorkspaceWidget::EXTENSION_ID:
			case SearchFields_WorkspaceWidget::WORKSPACE_TAB_ID:
			case SearchFields_WorkspaceWidget::LABEL:
			case SearchFields_WorkspaceWidget::UPDATED_AT:
			case SearchFields_WorkspaceWidget::PARAMS_JSON:
			case SearchFields_WorkspaceWidget::POS:
			case SearchFields_WorkspaceWidget::CACHE_TTL:
			case 'placeholder_string':
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case 'placeholder_number':
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case 'placeholder_date':
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_WorkspaceWidget::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_WorkspaceWidget::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
};

class Context_WorkspaceWidget extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	const ID = 'cerberusweb.contexts.workspace.widget';
	
	static function isReadableByActor($models, $actor) {
		return CerberusContexts::isReadableByDelegateOwner($actor, CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $models, 'tab_page_owner_');
	}
	
	static function isWriteableByActor($models, $actor) {
		return CerberusContexts::isWriteableByDelegateOwner($actor, CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $models, 'tab_page_owner_');
	}
	
	function getRandom() {
		return DAO_WorkspaceTab::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::services()->url();
		$url = $url_writer->writeNoProxy('c=profiles&type=workspace_widget&id='.$context_id, true);
		return $url;
	}
	
	function profileGetFields($model=null) {
		$translate = DevblocksPlatform::getTranslationService();
		$properties = [];
		
		if(is_null($model))
			$model = new Model_WorkspaceWidget();
		
		$properties['name'] = array(
			'label' => mb_ucfirst($translate->_('common.name')),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $model->id,
			'params' => [
				'context' => self::ID,
			],
		);
		
		$properties['extension_id'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.type'),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $model->extension_id,
		);
		
		$properties['workspace_tab_id'] = array(
			'label' => DevblocksPlatform::translateCapitalized('dashboard'),
			'type' => Model_CustomField::TYPE_LINK,
			'value' => $model->workspace_tab_id,
			'params' => [
				'context' => CerberusContexts::CONTEXT_WORKSPACE_TAB
			]
		);
		
		$properties['cache_ttl'] = array(
			'label' => DevblocksPlatform::translate('Cache TTL'),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => DevblocksPlatform::strSecsToString($model->cache_ttl),
		);
		
		$properties['updated'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $model->updated_at,
		);
		
		return $properties;
	}
	
	function getMeta($context_id) {
		$url_writer = DevblocksPlatform::services()->url();

		if(null == ($workspace_widget = DAO_WorkspaceWidget::get($context_id)))
			return [];
		
		return array(
			'id' => $workspace_widget->id,
			'name' => $workspace_widget->label,
			'permalink' => $this->profileGetUrl($context_id),
			'updated' => $workspace_widget->updated_at,
		);
	}
	
	function getDefaultProperties() {
		return array(
			'extension_id',
			'tab__label',
			'updated_at',
		);
	}
	
	function getContext($widget, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Workspace Widget:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_WORKSPACE_WIDGET);
		
		// Polymorph
		if(is_numeric($widget)) {
			$widget = DAO_WorkspaceWidget::get($widget);
		} elseif($widget instanceof Model_WorkspaceWidget) {
			// It's what we want already.
		} elseif(is_array($widget)) {
			$widget = Cerb_ORMHelper::recastArrayToModel($widget, 'Model_WorkspaceWidget');
		} else {
			$widget = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'extension_id' => $prefix.$translate->_('common.type'),
			'id' => $prefix.$translate->_('common.id'),
			'label' => $prefix.$translate->_('common.label'),
			'params' => $prefix.$translate->_('common.params'),
			'pos' => $prefix.$translate->_('common.order'),
			'updated_at' => $prefix.$translate->_('common.updated'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'extension_id' => Model_CustomField::TYPE_SINGLE_LINE,
			'id' => Model_CustomField::TYPE_NUMBER,
			'label' => Model_CustomField::TYPE_SINGLE_LINE,
			'params' => null,
			'pos' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated_at' => Model_CustomField::TYPE_DATE,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Token values
		$token_values = [];
		
		$token_values['_context'] = CerberusContexts::CONTEXT_WORKSPACE_WIDGET;
		$token_values['_types'] = $token_types;
		
		// Token values
		if(null != $widget) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $widget->label;
			$token_values['id'] = $widget->id;
			$token_values['extension_id'] = $widget->extension_id;
			$token_values['label'] = $widget->label;
			$token_values['params'] = $widget->params;
			$token_values['pos'] = $widget->pos;
			$token_values['updated_at'] = $widget->updated_at;
			
			$token_values['tab_id'] = $widget->workspace_tab_id;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($widget, $token_values);
		}
		
		// Tab
		$merge_token_labels = [];
		$merge_token_values = [];
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKSPACE_TAB, null, $merge_token_labels, $merge_token_values, '', true);

		CerberusContexts::merge(
			'tab_',
			$prefix.'Tab:',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		return true;
	}
	
	function getKeyToDaoFieldMap() {
		return [
			'cache_ttl' => DAO_WorkspaceWidget::CACHE_TTL,
			'id' => DAO_WorkspaceWidget::ID,
			'extension_id' => DAO_WorkspaceWidget::EXTENSION_ID,
			'label' => DAO_WorkspaceWidget::LABEL,
			'links' => '_links',
			'pos' => DAO_WorkspaceWidget::POS,
			'tab_id' => DAO_WorkspaceWidget::WORKSPACE_TAB_ID,
			'updated_at' => DAO_WorkspaceWidget::UPDATED_AT,
		];
	}
	
	function getDaoFieldsFromKeyAndValue($key, $value, &$out_fields, &$error) {
		$dict_key = DevblocksPlatform::strLower($key);
		switch($dict_key) {
			case 'links':
				$this->_getDaoFieldsLinks($value, $out_fields, $error);
				break;
			
			case 'params':
				if(!is_array($value)) {
					$error = 'must be an object.';
					return false;
				}
				
				if(false == ($json = json_encode($value))) {
					$error = 'could not be JSON encoded.';
					return false;
				}
				
				$out_fields[DAO_WorkspaceWidget::PARAMS_JSON] = $json;
				break;
		}
		
		return true;
	}
	
	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_WORKSPACE_WIDGET;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, false);
		}
		
		switch($token) {
			case 'data':
				$values = $dictionary;
				
				if(null == ($widget = DAO_WorkspaceWidget::get($context_id)))
					break;
				
				$widget_ext = Extension_WorkspaceWidget::get($dictionary['extension_id']);

				$values['data'] = false;
				
				if(!($widget_ext instanceof ICerbWorkspaceWidget_ExportData))
					break;

				$json = json_decode($widget_ext->exportData($widget, 'json'), true);

				if(!is_array($json))
					break;
				
				// Remove redundant data
				if(isset($json['widget'])) {
					unset($json['widget']['label']);
					unset($json['widget']['version']);
				}
				
				$values['data'] = isset($json['widget']) ? $json['widget'] : $json;
				break;
				
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $links);
				break;
			
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}

	function getChooserView($view_id=null) {
		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = DevblocksPlatform::translateCapitalized('common.workspace.widgets');
		$view->renderSortBy = SearchFields_WorkspaceWidget::UPDATED_AT;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Widgets';
		
		$params_req = [];
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_WorkspaceWidget::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_WorkspaceWidget::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);
		
		$context = CerberusContexts::CONTEXT_WORKSPACE_WIDGET;
		
		$model = new Model_WorkspaceWidget();
		$model->cache_ttl = 60;
		
		if(!empty($context_id)) {
			$model = DAO_WorkspaceWidget::get($context_id);
			
		} else {
			if(!empty($edit)) {
				$tokens = explode(' ', trim($edit));
				
				foreach($tokens as $token) {
					@list($k,$v) = explode(':', $token);
					
					if($v)
					switch($k) {
						case 'tab.id':
							$model->workspace_tab_id = intval($v);
							break;
					}
				}
			}
		}
		
		if(empty($context_id) || $edit) {
			if(isset($model))
				$tpl->assign('model', $model);
			
			$widget_extensions = Extension_WorkspaceWidget::getAll(false);
			$tpl->assign('widget_extensions', $widget_extensions);
			
			// Custom fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
	
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			// View
			$tpl->assign('id', $context_id);
			$tpl->assign('view_id', $view_id);
			$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/peek_edit.tpl');
			
		} else {
			// Links
			$links = array(
				$context => array(
					$context_id => 
						DAO_ContextLink::getContextLinkCounts(
							$context,
							$context_id,
							[]
						),
				),
			);
			$tpl->assign('links', $links);
			
			// Timeline
			if($context_id) {
				$timeline_json = Page_Profiles::getTimelineJson(Extension_DevblocksContext::getTimelineComments($context, $context_id));
				$tpl->assign('timeline_json', $timeline_json);
			}

			// Context
			if(false == ($context_ext = Extension_DevblocksContext::get($context)))
				return;
			
			// Dictionary
			$labels = [];
			$values = [];
			CerberusContexts::getContext($context, $model, $labels, $values, '', true, false);
			$dict = DevblocksDictionaryDelegate::instance($values);
			$tpl->assign('dict', $dict);
			
			$properties = $context_ext->getCardProperties();
			$tpl->assign('properties', $properties);
			
			// Card search buttons
			$search_buttons = $context_ext->getCardSearchButtons($dict, []);
			$tpl->assign('search_buttons', $search_buttons);
			
			$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/peek.tpl');
		}
	}
};