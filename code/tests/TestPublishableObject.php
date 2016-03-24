<?php

/**
 * Description of TestPublishableObject
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TestPublishableObject extends SapphireTest {
	protected $extraDataObjects = array(
		'TestPublishableDataObject',
	);


	public function setUpOnce() {
		parent::setUpOnce();
	}

	public function testCreatePublishableObject() {
		$member = Security::findAnAdministrator();
		$member->logIn();

		$object = new TestPublishableDataObject();

		$this->assertTrue($object->hasExtension('Versioned'));

		$object->Title = 'This data object';
		$object->Content = 'Content of object';

		$this->assertTrue($object->isNew());

		$object->write();

		$this->assertNotNull($object->ID);

		$this->assertTrue($object->getIsModifiedOnStage());
		$this->assertFalse($object->getExistsOnLive());

		$object->doPublish();

		$this->assertTrue($object->getExistsOnLive());
		$this->assertFalse($object->getIsModifiedOnStage());
	}
}

class TestPublishableDataObject extends DataObject implements TestOnly {
	public static $db = array(
		'Title'			=> 'Varchar',
		'Content'		=> 'HTMLText',
	);

	public static $extensions = array(
		'PublishableObject',
		"Versioned('Stage','Live')",
	);
}