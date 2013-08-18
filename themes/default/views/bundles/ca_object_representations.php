<?php
/* ----------------------------------------------------------------------
 * bundles/ca_object_representations.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2009-2013 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */

	JavascriptLoadManager::register('sortableUI');

	$vs_id_prefix 		= $this->getVar('placement_code').$this->getVar('id_prefix');
	$t_instance 		= $this->getVar('t_instance');
	$t_item 			= $this->getVar('t_item');			// object representation
	$t_item_label		= $t_item->getLabelTableInstance();
	$t_item_rel 		= $this->getVar('t_item_rel');
	$t_subject 			= $this->getVar('t_subject');		// object
	$vs_add_label 		= $this->getVar('add_label');
	$va_settings 		= $this->getVar('settings');

	$vb_read_only		=	(isset($va_settings['readonly']) && $va_settings['readonly']);
	$vb_batch			=	$this->getVar('batch');
	
	if (!in_array($vs_default_upload_type = $this->getVar('defaultRepresentationUploadType'), array('upload', 'url', 'search'))) {
		$vs_default_upload_type = 'upload';
	}
	
	$vb_allow_fetching_from_urls = $this->request->getAppConfig()->get('allow_fetching_of_media_from_remote_urls');
	
	// generate list of inital form values; the bundle Javascript call will
	// use the template to generate the initial form
	$va_inital_values = array();
	$va_reps = $t_subject->getRepresentations(array('thumbnail', 'original'));
	$va_rep_type_list = $t_item->getTypeList();
	$va_errors = array();
	
	$vn_primary_id = 0;
	if (sizeof($va_reps)) {
		$o_type_config = Configuration::load($t_item->getAppConfig()->get('annotation_type_config'));
 		$va_annotation_type_mappings = $o_type_config->getAssoc('mappings');
 		
		foreach ($va_reps as $va_rep) {
			$vn_num_multifiles = $va_rep['num_multifiles'];
			if ($vs_extracted_metadata = caFormatMediaMetadata(caUnserializeForDatabase($va_rep['media_metadata']))) {
				$vs_extracted_metadata = "<div class='caObjectRepresentationMetadataDisplay'><h3>"._t('Extracted metadata').":</h3>\n{$vs_extracted_metadata}</div>\n";
			}
			$vs_md5 = isset($va_rep['info']['original']['MD5']) ? "<h3>"._t('MD5 signature').':</h3>'.$va_rep['info']['original']['MD5'] : '';

			if ($va_rep['is_primary']) {
				$vn_primary_id = $va_rep['representation_id'];
			}
			$va_inital_values[$va_rep['representation_id']] = array(
				'status' => $va_rep['status'], 
				'access_display' => $t_item->getChoiceListValue('access', $va_rep['access']), 
				'access' => $va_rep['access'],
				'rep_label' => $va_rep['label'],
				'is_primary' => (int)$va_rep['is_primary'],
				'is_primary_display' => ($va_rep['is_primary'] == 1) ? "<div class='caObjectRepresentationPrimaryDisplay'>"._t('PRIMARY')."</div>" : '', 
				'locale_id' => $va_rep['locale_id'], 
				'icon' => $va_rep['tags']['thumbnail'], 
				'mimetype' => $va_rep['info']['original']['PROPERTIES']['mimetype'], 
				'annotation_type' => isset($va_annotation_type_mappings[$va_rep['info']['original']['PROPERTIES']['mimetype']]) ? $va_annotation_type_mappings[$va_rep['info']['original']['PROPERTIES']['mimetype']] : null,
				'type' => $va_rep['info']['original']['PROPERTIES']['typename'], 
				'dimensions' => $va_rep['dimensions']['original'], 
				'filename' => $va_rep['info']['original_filename'] ? $va_rep['info']['original_filename'] : _t('Unknown'),
				'num_multifiles' => ($vn_num_multifiles ? (($vn_num_multifiles == 1) ? _t('+ 1 additional preview') : _t('+ %1 additional previews', $vn_num_multifiles)) : ''),
				'metadata' => $vs_extracted_metadata,
				'md5' => $vs_md5 ? "<div class='caObjectRepresentationMD5Display'>{$vs_md5}</div>" : "",
				'rep_type_id' => $va_rep['type_id'],
				'typename' => $va_rep_type_list[$va_rep['type_id']]['name_singular'],
				'fetched_from' => $va_rep['fetched_from'],
				'fetched_on' => date('c', $va_rep['fetched_on']),
				'fetched' => $va_rep['fetched_from'] ? "<div class='caObjectRepresentationSourceDisplay'>"._t("<h3>Fetched from:</h3> URL %1 on %2", '<a href="'.$va_rep['fetched_from'].'" target="_ext" title="'.$va_rep['fetched_from'].'">'.$va_rep['fetched_from'].'</a>', date('c', $va_rep['fetched_on']))."</div>" : ""
			);
			
			if(is_array($va_action_errors = $this->request->getActionErrors('ca_object_representations', $va_rep['representation_id']))) {
				foreach($va_action_errors as $o_error) {
					$va_errors[$va_rep['representation_id']][] = array('errorDescription' => $o_error->getErrorDescription(), 'errorCode' => $o_error->getErrorNumber());
				}
			}
		}
	}
	
	$va_failed_inserts = array();
	foreach($this->request->getActionErrorSubSources('ca_object_representations') as $vs_error_subsource) {
		if (substr($vs_error_subsource, 0, 4) === 'new_') {
			$va_action_errors = $this->request->getActionErrors('ca_object_representations', $vs_error_subsource);
			foreach($va_action_errors as $o_error) {
				$va_failed_inserts[] = array('icon' => '', '_errors' => array(array('errorDescription' => $o_error->getErrorDescription(), 'errorCode' => $o_error->getErrorNumber())));
			}
		}
	}
	
	if ($vb_batch) {
		print "<div class='editorBatchModeControl'>"._t("In batch")." ".
			caHTMLSelect($vs_id_prefix.$t_item->tableNum()."_rel_batch_mode", array(
				_t("do not use") => "_disabled_", 
				_t('add to each item') => '_add_', 
				_t('replace values') => '_replace_',
				_t('remove all values') => '_delete_'
			), array('id' => $vs_id_prefix.$t_item->tableNum()."_rel_batch_mode_select"))."</div>\n";
?>
	<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery('#<?php print $vs_id_prefix.$t_item->tableNum(); ?>_rel_batch_mode_select').change(function() {
				if ((jQuery(this).val() == '_disabled_') || (jQuery(this).val() == '_delete_')) {
					jQuery('#<?php print $vs_id_prefix.$t_item->tableNum().'_rel'; ?>').slideUp(250);
				} else {
					jQuery('#<?php print $vs_id_prefix.$t_item->tableNum().'_rel'; ?>').slideDown(250);
				}
			});
			jQuery('#<?php print $vs_id_prefix.$t_item->tableNum().'_rel'; ?>').hide();
		});
	</script>
