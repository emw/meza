<?php

$egApprovedRevsPermissions = array (

	'All Pages' => array( 'group' => 'sysop' ),

	'Namespace Permissions' => array (
		NS_MAIN => array("Group" => "Editors"), // User:Admin and User:Editor
		NS_USER => array(), // defaults to "self", e.g. any user can approve their own page
		NS_FILE => array(), // Admin
		// NS_TEMPLATE => array(),
		// NS_HELP => array(),
		// NS_PROJECT => array(),
	),

	'Category Permissions' => array (
		"+No override" => array( "User:Basic" ), // Basic, Editor, and Admin
		"Override" => array( "User:Basic" ), // Admin and Basic
		//"Expert pages" => array( "Property:Is expert" ), // [[Is expert::Basic]] -> Basic and Admin
	),

	'Page Permissions' => array (
		"Basic's Page" => array( "Creator" ), // Basic and Admin
		"+Not just Basic's" => array( "Creator" ), // Basic, Editor, and Admin
	)

);

// $wgGroupPermissions['*']['viewlinktolatest'] = false;
// $wgGroupPermissions['editors']['viewlinktolatest'] = true;
// $wgGroupPermissions['sysop']['viewlinktolatest'] = true;


// $egApprovedRevsAutomaticApprovals = false;


// $egApprovedRevsBlankIfUnapproved = true;
// $egApprovedRevsAutomaticApprovals = true;

