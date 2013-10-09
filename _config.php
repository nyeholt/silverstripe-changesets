<?php

Director::addRules(100, array(
	'changeset-admin' => 'ChangesetsAdmin',
));

define('CHANGESETS_DIR', 'changesets');

// Add the following to your _config to enable this module
// DataObject::add_extension('SiteTree', 'ChangesetTrackable');

if (!class_exists('PublishableObject') && !class_exists('Publishable')) {
	throw new Exception('The changesets module requires the publishableobjects module (http://github.com/nyeholt/silverstripe-publishableobjects) ');
}