<?php
	} else {
		print caEditorBundleShowHideControl($this->request, $vs_id_prefix.$t_item->tableNum().'_rel');
	}
?>
<div id="<?php print $vs_id_prefix.$t_item->tableNum().'_rel'; ?>" <?php print $vb_batch ? "class='editorBatchBundleContent'" : ''; ?>>
<?php
	//
	// Template to generate display for existing items
	//
?>
	<textarea class='caItemTemplate' style='display: none;'>
		<div id="<?php print $vs_id_prefix; ?>Item_{n}" class="labelInfo">

			<span class="formLabelError">{error}</span>
<?php 
	if (!$vb_read_only) {
?>
	<div style="float: right;">
		<div style="margin: 0 0 10px 5px;"><a href="#" class="caDeleteItemButton"><?php print caNavIcon($this->request, __CA_NAV_BUTTON_DEL_BUNDLE__); ?></a></div>
	</div>
<?php
	}
?>	
			<div style="width: 680px;">
				<div style="float: left;">
					<div class="caObjectRepresentationListItemImageThumb"><a href="#" onclick="caMediaPanel.showPanel('<?php print urldecode(caNavUrl($this->request, 'editor/objects', 'ObjectEditor', 'GetRepresentationEditor', array('object_id' => $t_subject->getPrimaryKey(), 'representation_id' => '{n}'))); ?>'); return false;">{icon}</a></div>
				</div>
				<div style="float: right; width: 550px;">
					<div style="float: left; width: 80%;">
						<div class='caObjectRepresentationListInfo'>
							<a title="{filename}"><?php print _t('Original file name: <em>{filename}</em>'); ?></a>
						</div>
						<div class='caObjectRepresentationListInfo'>
							{type}; {dimensions} {num_multifiles}
						</div>
						
						{is_primary_display}
						<div id='{fieldNamePrefix}is_primary_indicator_{n}' class='caObjectRepresentationPrimaryIndicator' style='display: none; color: #cc0000; font-style: italic;'><?php print _t('Will be primary after save'); ?></div>
						<input type="hidden" name="{fieldNamePrefix}is_primary_{n}" id="{fieldNamePrefix}is_primary_{n}" class="{fieldNamePrefix}is_primary" value=""/>
					
						<div class='caObjectRepresentationListAccessDisplay'>
							<h3>Access:</h3> {access_display}
							<span id="{fieldNamePrefix}change_{n}"><a href='#' onclick='caOpenRepresentationDetailEditor("{n}"); return false;'><?php print caNavIcon($this->request, __CA_NAV_BUTTON_UPDATE__, null, array('width' => '16', 'height' => '16')).'</a>'; ?></span>
						</div>
			
						<div id='{fieldNamePrefix}detail_editor_{n}' style='display: none; width: 100%;'>
							<div class="caObjectRepresentationDetailEditorElement"><?php print $t_item_label->htmlFormElement('name', '<div>^LABEL<br/>^ELEMENT</div>', array('classname' => 'caObjectRepresentationDetailEditorElement', 'id' => "{fieldNamePrefix}rep_label_{n}", 'name' => "{fieldNamePrefix}rep_label_{n}", "value" => "{rep_label}", 'no_tooltips' => false, 'tooltip_namespace' => 'bundle_ca_object_representations', 'textAreaTagName' => 'textentry', 'width' => 60)); ?></div>
							<br class="clear"/>
							<div class="caObjectRepresentationDetailEditorElement"><?php print $t_item->htmlFormElement('access', null, array('classname' => 'caObjectRepresentationDetailEditorElement', 'id' => "{fieldNamePrefix}access_{n}", 'name' => "{fieldNamePrefix}access_{n}", "value" => "{access}", 'no_tooltips' => false, 'tooltip_namespace' => 'bundle_ca_object_representations')); ?></div>
							<div class="caObjectRepresentationDetailEditorElement"><?php print $t_item->htmlFormElement('status', '<div>^LABEL<br/>^ELEMENT</div>', array('classname' => 'caObjectRepresentationDetailEditorElement', 'id' => "{fieldNamePrefix}status_{n}", 'name' => "{fieldNamePrefix}status_{n}", "value" => "{status}", 'no_tooltips' => false, 'tooltip_namespace' => 'bundle_ca_object_representations')); ?></div>
						</div>
						
					</div>
					
					<div style="float: right; width: 20%;">						
						<div class='caObjectRepresentationListActionButton'>
							<span id="{fieldNamePrefix}primary_{n}"><a href='#' onclick='caSetRepresentationAsPrimary("{n}"); return false;'><?php print caNavIcon($this->request, __CA_NAV_BUTTON_MAKE_PRIMARY__, null, array('width' => '16', 'height' => '16')).'</a> '._t('Make primary'); ?></span>
						</div>
						<div class='caObjectRepresentationListActionButton'>
							<span id="{fieldNamePrefix}edit_{n}"><?php print urldecode(caNavLink($this->request, caNavIcon($this->request, __CA_NAV_BUTTON_EDIT__), '', 'editor/object_representations', 'ObjectRepresentationEditor', 'Edit', array('representation_id' => "{n}"), array('id' => "{fieldNamePrefix}edit_button_{n}"))).' '._t('Edit full record'); ?></span>
						</div>
						<div class='caObjectRepresentationListActionButton'>
							<span id="{fieldNamePrefix}download_{n}"><?php print urldecode(caNavButton($this->request, __CA_NAV_BUTTON_DOWNLOAD__, 'Download', 'editor/objects', 'ObjectEditor', 'DownloadRepresentation', array('version' => 'original', 'representation_id' => "{n}", 'object_id' => $t_subject->getPrimaryKey(), 'download' => 1), array('id' => "{fieldNamePrefix}download_button_{n}"), array('no_background' => true, 'dont_show_content' => true))).' '._t('Download'); ?></span>
						</div>
						<div class="caAnnotationEditorLaunchButton annotationType{annotation_type} caObjectRepresentationListActionButton">
							<span id="{fieldNamePrefix}edit_annotations_{n}"><a href="#" onclick="caAnnotationEditor<?php print $vs_id_prefix; ?>.showPanel('<?php print urldecode(caNavUrl($this->request, 'editor/object_representations', 'ObjectRepresentationEditor', 'GetAnnotationEditor', array('representation_id' => '{n}'))); ?>'); return false;" id="{fieldNamePrefix}edit_annotations_button_{n}"><img src='<?php print $this->request->getThemeUrlPath()."/graphics/buttons/clock.png"; ?>' border='0' height='16px' width='16px'/></a> <?php print _t('Annotations'); ?></span>
						</div>
					</div>	
				</div>
			</div>
			
			<br class="clear"/>
			
			<div id="{fieldNamePrefix}media_metadata_container_{n}" >	
				<div class="caObjectRepresentationMetadataButton">
					<a href="#" id="{fieldNamePrefix}caObjectRepresentationMetadataButton_{n}" onclick="caToggleDisplayObjectRepresentationMetadata('{fieldNamePrefix}media_metadata_{n}', '{fieldNamePrefix}caObjectRepresentationMetadataButton_{n}'); return false;" class="caObjectRepresentationMetadataButton"><?php print "<img src='".$this->request->getThemeUrlPath()."/graphics/icons/downarrow.jpg' border='0' height='11px' width='11px'/>"; ?><?php print _t('Media metadata'); ?></a>
				</div>
				<div>
					<div id="{fieldNamePrefix}media_metadata_{n}" class="caObjectRepresentationMetadata">
						{fetched}
						{md5}
						{metadata}
					</div>
				</div>
			</div>
	
			<br class="clear"/>
		</div>
