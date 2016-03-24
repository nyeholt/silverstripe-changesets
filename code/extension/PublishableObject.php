<?php

/**
 * An extension to abstract some of the functionality around publishing, for objects that aren't
 * extended from SiteTree
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class PublishableObject extends Versioned {
	private static $db = array(
		'Version' => 'Int',
		'Status' => "Enum('New,Draft,Published,Unpublished,Saved (update)')"
	);

	/**
	 * @param  null  $class
	 * @param        $extension
	 * @param        $args
	 * @return array
	 */
	public static function get_extra_config($class, $extension, $args) {
		return array(
			'has_one' => array(
				'CurrentVersion' => $class,
			),
			'has_many' => array(
				'Versions' => $class
			),
			'searchable_fields' => array()
		);
	}

	/**
	 *
	 */
	public function __construct() {
		parent::__construct(array('Stage', 'Live'));
	}

	public function onBeforeWrite() {
		if (!$this->owner->Status) {
			$this->owner->Status = 'New';
		}
	}

	/**
	 * Taken from SiteTree
	 *
	 * @param Member $member
	 * @return boolean
	 */
	public function canPublish($member = null) {
		if (!$member || !(is_a($member, 'Member')) || is_numeric($member))
			$member = Member::currentUser();
		if ($member && Permission::checkMember($member, "ADMIN"))
			return true;

		// fail over to canEdit()
		return $this->owner->canEdit($member);
	}

	public function onBeforePublish($original) {

	}

	public function onAfterPublish($original) {

	}

	/**
	 * Modified version of the SiteTree publish method.
	 *
	 * @return <type>
	 */
	public function doPublish() {
		if (!$this->owner->canPublish())
			return false;

		$class = $this->owner->class;
		$ownerId = $this->owner->ID;

		$dataClasses = ClassInfo::dataClassesFor($class);
		$dataClasses = array_values($dataClasses);

		$class = $dataClasses[count($dataClasses) - 1];

		$original = Versioned::get_one_by_stage("$class", "Live", "\"$class\".\"ID\" = $ownerId");
		if (!$original)
			$original = new $class();

		$this->owner->invokeWithExtensions('onBeforePublish', $original);

		// Handle activities undertaken by decorators
		$this->owner->Status = "Published";
		//$this->PublishedByID = Member::currentUser()->ID;
		$this->owner->write();
		$this->owner->publish("Stage", "Live");

		if ($this->owner->hasField('Sort')) {
			// find the table that actually defines the sortable field
			$class = get_class($this->owner);
			if ($this->owner->hasMethod('findClassDefiningSortField')) {
				$class = $this->owner->findClassDefiningSortField();
			}

			DB::query("UPDATE \"{$class}_Live\"
				SET \"Sort\" = (SELECT \"{$class}\".\"Sort\" FROM \"{$class}\" WHERE \"{$class}_Live\".\"ID\" = \"{$class}\".\"ID\")");
		}

		// Handle activities undertaken by decorators
		$this->owner->invokeWithExtensions('onAfterPublish', $original);

		return true;
	}

	function doUnpublish() {
		if (!$this->canPublish())
			return false;
		if (!$this->owner->ID)
			return false;

		$origStage = Versioned::current_stage();
		Versioned::reading_stage('Live');

		// This way our ID won't be unset
		$clone = clone $this->owner;
		$clone->delete();

		Versioned::reading_stage($origStage);

		return true;
	}

	/**
	 * Check if this page is new - that is, if it has yet to have been written
	 * to the database.
	 *
	 * @return boolean True if this page is new.
	 */
	function isNew() {
		/**
		 * This check was a problem for a self-hosted site, and may indicate a
		 * bug in the interpreter on their server, or a bug here
		 * Changing the condition from empty($this->owner->ID) to
		 * !$this->owner->ID && !$this->record['ID'] fixed this.
		 */
		if (empty($this->owner->ID))
			return true;

		if (is_numeric($this->owner->ID))
			return false;

		return stripos($this->owner->ID, 'new') === 0;
	}

	/**
	 * Compares current draft with live version,
	 * and returns TRUE if no draft version of this page exists,
	 * but the page is still published (after triggering "Delete from draft site" in the CMS).
	 *
	 * @return boolean
	 */
	function getIsDeletedFromStage() {
		if (!$this->owner->ID)
			return true;
		if ($this->isNew())
			return false;

		$stageVersion = Versioned::get_versionnumber_by_stage(get_class($this->owner), 'Stage', $this->owner->ID);

		// Return true for both completely deleted pages and for pages just deleted from stage.
		return !($stageVersion);
	}

	/**
	 * Return true if this page exists on the live site
	 */
	function getExistsOnLive() {
		return (bool) Versioned::get_versionnumber_by_stage(get_class($this->owner), 'Live', $this->owner->ID);
	}

	/**
	 * Compares current draft with live version,
	 * and returns TRUE if these versions differ,
	 * meaning there have been unpublished changes to the draft site.
	 *
	 * @return boolean
	 */
	public function getIsModifiedOnStage() {
		// new unsaved pages could be never be published
		if ($this->isNew())
			return false;

		$stageVersion = Versioned::get_versionnumber_by_stage(get_class($this->owner), 'Stage', $this->owner->ID);
		$liveVersion = Versioned::get_versionnumber_by_stage(get_class($this->owner), 'Live', $this->owner->ID);

		return ($stageVersion != $liveVersion);
	}

	/**
	 * Compares current draft with live version,
	 * and returns true if no live version exists,
	 * meaning the page was never published.
	 *
	 * @return boolean
	 */
	public function getIsAddedToStage() {
		// new unsaved pages could be never be published
		if ($this->isNew())
			return false;

		$stageVersion = Versioned::get_versionnumber_by_stage(get_class($this->owner), 'Stage', $this->owner->ID);
		$liveVersion = Versioned::get_versionnumber_by_stage(get_class($this->owner), 'Live', $this->owner->ID);

		return ($stageVersion && !$liveVersion);
	}

}
