<?php

// in meza, this is set to false. Setting it again here just to be 100% clear
// about it since this is not the Approved Revs default.
$egApprovedRevsAutomaticApprovals = false;
// being abundantly clear for this one, too
$egApprovedRevsBlankIfUnapproved = false;


$egApprovedRevsPermissions = array (

        'All Pages' => array( 'group' => 'sysop' ),

        'Namespace Permissions' => array (
                NS_MAIN => array("group" => "Editors"), // User:Admin and User:Editor
                NS_USER => array(), // defaults to "self", e.g. any user can approve their own page
                NS_FILE => array(), // Admin
                // NS_TEMPLATE => array(),
                // NS_HELP => array(),
                // NS_PROJECT => array(),
        ),

        'Category Permissions' => array (
                "No override" => array( "user" => array("Basic"), "override" => false ), // Basic, Editor, and Admin
                "Override" => array( "user" => array("Basic") ), // Admin and Basic

                // Uncomment #2
                // "Expert pages" => array( "property" => "Is expert" ), // [[Is expert::Basic]] -> Basic and Admin
        ),

        'Page Permissions' => array (
                "Basic's page" => array( "creator" => true ), // Basic and Admin
                "Not just Basic's" => array( "creator" => true, "override" => false ), // Basic, Editor, and Admin
        )

);

$wgGroupPermissions['Editors'] = $wgGroupPermissions['user'];

// Uncomment #1:
// $egApprovedRevsShowNotApprovedMessage = true;

// Uncomment #2:
// Uncomment line above in "Category Permissions"

// Uncomment #3:
// $wgGroupPermissions['*']['viewlinktolatest'] = false;
// $wgGroupPermissions['Editors']['viewlinktolatest'] = true;
// $wgGroupPermissions['sysop']['viewlinktolatest'] = true;

// Uncomment #4:
// $egApprovedRevsAutomaticApprovals = false;
// $egApprovedRevsBlankIfUnapproved = true;

// Uncomment #5:
// $egApprovedRevsAutomaticApprovals = false;
// $egApprovedRevsBlankIfUnapproved = true;

// Uncomment #6:
// $egApprovedRevsAutomaticApprovals = true;
// $egApprovedRevsBlankIfUnapproved = true;