<?php
		print TooltipManager::getLoadHTML('bundle_ca_object_representations');
?>
	</textarea>
<?php
	//
	// Template to generate controls for creating new relationship
	//
	$vs_rel_dir = ($t_item_rel->getLeftTableName() == $t_subject->tableName()) ? 'ltol' : 'rtol';
	$vn_left_sub_type_id = ($t_item_rel->getLeftTableName() == $t_subject->tableName()) ? $t_subject->get('type_id') : null;
	$vn_right_sub_type_id = ($t_item_rel->getRightTableName() == $t_subject->tableName()) ? $t_subject->get('type_id') : null;
?>
	<textarea class='caNewItemTemplate' style='display: none;'>	
		<div id="<?php print $vs_id_prefix; ?>Item_{n}" class="labelInfo">

			<h2><?php print ($t_item_rel->hasField('type_id')) ? _t('Add representation with relationship type %1', $t_item_rel->getRelationshipTypesAsHTMLSelect($vs_rel_dir, $vn_left_sub_type_id, $vn_right_sub_type_id, array('name' => '{fieldNamePrefix}type_id_{n}'))) : _t('Add representation'); ?></h2>

			<span class="formLabelError">{error}</span>
			<div style="float: right;">
				<div style="margin: 0 0 10px 5px;"><a href="#" class="caDeleteItemButton"><?php print caNavIcon($this->request, __CA_NAV_BUTTON_DEL_BUNDLE__); ?></a></div>
			</div>
			
			<table id="{fieldNamePrefix}upload_options{n}">
				<tr>
					<td class='formLabel'><?php print caHTMLRadioButtonInput('{fieldNamePrefix}upload_type{n}', array('id' => '{fieldNamePrefix}upload_type_upload{n}', 'class' => '{fieldNamePrefix}upload_type{n}', 'value' => 'upload'), array('checked' => ($vs_default_upload_type == 'upload') ? 1 : 0)).' '._t('using uploaded file'); ?></td>
					<td class='formLabel'><?php print $t_item->htmlFormElement('media', '^ELEMENT', array('name' => "{fieldNamePrefix}media_{n}", 'id' => "{fieldNamePrefix}media_{n}", "value" => "", 'no_tooltips' => false, 'tooltip_namespace' => 'bundle_ca_object_representations', 'class' => 'uploadInput')); ?></td>
				</tr>
