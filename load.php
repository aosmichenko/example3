<?php
/**
 * Plugin Name: Tealium
 * Description: A framework for creating a Tealium data layer object.
 *
 * @package AMI\Shared
 */

// Include files
require_once AMIS_INC . 'tealium/class-tealium.php';
require_once AMIS_INC . 'tealium/taxonomies/tag-control.php';

// Run the load functions
AMI\Shared\Tealium::load();
AMI\Shared\Tealium\Taxonomies\Tag_Control\load();
