<?php
/**
 * Object that represents an item in a content changeset
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ContentChangesetItem extends DataObject implements CMSPreviewable {
    public static $db = array(
		'OtherID' => 'Int',
		'OtherClass' => 'Varchar(32)',
	);

	public static $has_one = array(
		'Changeset' => 'ContentChangeset',
	);
	
	public static $summary_fields = array(
		'DisplayLabel' => 'Title',
		'getRealItem.LastEdited' => 'Last Edited',
	);

	public function getRealItem() {
		return DataObject::get_by_id($this->OtherClass, $this->OtherID);
	}
	
	public function DisplayLabel() {
		$item = $this->getRealItem();
		
		return sprintf('%s (%s #%s)', $item->Title, $item->ID, $item->ClassName);
	}

	public function CMSEditLink() {
		$item = $this->getRealItem();
		if ($item instanceof CMSPreviewable) {
			return $item->CMSEditLink();
		}
	}

	public function Link() {
		$item = $this->getRealItem();
		if ($item instanceof CMSPreviewable) {
			return $item->Link();
		}
	}

	public function canView($member = null) {
		return $this->getRealItem()->canView($member);
	}
	
	public function canEdit($member = null) {
		return $this->getRealItem()->canEdit($member);
	}
	
	public function canDelete($member = null) {
		return $this->getRealItem()->canDelete($member);
	}
}