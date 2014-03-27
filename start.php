<?php

function au_group_activity_init() {
  	
  //activity page - replaces the default for groups with one offering more control
  elgg_register_page_handler('group_activity_plus','au_group_activity_page_handler');
  
 //intercept pagesetup so that we can get the group ID
 elgg_register_event_handler('pagesetup','system','au_group_activity_menu_setup');    
	  
}

elgg_register_event_handler('init', 'system', 'au_group_activity_init');


function au_group_activity_menu_setup(){	
  // register new menu item for activities and delete old one
  elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'au_group_activity_owner_block');
  
  
  
  
  
}





/**
 * Provide group owner_block link to activity page
 */
function au_group_activity_owner_block($hook, $type, $return, $params) {
	$group=$params['entity'];
	if (elgg_instanceof($group, 'group')) {
		//kill the activity menu
		foreach($return as $key=>$item){
	  	if($item->getName()=='activity'){
		  	unset($return[$key]);
	  	}
	  	
		}
		//add the menu item if activity is enabled
		if ($group->activity_enable != 'no'){
		$url = 'group_activity_plus/group/' . $params['entity']->guid . '/ingroup';
		$item = new ElggMenuItem('au_group_activity', elgg_echo('groups:activity'), $url);
		$return[] = $item;
	}
	}
	
	return $return;
}


// pluginhook to kill all the standard activity tabs called from activity page handler

function au_group_activity_kill_activity_tabs($hook,$type,$return,$params){
	//get rid of standard activity tabs
		foreach($return as $key=>$item){
			$tabs=array('all','mine','friend');
		  	if(in_array($item->getName(), $tabs)){
			  	unset($return[$key]);
		  	}
	  	}
	return $return;  		  	
}



function au_group_activity_tabs($handler,$guid,$selected){
	  		// kill standard activity tabs - we will add them back in soon
	  		elgg_register_plugin_hook_handler('register', 'menu:filter', 'au_group_activity_kill_activity_tabs',1000);		

			// we need to create tabs for outgroup and in-group activities
		    $tab = array(
		        'name' => "ingroup",
		        'text' => elgg_echo('au_group_activity:ingroupactivities'),
		        'href' => "$handler/group/{$guid}/ingroup",
		        'selected' => $selected=='ingroup'?true:false,
		     );
		        
			elgg_register_menu_item('filter', $tab);

		    $tab = array(
		        'name' => "outgroup",
		        'text' => elgg_echo('au_group_activity:outgroupactivities'),
		        'href' => "$handler/group/{$guid}/outgroup",
				'selected' => $selected=='outgroup'?true:false, 
		     );
		        
			elgg_register_menu_item('filter', $tab);
		    $tab = array(
		        'name' => "members",
		        'text' => elgg_echo('au_group_activity:memberactivities'),
		        'href' => "$handler/group/{$guid}/members",
				'selected' => $selected=='members'?true:false, 
		     );
		        
			elgg_register_menu_item('filter', $tab);

		    $tab = array(
		        'name' => "stats",
		        'text' => elgg_echo('au_group_activity:activitystats'),
		        'href' => "$handler/group/{$guid}/stats",
				'selected' => $selected=='stats'?true:false, 
		     );
		        
			elgg_register_menu_item('filter', $tab);
}
/**
 * Provide a group page for activities with tabs and better filtering

 */
function au_group_activity_page_handler($page,$handler) {
   	if ($page[0] == 'group') {
	   	$guid=$page[1];
	 	$group = get_entity($guid);	  	
	 	if (elgg_instanceof($group, 'group')) {
	 		//page 2 should indicate the page in question
		  	switch ($page[2]) {
				case 'ingroup' :
					au_group_activity_tabs($handler,$guid,'ingroup');
					$options['joins'] = array("JOIN {$db_prefix}entities e ON e.guid = rv.object_guid");
					$options['wheres']= array("e.container_guid = $guid");	
				  	$options['selected']='ingroup';  
				  	$titlepart=elgg_echo('au_group_activity:ingroupactivities');
					au_group_activity_handle_activity_page($group,$page[2],$options);
					break;
				case 'outgroup':
					au_group_activity_tabs($handler,$guid,'outgroup');
				  	$id=$group->group_acl;
				  	$members = get_members_of_access_collection($id,	TRUE);
				  	$options['subject_guids'] = $members;
				  	$titlepart=elgg_echo('au_group_activity:outgroupactivities');
				  	$options['selected']='outgroup';
					au_group_activity_handle_activity_page($group,$page[2],$options);
					break;
				case 'members':
					au_group_activity_tabs($handler,$guid,'members');				
					au_group_activity_handle_members_page($group,$page[3]);
					break;
				case 'stats':
					au_group_activity_tabs($handler,$guid,'stats');
					au_group_activity_handle_stats_page($group);
					break;
				default:
					return false;
			}
			return true;
		}else{
			//this is not an actual group - stop right here
			return false;
		}	
	}else{
		//it never even claimed to be one
			return false;
	}
	
}	
    	


function au_group_activity_handle_activity_page($group,$page,$options){
	
	$guid=$group->guid;
	//checking for filter	
	
	$type = preg_replace('[\W]', '', get_input('type', 'all'));
	$subtype = preg_replace('[\W]', '', get_input('subtype', ''));		
	if ($type != 'all') {
		$options['type'] = $type;
		if ($subtype) {
			$options['subtype'] = $subtype;
		}
	}
	
	if ($subtype) {
		$selector = "type=$type&subtype=$subtype";
	} else {
		$selector = "type=$type";
	}


	$db_prefix = elgg_get_config('dbprefix');
 // now start building the page 
   	$content="";  	
  	elgg_set_page_owner_guid($group->guid);  
	$title = elgg_echo('groups:activity').": $titlepart ";
	elgg_push_breadcrumb($group->name, $group->getURL());
	elgg_push_breadcrumb($title);

	// this is where we actually build the list of content
	$content.= elgg_view('core/river/filter', array('selector' => $selector));

	$options['pagination'] = true;

	//$results = elgg_list_river($options);
	if ($results) {
		$content .= $results;
	}else{
		$content .= '<p>' . elgg_echo('groups:activity:none') .'</p>';
	} 
	

	$params = array(
		'filter_context' => 'au_group_activity',
		'content' => $content,
		'title' => $title,
		'class' => 'elgg-river-layout',
	);
	$body = elgg_view_layout('content', $params);

	echo elgg_view_page($title, $body);

	  
   return true;
   }
}

	





function au_group_activity_handle_members_page($group,$member){
	$group=elgg_get_page_owner_entity();
	if (elgg_instanceof($group,'group')){
		$title = elgg_echo('groups:members:title', array($group->name));
	
		elgg_push_breadcrumb($group->name, $group->getURL());
		elgg_push_breadcrumb(elgg_echo('groups:members'));
	
		$db_prefix = elgg_get_config('dbprefix');
		$content = elgg_list_entities_from_relationship(array(
			'relationship' => 'member',
			'relationship_guid' => $group->guid,
			'inverse_relationship' => true,
			'type' => 'user',
			'limit' => 20,
			'joins' => array("JOIN {$db_prefix}users_entity u ON e.guid=u.guid"),
			'order_by' => 'u.name ASC',
		));
	
		$params = array(
			'content' => $content,
			'title' => $title,
			'filter' => '',
		);
		$body = elgg_view_layout('content', $params);
	
		echo elgg_view_page($title, $body);
	}
}