<?php
		if ($vb_allow_fetching_from_urls) {
?>
				<tr>
					<td class='formLabel'><?php print caHTMLRadioButtonInput('{fieldNamePrefix}upload_type{n}', array('id' => '{fieldNamePrefix}upload_type_url{n}', 'class' => '{fieldNamePrefix}upload_type{n}', 'value' => 'url'), array('checked' => ($vs_default_upload_type == 'url') ? 1 : 0)).' '._t('from URL'); ?></td>
					<td class='formLabel'><?php print caHTMLTextInput("{fieldNamePrefix}media_url_{n}", array('id' => '{fieldNamePrefix}media_url_{n}', 'class' => 'urlBg uploadInput'), array('width' => '450px')); ?></td>
				</tr>
<?php
		}
		
		if ((bool)$this->request->getAppConfig()->get($t_subject->tableName().'_allow_relationships_to_existing_representations')) {
?>
				<tr>
					<td class='formLabel'><?php print caHTMLRadioButtonInput('{fieldNamePrefix}upload_type{n}', array('id' => '{fieldNamePrefix}upload_type_search{n}', 'class' => '{fieldNamePrefix}upload_type{n}', 'value' => 'search'), array('checked' => ($vs_default_upload_type == 'search') ? 1 : 0)).' '._t('using existing'); ?></td>
					<td class='formLabel'>
						<?php print caHTMLTextInput('{fieldNamePrefix}autocomplete{n}', array('value' => '{{label}}', 'id' => '{fieldNamePrefix}autocomplete{n}', 'class' => 'lookupBg uploadInput'), array('width' => '465px')); ?>
<?php
	if ($t_item_rel && $t_item_rel->hasField('type_id')) {
?>
						<select name="<?php print $vs_id_prefix; ?>_type_id{n}" id="<?php print $vs_id_prefix; ?>_type_id{n}" style="display: none;"></select>
<?php
	}
?>
						<input type="hidden" name="<?php print $vs_id_prefix; ?>_id{n}" id="<?php print $vs_id_prefix; ?>_id{n}" value="{id}"/>
					</td>
				</tr>
<?php
		}
