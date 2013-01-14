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
		'ContentVersion'	=> 'Int',		// the version of the item at the point of publication
	);

	public static $has_one = array(
		'Changeset' => 'ContentChangeset',
	);
	
	public static $summary_fields = array(
		'DisplayLabel'				=> 'Title',
		'getRealItem.LastEdited'	=> 'Last Edited',
		'getRealItem.getChangeType' => 'Change Type',
		'ContentVersion'			=> 'Published Version',
		'getRealItem.Version'		=> 'Current Version',
	);

	public function getRealItem() {
		$item = DataObject::get_by_id($this->OtherClass, $this->OtherID);
		if (!$item) {
			$item = ArrayData::create(array(
				'ID'		=> $this->OtherID,
				'ClassName'	=> $this->OtherClass,
				'Title'		=> 'deleted',
				'LastEdited' => 'deleted',
				'getChangeType' => 'deleted',
				'Version' => 'deleted'
			));
		}
		return $item;
	}
	
	public function DisplayLabel() {
		$item = $this->getRealItem();
		if ($item) {
			return sprintf('%s (%s #%s)', $item->Title, $item->ClassName, $item->ID);
		}
		return 'missing';
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
		return $this->Changeset()->canView($member);
	}
	
	public function canEdit($member = null) {
		return $this->Changeset()->canEdit($member);
	}
	
	public function canDelete($member = null) {
		return $this->Changeset()->canDelete($member);
	}
}