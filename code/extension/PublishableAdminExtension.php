<?php

/**
 * Extension added to controllers for data objects to ensure the draft
 * stage is selected when modifying objects
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class PublishableAdminExtension extends Extension {

	public function onBeforeInit() {
		Versioned::reading_stage('Stage');
	}
}