?>
			</table>
			
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("#{fieldNamePrefix}upload_options{n} tr td .uploadInput").prop('disabled', true);
					jQuery("#{fieldNamePrefix}upload_type_upload{n}").click(function() {
						jQuery("#{fieldNamePrefix}media_{n}").prop('disabled', false);
						jQuery("#{fieldNamePrefix}media_url_{n}").prop('disabled', true);
						jQuery("#{fieldNamePrefix}autocomplete{n}").prop('disabled', true);
					});
					jQuery("#{fieldNamePrefix}upload_type_url{n}").click(function() {
						jQuery("#{fieldNamePrefix}media_{n}").prop('disabled', true);
						jQuery("#{fieldNamePrefix}media_url_{n}").prop('disabled', false);
						jQuery("#{fieldNamePrefix}autocomplete{n}").prop('disabled', true);
					});
					jQuery("#{fieldNamePrefix}upload_type_search{n}").click(function() {
						jQuery("#{fieldNamePrefix}media_{n}").prop('disabled', true);
						jQuery("#{fieldNamePrefix}media_url_{n}").prop('disabled', true);
						jQuery("#{fieldNamePrefix}autocomplete{n}").prop('disabled', false);
					});
					
					jQuery("input.{fieldNamePrefix}upload_type{n}:checked").click();
				});
			</script>
	</div>
			
	<br class="clear"/>
</div>
	</textarea>
	
	<div class="bundleContainer">
		<div class="caItemList">
		
		</div>
<?php 
	if (!$vb_read_only) {
?>
		<div class='button labelInfo caAddItemButton'><a href='#'><?php print caNavIcon($this->request, __CA_NAV_BUTTON_ADD__); ?> <?php print $vs_add_label ? $vs_add_label : _t("Add representation")." &rsaquo;"; ?></a></div>
<?php
	}
?>
	</div>
</div>

<input type="hidden" id="<?php print $vs_id_prefix; ?>_ObjectRepresentationBundleList" name="<?php print $vs_id_prefix; ?>_ObjectRepresentationBundleList" value=""/>
<?php
	// order element
