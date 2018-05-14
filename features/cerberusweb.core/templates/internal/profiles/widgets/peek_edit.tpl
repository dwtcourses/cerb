{$peek_context = CerberusContexts::CONTEXT_PROFILE_WIDGET}
{$form_id = uniqid()}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="{$form_id}">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="profile_widget">
<input type="hidden" name="action" value="savePeekJson">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<table cellspacing="0" cellpadding="2" border="0" width="98%">
	<tr>
		<td width="1%" nowrap="nowrap"><b>{'common.name'|devblocks_translate|capitalize}:</b></td>
		<td width="99%">
			<input type="text" name="name" value="{$model->name}" style="width:98%;" autofocus="autofocus">
		</td>
	</tr>

	<tr>
		<td width="1%" nowrap="nowrap" valign="top">
			<b>{'dashboard'|devblocks_translate|capitalize}:</b>
		</td>
		<td width="99%">
			<button type="button" class="chooser-abstract" data-field-name="profile_tab_id" data-context="{CerberusContexts::CONTEXT_PROFILE_TAB}" data-single="true" data-query-required="type:&quot;cerb.profile.tab.dashboard&quot;" data-autocomplete="type:&quot;cerb.profile.tab.dashboard&quot;" data-autocomplete-if-empty="true"><span class="glyphicons glyphicons-search"></span></button>
			
			<ul class="bubbles chooser-container">
				{if $model->profile_tab_id}
				{$tab = $model->getProfileTab()}
				{if $tab}
					<li><input type="hidden" name="profile_tab_id" value="{$tab->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_PROFILE_TAB}" data-context-id="{$tab->id}">{$tab->name}</a></li>
				{/if}
				{/if}
			</ul>
		</td>
	</tr>

	<tr>
		<td width="1%" nowrap="nowrap" valign="top">
			<b>{'common.type'|devblocks_translate|capitalize}:</b>
		</td>
		<td width="99%" valign="top">
			{if $model->id}
				{$widget_extension = $model->getExtension()}
				{$widget_extension->manifest->name}
			{else}
				<select name="extension_id">
					<option value="">-- {'common.choose'|devblocks_translate|lower} --</option>
					{foreach from=$widget_extensions item=widget_extension}
					<option value="{$widget_extension->id}">{$widget_extension->name}</option>
					{/foreach}
				</select>
			{/if}
		</td>
	</tr>
	
	<tr>
		<td width="1%" nowrap="nowrap" valign="top">
			<b>{'common.width'|devblocks_translate|capitalize}:</b>
		</td>
		<td width="99%" valign="top">
			{$widths = [1=>'1X', 2=>'2X', 3=>'3X', 4=>'4X']}
			<select name="width_units">
				{foreach from=$widths item=width_label key=width}
				<option value="{$width}" {if $model->width_units == $width}selected="selected"{/if}>{$width_label}</option>
				{/foreach}
			</select>
		</td>
	</tr>

	{if !empty($custom_fields)}
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false tbody=true}
	{/if}
</table>

{* The rest of config comes from the widget *}
<div class="cerb-widget-params">
{if $model->id}
	{$widget_extension = $model->getExtension()}
	{if $widget_extension && method_exists($widget_extension,'renderConfig')}
		{$widget_extension->renderConfig($model)}
	{/if}
{/if}
</div>

<div class="cerb-placeholder-menu" style="display:none;">
{include file="devblocks:cerberusweb.core::internal/profiles/tabs/dashboard/toolbar.tpl"}
</div>

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=$peek_context context_id=$model->id}

{if !empty($model->id)}
<fieldset style="display:none;" class="delete">
	<legend>{'common.delete'|devblocks_translate|capitalize}</legend>
	
	<div>
		Are you sure you want to permanently delete this profile widget?
	</div>
	
	<button type="button" class="delete red">{'common.yes'|devblocks_translate|capitalize}</button>
	<button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();">{'common.no'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

<div class="status"></div>

<div class="buttons" style="margin-top:10px;">
	<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	{if !empty($model->id) && $active_worker->hasPriv("contexts.{$peek_context}.delete")}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
</div>

</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#{$form_id}');
	var $popup = genericAjaxPopupFind($frm);
	
	$popup.one('popup_open', function(event,ui) {
		$popup.dialog('option','title',"{'Profile Widget'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		$popup.css('overflow', 'inherit');

		// Buttons
		$popup.find('button.submit').click(Devblocks.callbackPeekEditSave);
		$popup.find('button.delete').click({ mode: 'delete' }, Devblocks.callbackPeekEditSave);
		
		var $toolbar = $popup.find('.cerb-placeholder-menu').detach();
		var $params = $popup.find('.cerb-widget-params');

		// Abstract choosers
		$popup.find('button.chooser-abstract')
			.cerbChooserTrigger()
			.on('cerb-chooser-saved', function(e) {
				var $target = $(e.target);
				
				if($target.attr('data-field-name') == 'profile_tab_id') {
					var $bubble = $target.siblings('ul.chooser-container').find('> li:first input:hidden');
					var id = $bubble.first().val();
					
					if(id) {
						$toolbar.empty().detach();
						genericAjaxGet($toolbar,'c=profiles&a=handleProfileTabAction&tab_id=' + encodeURIComponent(id) + '&action=getPlaceholderToolbarForTab');
						
					} else {
						$toolbar.empty().detach();
					}
				}
			})
			;
		
		// Abstract peeks
		$popup.find('.cerb-peek-trigger').cerbPeekTrigger();
		
		// Switching extension params
		var $select = $popup.find('select[name=extension_id]');
		
		$select.on('change', function(e) {
			var extension_id = $select.val();
			
			if(0 == extension_id.length) {
				$params.hide().empty();
				return;
			}
			
			// Fetch via Ajax
			genericAjaxGet($params, 'c=profiles&a=renderWidgetConfig&extension=' + encodeURIComponent(extension_id), function(html) {
				$params.find('button.chooser-abstract').cerbChooserTrigger();
				$params.find('.cerb-peek-trigger').cerbPeekTrigger();
			});
		});
		
		// Placeholder toolbar
		$popup.delegate(':text.placeholders, textarea.placeholders, pre.placeholders', 'focus', function(e) {
			e.stopPropagation();
			
			var $target = $(e.target);
			var $parent = $target.closest('.ace_editor');
			
			if(0 != $parent.length) {
				$toolbar.find('div.tester').html('');
				$toolbar.find('ul.menu').hide();
				$toolbar.show().insertAfter($parent);
				$toolbar.data('src', $parent);
				
			} else {
				if(0 == $target.nextAll($toolbar).length) {
					$toolbar.find('div.tester').html('');
					$toolbar.find('ul.menu').hide();
					$toolbar.show().insertAfter($target);
					$toolbar.data('src', $target);
					
					// If a markItUp editor, move to parent
					if($target.is('.markItUpEditor')) {
						$target = $target.closest('.markItUp').parent();
						$toolbar.find('button.tester').hide();
						
					} else {
						$toolbar.find('button.tester').show();
					}
				}
			}
		});
		
	});
});
</script>