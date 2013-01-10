<?php

Director::addRules(60, array(
	'changeset-admin' => 'ChangesetsAdmin',
));

define('CHANGESETS_DIR', 'changesets');

// Add the following to your _config to enable this module
// DataObject::add_extension('SiteTree', 'ChangesetTrackable');

if (!class_exists('PublishableObject')) {
	throw new Exception('The changesets module requires the publishableobjects module (http://github.com/nyeholt/silverstripe-publishableobjects) ');
}