?>		
<script type="text/javascript">
	function caToggleDisplayObjectRepresentationMetadata(media_metadata_id, media_metadata_button_id) {
		var m = jQuery('#' + media_metadata_id).is(':hidden');
		jQuery('#' + media_metadata_id).slideToggle(300);
		jQuery('#' + media_metadata_button_id + ' img').rotate({ duration:500, angle: m ? 0 : 180, animateTo: m ? 180 : 0 });
	}
	
	function caOpenRepresentationDetailEditor(id) {
		jQuery('#<?php print $vs_id_prefix; ?>_detail_editor_' + id).slideToggle(250);
	}
	
	function caSetRepresentationAsPrimary(id) {
		jQuery('.<?php print $vs_id_prefix; ?>_is_primary').val('');
		jQuery('#<?php print $vs_id_prefix; ?>_is_primary_' + id).val('1');
		jQuery('.caObjectRepresentationPrimaryIndicator').hide();
		if (id != <?php print (int)$vn_primary_id; ?>) {
			jQuery('#<?php print $vs_id_prefix; ?>_is_primary_indicator_' + id).show();
		}
	}
	
	var caAnnotationEditor<?php print $vs_id_prefix; ?>;
	jQuery(document).ready(function() {
		caUI.initRelationBundle('#<?php print $vs_id_prefix.$t_item->tableNum().'_rel'; ?>', {
			fieldNamePrefix: '<?php print $vs_id_prefix; ?>_',
			templateValues: ['status', 'access', 'access_display', 'is_primary', 'is_primary_display', 'media', 'locale_id', 'icon', 'type', 'dimensions', 'filename', 'num_multifiles', 'metadata', 'rep_type_id', 'type_id', 'typename', 'fetched', 'label', 'id'],
			initialValues: <?php print json_encode($va_inital_values); ?>,
			errors: <?php print json_encode($va_errors); ?>,
			forceNewValues: <?php print json_encode($va_failed_inserts); ?>,
			itemID: '<?php print $vs_id_prefix; ?>Item_',
			templateClassName: 'caNewItemTemplate',
			initialValueTemplateClassName: 'caItemTemplate',
			itemListClassName: 'caItemList',
			itemClassName: 'labelInfo',
			addButtonClassName: 'caAddItemButton',
			deleteButtonClassName: 'caDeleteItemButton',
			showOnNewIDList: ['<?php print $vs_id_prefix; ?>_media_', '<?php print $vs_id_prefix; ?>_rep_type_id_div_'],
			hideOnNewIDList: ['<?php print $vs_id_prefix; ?>_media_show_update_', '<?php print $vs_id_prefix; ?>_edit_','<?php print $vs_id_prefix; ?>_download_', '<?php print $vs_id_prefix; ?>_media_metadata_container_', '<?php print $vs_id_prefix; ?>_edit_annotations_'],
			enableOnNewIDList: ['<?php print $vs_id_prefix; ?>_rep_type_id_'],
			showEmptyFormsOnLoad: 1,
			readonly: <?php print $vb_read_only ? "true" : "false"; ?>,
			isSortable: <?php print !$vb_read_only ? "true" : "false"; ?>,
			listSortOrderID: '<?php print $vs_id_prefix; ?>_ObjectRepresentationBundleList',
			defaultLocaleID: <?php print ca_locales::getDefaultCataloguingLocaleID(); ?>,
			
			relationshipTypes: <?php print json_encode($this->getVar('relationship_types_by_sub_type')); ?>,
			autocompleteUrl: '<?php print caNavUrl($this->request, 'lookup', 'ObjectRepresentation', 'Get', $va_lookup_params); ?>',
			autocompleteInputID: '<?php print $vs_id_prefix; ?>_autocomplete',
			
			extraParams: { exact: 1 }
		
		});
		if (caUI.initPanel) {
			caAnnotationEditor<?php print $vs_id_prefix; ?> = caUI.initPanel({ 
				panelID: "caAnnotationEditor<?php print $vs_id_prefix; ?>",						/* DOM ID of the <div> enclosing the panel */
				panelContentID: "caAnnotationEditor<?php print $vs_id_prefix; ?>ContentArea",		/* DOM ID of the content area <div> in the panel */
				exposeBackgroundColor: "#000000",				
				exposeBackgroundOpacity: 0.7,					
				panelTransitionSpeed: 400,						
				closeButtonSelector: ".close",
				centerHorizontal: true,
				onOpenCallback: function() {
					jQuery("#topNavContainer").hide(250);
				},
				onCloseCallback: function() {
					jQuery("#topNavContainer").show(250);
				}
			});
		}
		
		jQuery("body").append('<div id="caAnnotationEditor<?php print $vs_id_prefix; ?>" class="caAnnotationEditorPanel"><div id="caAnnotationEditor<?php print $vs_id_prefix; ?>ContentArea" class="caAnnotationEditorPanelContentArea"></div></div>');
	
		// Hide annotation editor links for non-timebased media
		jQuery(".caAnnotationEditorLaunchButton").hide();
		jQuery(".annotationTypeTimeBasedVideo, .annotationTypeTimeBasedAudio").show();
	});
</script>
